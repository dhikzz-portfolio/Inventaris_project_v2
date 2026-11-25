<?php

namespace App\Http\Controllers;

use App\Enums\DamageClassification;
use App\Enums\DamageReportStatus;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\DamageReport;
use App\Models\Room;
use App\Services\DamageReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DamageReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = $request->validate([
            'classification' => ['nullable', Rule::in(DamageClassification::values())],
            'status' => ['nullable', Rule::in(DamageReportStatus::values())],
            'room_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reports = DamageReport::query()
            ->visibleTo($user)
            ->with(['unit.catalog', 'unit.room', 'unit.specification', 'reporter', 'handler'])
            ->ofClassification($filters['classification'] ?? null)
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['room_id'] ?? null), fn ($query) => $query->whereHas('unit', fn ($unit) => $unit->where('room_id', (int) $filters['room_id'])))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.trim((string) $filters['q']).'%';
                $query->whereHas('unit', fn ($unit) => $unit
                    ->where('inventory_code', 'like', $term)
                    ->orWhereHas('catalog', fn ($catalog) => $catalog->where('name', 'like', $term)));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('damage-reports.index', [
            'reports' => $reports,
            'classifications' => DamageClassification::cases(),
            'statuses' => DamageReportStatus::cases(),
            'filters' => $filters,
            'rooms' => Room::query()
                ->when(! $user->isSuperAdmin() && ! $user->isAdmin(), fn ($query) => $query->where('is_lab', true))
                ->when($user->isOperator(), fn ($query) => $query->whereIn('id', $user->roomScopes()->select('room_id')))
                ->orderBy('code')
                ->get(),
            'summary' => [
                'ringan' => DamageReport::query()->visibleTo($user)->where('classification', DamageClassification::RUSAK_RINGAN->value)->count(),
                'berat' => DamageReport::query()->visibleTo($user)->where('classification', DamageClassification::RUSAK_BERAT->value)->count(),
                'terbuka' => DamageReport::query()->visibleTo($user)->where('status', '!=', DamageReportStatus::SELESAI->value)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $units = AssetUnit::query()
            ->visibleTo($user)
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->whereNull('archived_at')
            ->with(['catalog.category', 'room', 'specification'])
            ->orderBy('inventory_code')
            ->get();

        return view('damage-reports.create', [
            'units' => $units,
            'selectedUnitId' => $request->integer('unit') ?: null,
            'classifications' => DamageClassification::cases(),
        ]);
    }

    public function store(Request $request, DamageReportService $service): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isOperator() || $user->isAdmin(), 403);

        $data = $request->validate([
            'asset_unit_id' => ['required', 'integer'],
            'classification' => ['required', Rule::in(DamageClassification::values())],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:2048'],
        ], [
            'description.required' => 'Deskripsi kerusakan wajib diisi.',
            'description.min' => 'Jelaskan kerusakan minimal 5 karakter.',
        ]);

        // Unit harus berada dalam cakupan pengguna; ID dari request tidak
        // dipercaya tanpa pemeriksaan ulang.
        $unit = AssetUnit::query()
            ->visibleTo($user)
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->whereKey($data['asset_unit_id'])
            ->first();

        if ($unit === null) {
            return back()->withErrors(['asset_unit_id' => 'Unit inventaris tidak ditemukan pada ruang lingkup Anda.'])->withInput();
        }

        $photoPaths = [];

        foreach ((array) $request->file('photos', []) as $photo) {
            $photoPaths[] = $photo->store('damage-reports', 'local');
        }

        if ($request->hasFile('photo')) {
            array_unshift($photoPaths, $request->file('photo')->store('damage-reports', 'local'));
        }

        $report = $service->report(
            $user,
            $unit,
            DamageClassification::from($data['classification']),
            $data['description'],
            $photoPaths[0] ?? null,
            $photoPaths,
        );

        return redirect()->route('damage-reports.show', $report)
            ->with('success', 'Laporan kerusakan tersimpan. Penghapusan inventaris tetap mengikuti alur resmi.');
    }

    public function show(Request $request, DamageReport $damageReport): View
    {
        $user = $request->user();

        abort_unless(
            DamageReport::query()->visibleTo($user)->whereKey($damageReport->id)->exists(),
            404
        );

        $damageReport->load(['unit.catalog.category', 'unit.room.building', 'unit.specification', 'reporter', 'handler', 'photos']);

        return view('damage-reports.show', [
            'report' => $damageReport,
            'logs' => $damageReport->logs()->with('user')->get(),
            'statuses' => $damageReport->status->allowedTransitions(),
            'canUpdate' => $user->isAdmin() || $user->isEditorValidator() || (int) $damageReport->reported_by === (int) $user->id,
        ]);
    }

    public function update(Request $request, DamageReport $damageReport, DamageReportService $service): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->isEditorValidator() || (int) $damageReport->reported_by === (int) $user->id,
            403
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(DamageReportStatus::values())],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->updateStatus(
            $damageReport,
            DamageReportStatus::from($data['status']),
            $data['note'] ?? null,
            $user,
        );

        return back()->with('success', 'Status penanganan diperbarui. Data inventaris tidak diubah.');
    }
}

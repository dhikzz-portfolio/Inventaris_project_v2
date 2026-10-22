<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LabelController extends Controller
{
    /**
     * Halaman cetak label: daftar unit fisik dalam cakupan akses pengguna,
     * dengan filter dan pemilihan unit (revisi 2.1 — label melekat pada unit,
     * bukan pada baris katalog).
     */
    public function index(Request $request): View
    {
        return view('labels.index', [
            'units' => $this->filteredUnits($request)
                ->with(['catalog.category', 'room'])
                ->orderBy('inventory_code')
                ->paginate(25)
                ->withQueryString(),
            'catalogs' => Item::query()
                ->approved()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->pluck('name', 'id'),
            'rooms' => Room::query()
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
                ->orderBy('code')
                ->get(),
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Condition::cases(),
            'statuses' => UnitStatus::cases(),
            'filters' => $this->filters($request),
        ]);
    }

    /**
     * Cetak label massal untuk unit yang dipilih. "Pilih semua sesuai filter"
     * mengirim filter aktif sehingga pemilihan tidak terbatas pada halaman
     * yang sedang tampil.
     */
    public function print(Request $request, LabelService $labels): Response|RedirectResponse
    {
        $data = $request->validate([
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'exists:asset_units,id'],
            'select_all' => ['nullable', 'boolean'],
        ]);

        $unitIds = $request->boolean('select_all')
            ? $this->filteredUnits($request)->pluck('id')
            : collect($data['unit_ids'] ?? []);

        $unitIds = $unitIds->unique()->values();

        if ($unitIds->isEmpty()) {
            return back()
                ->withErrors(['unit_ids' => 'Pilih minimal satu unit untuk dicetak labelnya.'])
                ->withInput();
        }

        [$pdf, $units] = $labels->printUnits($unitIds->all());

        return $pdf->stream('label-massal-'.count($units).'-unit-'.now()->format('Ymd-His').'.pdf');
    }

    public function scan(): View
    {
        return view('labels.scan');
    }

    /** Hasil scan QR: dukung kode unit baru maupun ID internal lama. */
    public function scanResolve(string $internalId): RedirectResponse
    {
        $codes = app(InventoryCodeService::class);
        $code = $codes->normalize($internalId);

        if ($codes->isValidFormat($code)) {
            $unit = $codes->resolve($code);
            $visible = $unit !== null && AssetUnit::query()
                ->visibleTo(request()->user())
                ->whereKey($unit->id)
                ->exists();

            if ($unit !== null && $visible) {
                return redirect()->route('units.show', $unit);
            }

            return redirect()->route('labels.index')
                ->with('error', "Kode \"{$code}\" tidak ditemukan dalam cakupan akses Anda.");
        }

        $item = Item::where('internal_id', $internalId)->first();

        if (! $item) {
            return redirect()->route('labels.index')
                ->with('error', "QR/ID \"{$internalId}\" tidak ditemukan di sistem.");
        }

        return redirect()->route('items.show', $item);
    }

    /**
     * Unit yang boleh dicetak: dalam cakupan akses pengguna, katalog sudah
     * disetujui, dan bukan unit yang sudah dihapus. Pencarian kode memakai
     * normalisasi yang sama dengan halaman detail katalog.
     */
    private function filteredUnits(Request $request)
    {
        $filters = $this->filters($request);

        return AssetUnit::query()
            ->visibleTo($request->user())
            ->whereHas('catalog', fn ($query) => $query->approved())
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->when($filters['catalog'] !== '', fn ($query) => $query->where('item_catalog_id', $filters['catalog']))
            ->when($filters['room'] !== '', fn ($query) => $query->where('room_id', $filters['room']))
            ->when($filters['category'] !== '', fn ($query) => $query->whereHas('catalog', fn ($query) => $query->where('category_id', $filters['category'])))
            ->when($filters['condition'] !== '', fn ($query) => $query->where('condition', $filters['condition']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $code = app(InventoryCodeService::class)->normalize($filters['search']);

                if ($this->isCodeLike($code)) {
                    $query->where('inventory_code', 'like', $code.'%');
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    /**
     * Nilai filter aktif — dibaca dari query string maupun body form cetak.
     *
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $read = fn (string $key) => trim((string) $request->input($key, $request->query($key, '')));

        return [
            'catalog' => $read('catalog'),
            'room' => $read('room'),
            'category' => $read('category'),
            'condition' => $read('condition'),
            'status' => $read('status'),
            'search' => $read('search'),
        ];
    }

    /** Potongan kode yang sah: G, G1, G1-0, G1-02, G1-02-1, G1-02-12. */
    private function isCodeLike(string $code): bool
    {
        return preg_match('/^[A-Z](?:[1-9](?:-(?:[0-9]{1,2}(?:-[0-9]{1,2})?)?)?)?$/', $code) === 1
            || app(InventoryCodeService::class)->isValidFormat($code);
    }
}

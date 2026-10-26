<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
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

    /**
     * Halaman scan universal untuk semua pengguna yang sudah login.
     *
     * Hasil pemindaian ditampilkan pada halaman yang sama (bukan dialihkan ke
     * katalog). Parameter `u`/`c` dari QR hanya petunjuk; unit selalu dicari
     * ulang dari database sehingga payload QR tidak pernah dipercaya mentah.
     */
    public function scan(Request $request): View
    {
        $code = trim((string) $request->query('code', ''));
        $unitId = $request->query('u');

        return view('labels.scan', [
            'code' => $code,
            'result' => $this->scanResult($request->user(), $code, $unitId),
            'qrRelease' => LabelService::QR_RELEASE,
        ]);
    }

    /** Kompatibilitas URL lama `/scan/{kode}`: tampilkan hasil di halaman scan. */
    public function scanResolve(Request $request, string $internalId): View
    {
        return view('labels.scan', [
            'code' => $internalId,
            'result' => $this->scanResult($request->user(), $internalId, null),
            'qrRelease' => LabelService::QR_RELEASE,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function scanResult(User $user, string $code, mixed $unitHint): ?array
    {
        if ($code === '' && $unitHint === null) {
            return null;
        }

        $codes = app(InventoryCodeService::class);
        $normalized = $codes->normalize($code);

        $unit = null;

        // Identitas stabil (ID unit) diprioritaskan bila QR menyertakannya.
        if ($unitHint !== null && ctype_digit((string) $unitHint)) {
            $unit = AssetUnit::query()->with(['catalog.category', 'room.major', 'room.building'])->find((int) $unitHint);
        }

        if ($unit === null && $normalized !== '') {
            if ($codes->isValidFormat($normalized)) {
                $unit = $codes->resolve($normalized);
                $unit?->load(['catalog.category', 'room.major', 'room.building']);
            } else {
                // Dukungan stiker lama berisi ID internal katalog.
                $legacy = Item::where('internal_id', $code)->first();

                if ($legacy !== null) {
                    return [
                        'status' => 'catalog',
                        'code' => (string) $legacy->internal_id,
                        'name' => $legacy->name,
                        'url' => route('items.show', $legacy),
                    ];
                }

                return [
                    'status' => 'invalid',
                    'code' => $code,
                    'message' => 'Format kode tidak valid. Contoh yang benar: G1-02-12.',
                ];
            }
        }

        if ($unit === null) {
            return [
                'status' => 'missing',
                'code' => $code,
                'message' => 'Kode/QR tidak ditemukan. Periksa kembali kode inventaris pada stiker.',
            ];
        }

        $visible = AssetUnit::query()
            ->visibleTo($user)
            ->whereKey($unit->id)
            ->exists();

        if (! $visible) {
            return [
                'status' => 'restricted',
                'code' => $unit->inventory_code,
                'message' => 'Unit ini berada di luar cakupan akses Anda. Detail inventaris memerlukan kewenangan pengelola yang lebih tinggi.',
            ];
        }

        $room = $unit->room;
        // Peminjam hanya menerima identitas dasar unit; nilai perolehan,
        // catatan internal, nomor register, dan riwayat tidak ditampilkan.
        $internal = $user->managesInventory() || $user->canApproveLoans();

        return [
            'status' => 'found',
            'unit_id' => $unit->id,
            'code' => $unit->inventory_code,
            'name' => $unit->catalog?->name,
            'specification' => $unit->catalog?->specification,
            'brand' => $unit->catalog?->brand,
            'model_type' => $unit->catalog?->model_type,
            'category' => $unit->catalog?->category?->name,
            'major' => $room?->major?->name,
            'building' => $room?->building?->name ?: ($room?->buildingCode() ? 'Gedung '.$room->buildingCode() : null),
            'floor' => $room?->floor,
            'room' => $room?->name,
            'room_code' => $room?->code,
            'unit_no' => $unit->unit_no,
            'condition' => $unit->condition->label(),
            'condition_badge' => $unit->condition->isDamaged() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800',
            'unit_status' => $unit->status->label(),
            'unit_status_badge' => $unit->status->badgeClass(),
            'archived' => $unit->archived_at !== null || $unit->status === UnitStatus::DIHAPUS,
            'register_no' => $internal ? $unit->register_no : null,
            'acquisition_value' => $internal ? $unit->acquisition_value : null,
            'notes' => $internal ? $unit->notes : null,
            'url' => route('units.show', $unit),
        ];
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

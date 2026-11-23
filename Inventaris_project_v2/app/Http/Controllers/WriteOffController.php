<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\NotificationType;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitStatusLog;
use App\Models\Building;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use App\Models\WriteOff;
use App\Models\WriteOffUnit;
use App\Services\AssetUnitService;
use App\Services\InventoryCodeService;
use App\Services\NotifyApp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WriteOffController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $units = $this->filteredUnits($user, $filters)
            ->with(['catalog.category', 'specification', 'room.building'])
            ->withExists(['openLoanItemUnit as is_on_loan'])
            ->withExists([
                'writeOffUnits as has_pending_write_off' => fn (Builder $query) => $query
                    ->whereHas('writeOff', fn (Builder $writeOff) => $writeOff->where('status', 'PENDING')),
            ])
            ->orderBy('inventory_code')
            ->paginate(25)
            ->withQueryString();

        // Daftar katalog harus mencakup setiap katalog yang unitnya muncul pada
        // daftar unit, termasuk katalog yang sudah diarsipkan atau berstatus
        // DIHAPUS tetapi unitnya belum dihapus, agar dua mode penghapusan
        // menawarkan cakupan yang sama.
        $catalogs = Item::query()
            ->approved()
            ->visibleTo($user)
            ->whereHas('units', fn (Builder $query) => $query
                ->visibleTo($user)
                ->where('status', '!=', UnitStatus::DIHAPUS->value))
            ->withCount([
                'units as active_units_count' => fn (Builder $query) => $query
                    ->visibleTo($user)
                    ->where('status', '!=', UnitStatus::DIHAPUS->value),
            ])
            ->with([
                'specifications' => fn ($query) => $query->withCount([
                    'units as active_units_count' => fn (Builder $unit) => $unit
                        ->visibleTo($user)
                        ->where('status', '!=', UnitStatus::DIHAPUS->value),
                ]),
            ])
            ->orderBy('name')
            ->get();

        return view('write-offs.index', [
            'units' => $units,
            'catalogs' => $catalogs,
            'catalogSummaries' => $this->catalogSummaries($catalogs, $user),
            'categories' => Category::orderBy('name')->get(),
            'brands' => Specification::query()->select('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'models' => Specification::query()->select('model_type')->distinct()->orderBy('model_type')->pluck('model_type'),
            'buildings' => Building::orderBy('code')->get(),
            'rooms' => Room::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->where('is_lab', true))
                ->orderBy('code')
                ->get(),
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG, UnitStatus::DIPINJAM],
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'in:UNIT,CATALOG'],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'distinct', 'exists:asset_units,id'],
            'select_all' => ['nullable', 'boolean'],
            'catalog_id' => ['nullable', 'required_if:mode,CATALOG', 'exists:item_catalogs,id'],
            'confirm_delete' => ['nullable', 'boolean'],
            'confirm_catalog' => ['nullable', 'boolean'],
            // Kompatibilitas pengajuan lama berbasis katalog + kuantitas.
            'item_id' => ['nullable', 'required_without:mode', 'exists:item_catalogs,id'],
            'quantity' => ['nullable', 'required_without:mode', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'berita_acara' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ], [
            'catalog_id.required_if' => 'Pilih katalog yang akan dihapus seluruh unitnya.',
        ]);

        $user = $request->user();
        $mode = $request->input('mode');

        if ($mode === null || $mode === '') {
            return $this->storeLegacy($request, $user);
        }

        if (! $request->boolean('confirm_delete')) {
            throw ValidationException::withMessages([
                'confirm_delete' => 'Konfirmasi penghapusan wajib dicentang sebelum pengajuan dikirim.',
            ]);
        }

        if ($mode === 'CATALOG') {
            if (! $request->boolean('confirm_catalog')) {
                throw ValidationException::withMessages([
                    'confirm_catalog' => 'Penghapusan seluruh katalog memerlukan konfirmasi khusus.',
                ]);
            }

            // Katalog yang diarsipkan tetap boleh dihapus unitnya karena
            // daftar katalog pada halaman ini memang menampilkannya.
            $catalog = Item::query()
                ->approved()
                ->visibleTo($user)
                ->whereKey($data['catalog_id'])
                ->first();

            if ($catalog === null) {
                abort(404);
            }

            $unitIds = $this->eligibleCatalogUnits($catalog, $user)->pluck('id')->all();
            $itemId = $catalog->id;

            if ($unitIds === []) {
                throw ValidationException::withMessages([
                    'catalog_id' => 'Tidak ada unit yang memenuhi syarat pada katalog ini (sedang dipinjam, sudah dihapus, atau menunggu persetujuan lain).',
                ]);
            }
        } else {
            $filters = $this->filters($request);

            $unitIds = $request->boolean('select_all')
                ? $this->filteredUnits($user, $filters)->pluck('id')->all()
                : array_map('intval', $data['unit_ids'] ?? []);

            $unitIds = array_values(array_unique($unitIds));

            if ($unitIds === []) {
                throw ValidationException::withMessages([
                    'unit_ids' => 'Pilih minimal satu unit, atau gunakan pilih semua sesuai filter.',
                ]);
            }

            $visibleIds = AssetUnit::query()
                ->visibleTo($user)
                ->whereIn('id', $unitIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($visibleIds) !== count($unitIds)) {
                throw ValidationException::withMessages([
                    'unit_ids' => 'Sebagian unit berada di luar cakupan akses Anda.',
                ]);
            }

            $itemId = (int) AssetUnit::query()->whereKey($unitIds[0])->value('item_catalog_id');
        }

        // Dokumen berita acara bersifat opsional.
        $path = $request->hasFile('berita_acara')
            ? $request->file('berita_acara')->store('write-offs', 'local')
            : null;
        // Admin adalah pemegang kewenangan akhir penghapusan (pengganti
        // Wakasek), sehingga pengajuannya langsung sah dan tercatat.
        $direct = $user->isApproverLevel2();

        $writeOff = DB::transaction(function () use ($unitIds, $itemId, $mode, $data, $user, $path, $direct) {
            // Kunci unit agar dua pengajuan bersamaan tidak menghapus unit yang sama.
            $units = AssetUnit::query()
                ->with(['catalog', 'specification'])
                ->whereIn('id', $unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($units->count() !== count($unitIds)) {
                throw ValidationException::withMessages([
                    'unit_ids' => 'Sebagian unit tidak ditemukan lagi. Muat ulang halaman.',
                ]);
            }

            foreach ($units as $unit) {
                $this->assertUnitEligible($unit);
            }

            $writeOff = WriteOff::create([
                'item_id' => $itemId,
                'quantity' => $units->count(),
                'mode' => $mode,
                'reason' => $data['reason'],
                'berita_acara_path' => $path,
                'status' => $direct ? 'APPROVED' : 'PENDING',
                'requested_by' => $user->id,
                'approved_by' => $direct ? $user->id : null,
                'approved_at' => $direct ? now() : null,
            ]);

            foreach ($units as $unit) {
                try {
                    WriteOffUnit::create([
                        'write_off_id' => $writeOff->id,
                        'asset_unit_id' => $unit->id,
                        'is_active' => 1,
                        'item_catalog_id' => $unit->item_catalog_id,
                        'specification_id' => $unit->specification_id,
                        'inventory_code' => $unit->inventory_code,
                        'brand' => $unit->specification?->brandLabel() ?? $unit->catalog?->brand,
                        'model_type' => $unit->specification?->modelLabel() ?? $unit->catalog?->model_type,
                        'room_id' => $unit->room_id,
                        'condition' => $unit->condition?->value,
                        'status_before' => $unit->status?->value,
                    ]);
                } catch (QueryException $exception) {
                    if ((string) $exception->getCode() === '23000') {
                        throw ValidationException::withMessages([
                            'unit_ids' => "Unit {$unit->inventory_code} sudah masuk pengajuan penghapusan aktif lain.",
                        ]);
                    }

                    throw $exception;
                }
            }

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($user)
                ->withProperties([
                    'mode' => $mode,
                    'unit_ids' => $units->pluck('id')->all(),
                    'kode' => $units->pluck('inventory_code')->all(),
                ])
                ->log(($direct ? 'Menghapus langsung (kewenangan Wakasek) ' : 'Mengajukan penghapusan ')
                    .$units->count().' unit inventaris');

            if ($direct) {
                $this->archiveSelectedUnits($writeOff, $user);
            } else {
                NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGHAPUSAN,
                    "Permohonan inventaris keluar: {$units->count()} unit menunggu persetujuan Wakasek.",
                    route('write-offs.pending'));
            }

            return $writeOff;
        });

        return redirect()->route('write-offs.index')
            ->with('success', $direct
                ? "Penghapusan {$writeOff->quantity} unit langsung diterapkan atas kewenangan Wakasek."
                : 'Permohonan inventaris keluar diajukan — menunggu persetujuan Wakasek Sarpras.');
    }

    /**
     * Alur lama (katalog + kuantitas) dipertahankan agar request/klien lama dan
     * data uji yang sudah ada tidak rusak. Pengajuan baru memakai mode UNIT atau
     * CATALOG dengan snapshot unit.
     */
    private function storeLegacy(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:item_catalogs,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'berita_acara' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $item = Item::findOrFail($data['item_id']);

        abort_unless(
            Item::query()->visibleTo($user)->whereKey($item->id)->exists(),
            404
        );

        $pendingQty = WriteOff::where('item_id', $item->id)->where('status', 'PENDING')->sum('quantity');
        $removable = $item->quantity - $item->activeLoanRemainingQuantity() - $pendingQty;

        abort_if((int) $data['quantity'] > $removable, 422,
            "Jumlah melebihi unit yang dapat dihapus (tersedia {$removable}, termasuk permohonan yang masih menunggu).");

        // Admin adalah pemegang kewenangan akhir penghapusan (pengganti
        // Wakasek), sehingga pengajuannya langsung sah dan tercatat.
        $direct = $user->isApproverLevel2();

        $writeOff = DB::transaction(function () use ($data, $item, $request, $direct, $user) {
            $writeOff = WriteOff::create([
                'item_id' => $item->id,
                'quantity' => $data['quantity'],
                'mode' => 'CATALOG',
                'reason' => $data['reason'],
                'berita_acara_path' => $request->hasFile('berita_acara')
                    ? $request->file('berita_acara')->store('write-offs', 'local')
                    : null,
                'status' => $direct ? 'APPROVED' : 'PENDING',
                'requested_by' => $user->id,
                'approved_by' => $direct ? $user->id : null,
                'approved_at' => $direct ? now() : null,
            ]);

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($user)
                ->log(($direct ? 'Menghapus langsung (kewenangan Wakasek) ' : 'Mengajukan penghapusan ')
                    ."{$item->name} ({$data['quantity']} {$item->unit})");

            if ($direct) {
                $this->archiveUnits($writeOff, $user);
            } else {
                NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGHAPUSAN,
                    "Permohonan inventaris keluar: \"{$item->name}\" ({$data['quantity']} {$item->unit}) menunggu persetujuan Wakasek.",
                    route('write-offs.pending'));
            }

            return $writeOff;
        });

        return redirect()->route('write-offs.index')
            ->with('success', $direct
                ? "Penghapusan \"{$item->name}\" langsung diterapkan atas kewenangan Wakasek — {$writeOff->quantity} unit diarsipkan."
                : 'Permohonan inventaris keluar diajukan — menunggu persetujuan Wakasek Sarpras.');
    }

    /** Daftar penghapusan menunggu approval (Wakasek). */
    public function pending(): View
    {
        return view('write-offs.pending', [
            'pending' => WriteOff::with(['item.room', 'requester', 'units.unit', 'units.specification', 'units.room'])
                ->where('status', 'PENDING')
                ->latest()
                ->get(),
            'decided' => WriteOff::with(['item', 'requester', 'approver'])
                ->withCount('units')
                ->where('status', '!=', 'PENDING')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function approve(Request $request, WriteOff $writeOff): RedirectResponse
    {
        abort_unless($writeOff->isPending(), 404);

        DB::transaction(function () use ($request, $writeOff) {
            $writeOff = WriteOff::query()->whereKey($writeOff->id)->lockForUpdate()->firstOrFail();

            abort_unless($writeOff->isPending(), 404);

            $writeOff->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            if ($writeOff->units()->exists()) {
                $this->archiveSelectedUnits($writeOff, $request->user());
            } else {
                $this->archiveUnits($writeOff, $request->user());
            }
        });

        return back()->with('success', 'Penghapusan disetujui — unit terpilih diarsipkan dan status katalog diperbarui.');
    }

    /**
     * Arsipkan tepat unit yang tercatat pada pengajuan. Kegagalan satu unit
     * membatalkan seluruh transaksi agar tidak ada penghapusan parsial.
     */
    private function archiveSelectedUnits(WriteOff $writeOff, ?User $actor): void
    {
        $rows = $writeOff->units()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Kunci seluruh unit dalam satu transaksi: mencegah unit yang sama
        // diarsipkan oleh pengajuan lain yang berjalan bersamaan.
        $units = AssetUnit::query()
            ->whereIn('id', $rows->pluck('asset_unit_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $catalogIds = [];
        $archived = 0;

        foreach ($rows as $row) {
            $unit = $units->get($row->asset_unit_id);

            if ($unit === null) {
                throw ValidationException::withMessages([
                    'unit_ids' => "Unit {$row->inventory_code} tidak ditemukan lagi.",
                ]);
            }

            if ($unit->status === UnitStatus::DIHAPUS || $unit->archived_at !== null) {
                throw ValidationException::withMessages([
                    'unit_ids' => "Unit {$unit->inventory_code} sudah dihapus sebelumnya.",
                ]);
            }

            // Validasi ulang di dalam transaksi: unit bisa saja dipinjam setelah pengajuan.
            if ($unit->isCurrentlyOnLoan()) {
                throw ValidationException::withMessages([
                    'unit_ids' => "Unit {$unit->inventory_code} sedang dipinjam dan tidak dapat dihapus.",
                ]);
            }

            $statusBefore = $unit->status;

            $unit->forceFill([
                'status' => UnitStatus::DIHAPUS,
                'archived_at' => now(),
            ])->save();

            AssetUnitStatusLog::create([
                'asset_unit_id' => $unit->id,
                'from_status' => $statusBefore->value,
                'to_status' => UnitStatus::DIHAPUS->value,
                'source' => AssetUnitStatusLog::SOURCE_TRANSACTION,
                'reason' => 'Penghapusan inventaris #'.$writeOff->id,
                'changed_by' => auth()->id(),
            ]);

            $catalogIds[] = $unit->item_catalog_id;
            $archived++;
        }

        if ($archived !== (int) $writeOff->quantity) {
            throw ValidationException::withMessages([
                'unit_ids' => "Jumlah unit yang diproses ({$archived}) tidak sesuai dengan pengajuan ({$writeOff->quantity}).",
            ]);
        }

        $service = app(AssetUnitService::class);

        foreach (array_unique($catalogIds) as $catalogId) {
            $catalog = Item::find($catalogId);

            if ($catalog === null) {
                continue;
            }

            $service->syncCatalogCounters($catalog);

            $remaining = $catalog->units()
                ->where('status', '!=', UnitStatus::DIHAPUS->value)
                ->count();

            if ($remaining === 0) {
                $catalog->update(['status' => ItemStatus::DIHAPUS]);
            }
        }

        activity('penghapusan')
            ->performedOn($writeOff)
            ->causedBy($actor)
            ->log("Menerapkan penghapusan {$rows->count()} unit terpilih");

        if ($writeOff->requester !== null && (int) $writeOff->requester->id !== (int) $actor?->id) {
            NotifyApp::to($writeOff->requester, NotificationType::PENGHAPUSAN,
                "Penghapusan {$writeOff->quantity} unit disetujui.", route('write-offs.index'));
        }
    }

    /**
     * Kompatibilitas pengajuan lama berbasis kuantitas katalog.
     */
    private function archiveUnits(WriteOff $writeOff, ?User $actor): void
    {
        $item = $writeOff->item;
        $units = app(AssetUnitService::class);

        // Data lama boleh belum memiliki baris unit. Untuk kasus itu angka
        // katalog tetap dikurangi seperti perilaku sebelumnya, tanpa membuat
        // unit fiktif. Setelah unit tersedia, jalur unit yang dipakai.
        if ($item->units()->count() === 0) {
            $remainingQty = max(0, (int) $item->quantity - (int) $writeOff->quantity);
            $item->update([
                'quantity' => $remainingQty,
                'status' => $remainingQty === 0 ? ItemStatus::DIHAPUS : $item->status,
            ]);

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($actor)
                ->log("Menerapkan penghapusan data lama {$item->name} ({$writeOff->quantity} unit, tanpa rincian unit)");

            return;
        }

        $candidates = $item->units()
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->lockForUpdate()
            ->get()
            ->sort(function ($a, $b) {
                $rank = fn ($unit) => match (true) {
                    $unit->status === UnitStatus::HILANG => 0,
                    $unit->condition === Condition::RUSAK_BERAT => 1,
                    $unit->condition === Condition::RUSAK_RINGAN => 2,
                    default => 3,
                };

                return [$rank($a), -$a->unit_no] <=> [$rank($b), -$b->unit_no];
            })
            ->take($writeOff->quantity);

        if ($candidates->count() !== (int) $writeOff->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Jumlah unit yang dapat dihapus ({$candidates->count()}) tidak sesuai dengan pengajuan ({$writeOff->quantity}).",
            ]);
        }

        foreach ($candidates as $unit) {
            $statusBefore = $unit->status;

            $unit->forceFill([
                'status' => UnitStatus::DIHAPUS,
                'archived_at' => now(),
            ])->save();

            AssetUnitStatusLog::create([
                'asset_unit_id' => $unit->id,
                'from_status' => $statusBefore->value,
                'to_status' => UnitStatus::DIHAPUS->value,
                'source' => AssetUnitStatusLog::SOURCE_TRANSACTION,
                'reason' => 'Penghapusan inventaris #'.$writeOff->id,
                'changed_by' => auth()->id(),
            ]);
        }

        $item->refresh();
        $units->syncCatalogCounters($item);

        $remaining = $item->units()->where('status', '!=', UnitStatus::DIHAPUS->value)->count();

        if ($remaining === 0) {
            $item->update(['status' => ItemStatus::DIHAPUS]);
        }

        activity('penghapusan')
            ->performedOn($writeOff)
            ->causedBy($actor)
            ->log("Menyetujui/menerapkan penghapusan {$item->name} ({$candidates->count()} unit diarsipkan, sisa unit: {$remaining})");

        if ($writeOff->requester !== null && (int) $writeOff->requester->id !== (int) $actor?->id) {
            NotifyApp::to($writeOff->requester, NotificationType::PENGHAPUSAN,
                "Penghapusan \"{$item->name}\" disetujui.", route('items.show', $item));
        }
    }

    public function reject(Request $request, WriteOff $writeOff): RedirectResponse
    {
        abort_unless($writeOff->isPending(), 404);

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $writeOff, $data): void {
            $writeOff = WriteOff::query()->whereKey($writeOff->id)->lockForUpdate()->firstOrFail();
            abort_unless($writeOff->isPending(), 404);

            $writeOff->update([
                'status' => 'REJECTED',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'decision_note' => $data['decision_note'] ?? null,
            ]);

            // Lepas kunci unik agar unit boleh diajukan kembali, tanpa
            // menghapus snapshot histori penolakan.
            $writeOff->units()->update(['is_active' => null]);

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($request->user())
                ->log('Menolak penghapusan barang');
        });

        NotifyApp::to($writeOff->requester, NotificationType::PENGHAPUSAN,
            'Penghapusan "'.$writeOff->item->name.'" DITOLAK'.($data['decision_note'] ? ' — '.$data['decision_note'] : ''),
            route('write-offs.index'));

        return back()->with('success', 'Permohonan penghapusan ditolak.');
    }

    /**
     * Unit yang dapat dihapus pada satu katalog: berada dalam cakupan pengguna,
     * bukan unit terhapus, tidak sedang dipinjam, dan belum masuk pengajuan yang
     * masih menunggu.
     */
    private function eligibleCatalogUnits(Item $catalog, User $user): Builder
    {
        return AssetUnit::query()
            ->visibleTo($user)
            ->where('item_catalog_id', $catalog->id)
            ->whereNotIn('status', [UnitStatus::DIHAPUS->value, UnitStatus::DIPINJAM->value])
            ->whereNull('archived_at')
            ->whereDoesntHave('openLoanItemUnit')
            ->whereDoesntHave('writeOffUnits', fn (Builder $query) => $query
                ->where('is_active', true));
    }

    private function assertUnitEligible(AssetUnit $unit): void
    {
        if ($unit->status === UnitStatus::DIHAPUS || $unit->archived_at !== null) {
            throw ValidationException::withMessages([
                'unit_ids' => "Unit {$unit->inventory_code} sudah dihapus.",
            ]);
        }

        if ($unit->status === UnitStatus::DIPINJAM || $unit->isCurrentlyOnLoan()) {
            throw ValidationException::withMessages([
                'unit_ids' => "Unit {$unit->inventory_code} sedang dipinjam dan tidak dapat dihapus.",
            ]);
        }

        if ($unit->hasPendingWriteOff() || $unit->hasActiveWriteOff()) {
            throw ValidationException::withMessages([
                'unit_ids' => "Unit {$unit->inventory_code} sudah masuk pengajuan penghapusan aktif lain.",
            ]);
        }
    }

    /**
     * Filter inventaris keluar memakai parameter yang sama dengan mutasi/cetak
     * label. Semua kondisi digabung dengan AND dan dapat dipakai bersamaan.
     *
     * @param  array<string, string>  $filters
     */
    private function filteredUnits(User $user, array $filters): Builder
    {
        $codes = app(InventoryCodeService::class);

        // Status katalog sengaja tidak difilter: unit yang masih hidup pada
        // katalog berstatus DIHAPUS/arsip harus tetap dapat dihapus dari sini
        // (mode per katalog juga menampilkan katalog tersebut).
        return AssetUnit::query()
            ->visibleTo($user)
            ->whereHas('catalog', fn (Builder $query) => $query->approved())
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters, $codes) {
                $term = '%'.mb_strtoupper(trim($filters['search'])).'%';
                $normalized = $codes->normalize($filters['search']);

                $query->where(function (Builder $nested) use ($term, $normalized) {
                    $nested->whereRaw('UPPER(inventory_code) LIKE ?', [$term])
                        ->orWhereHas('catalog', fn (Builder $catalog) => $catalog->whereRaw('UPPER(name) LIKE ?', [$term]));

                    if ($normalized !== '') {
                        $nested->orWhereRaw('UPPER(inventory_code) LIKE ?', ['%'.$normalized.'%']);
                    }
                });
            })
            ->when($filters['catalog'] !== '', fn (Builder $query) => $query->where('item_catalog_id', (int) $filters['catalog']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->whereHas(
                'catalog',
                fn (Builder $catalog) => $catalog->where('category_id', (int) $filters['category'])
            ))
            ->when($filters['brand'] !== '', fn (Builder $query) => $query->whereHas(
                'specification',
                fn (Builder $specification) => $specification->where('brand', $filters['brand'])
            ))
            ->when($filters['model'] !== '', fn (Builder $query) => $query->whereHas(
                'specification',
                fn (Builder $specification) => $specification->where('model_type', $filters['model'])
            ))
            ->when($filters['building'] !== '', fn (Builder $query) => $query->whereHas(
                'room.building',
                fn (Builder $building) => $building->where('code', strtoupper($filters['building']))
            ))
            ->when($filters['floor'] !== '', fn (Builder $query) => $query->whereHas(
                'room',
                fn (Builder $room) => $room->where('floor', (int) $filters['floor'])
            ))
            ->when($filters['room'] !== '', fn (Builder $query) => $query->where('room_id', (int) $filters['room']))
            ->when($filters['condition'] !== '', fn (Builder $query) => $query->where('condition', $filters['condition']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']));
    }

    /**
     * Ringkasan katalog untuk peringatan penghapusan menyeluruh.
     *
     * @param  Collection<int, Item>  $catalogs
     * @return array<int, array<string, mixed>>
     */
    private function catalogSummaries($catalogs, User $user): array
    {
        $summaries = [];

        foreach ($catalogs as $catalog) {
            $eligible = $this->eligibleCatalogUnits($catalog, $user)->with('room.building')->get();

            $summaries[$catalog->id] = [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'unit_count' => (int) $catalog->active_units_count,
                'eligible_count' => $eligible->count(),
                'spec_count' => $catalog->specifications->count(),
                'specs' => $catalog->specifications->map(fn (Specification $specification) => [
                    'label' => $specification->displayName(),
                    'units' => (int) $specification->active_units_count,
                ])->values()->all(),
                'locations' => $eligible->map(fn (AssetUnit $unit) => $unit->locationLabel())->unique()->values()->all(),
            ];
        }

        return $summaries;
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $read = fn (string $key) => trim((string) $request->query($key, $request->input($key, '')));

        return [
            'search' => $read('search'),
            'catalog' => $read('catalog'),
            'category' => $read('category'),
            'brand' => $read('brand'),
            'model' => $read('model'),
            'building' => $read('building'),
            'floor' => $read('floor'),
            'room' => $read('room'),
            'condition' => $read('condition'),
            'status' => $read('status'),
        ];
    }
}

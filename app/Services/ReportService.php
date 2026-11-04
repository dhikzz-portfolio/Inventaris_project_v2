<?php

namespace App\Services;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Major;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel;

class ReportService
{
    /**
     * Data laporan bulanan.
     *
     * @param  string  $period  YYYY-MM
     * @param  string  $scope  SEMUA | KATEGORI | RUANGAN | JURUSAN
     * @param  User|null  $user  Pengguna yang meminta laporan; menentukan cakupan data.
     */
    public function data(
        string $period,
        string $scope = 'SEMUA',
        ?int $scopeId = null,
        ?User $user = null,
        ?string $condition = null,
    ): array {
        $start = Carbon::parse($period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Ketua Laboran mencakup seluruh laboratorium, Laboran/K3 hanya ruangan
        // yang ditugaskan, dan bukan inventaris umum sekolah.
        $labOnly = $user !== null && ! $user->isSuperAdmin();
        $roomScopedIds = $user?->isOperator()
            ? $user->roomScopes()->pluck('room_id')->map(fn ($id) => (int) $id)->all()
            : null;

        $conditionFilter = filled($condition) && Condition::tryFrom((string) $condition) !== null
            ? (string) $condition
            : null;

        $items = Item::approved()
            ->with(['room.major', 'category'])
            ->when($labOnly, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('is_lab', true)))
            ->when($roomScopedIds !== null, fn ($q) => $q->whereIn('room_id', $roomScopedIds))
            ->when($scope === 'KATEGORI' && $scopeId, fn ($q) => $q->where('category_id', $scopeId))
            ->when($scope === 'RUANGAN' && $scopeId, fn ($q) => $q->where('room_id', $scopeId))
            ->when($scope === 'JURUSAN' && $scopeId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)))
            ->where('status', '!=', 'DIHAPUS')
            ->orderBy('name')
            ->get();

        // Unit fisik dalam cakupan & scope yang sama — sumber angka kondisi/status.
        $units = AssetUnit::query()
            ->visibleTo($user)
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->whereHas('catalog', fn ($query) => $query->approved()
                ->when($scope === 'KATEGORI' && $scopeId, fn ($qq) => $qq->where('category_id', $scopeId))
                ->when($scope === 'JURUSAN' && $scopeId, fn ($qq) => $qq->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)))
            )
            ->when($scope === 'RUANGAN' && $scopeId, fn ($query) => $query->where('room_id', $scopeId))
            ->when($conditionFilter !== null, fn ($query) => $query->where('condition', $conditionFilter))
            ->with(['catalog.category', 'room', 'specification'])
            ->orderBy('inventory_code')
            ->get();

        // Rincian unit saling eksklusif mengikuti urutan prioritas PRD 5.5:
        // DIHAPUS (keluar hitungan) → HILANG → DIPINJAM → rusak → baik.
        $unitBuckets = $units->countBy(fn (AssetUnit $unit) => match (true) {
            $unit->status === UnitStatus::HILANG => 'hilang',
            $unit->status === UnitStatus::DIPINJAM => 'dipinjam',
            $unit->condition === Condition::RUSAK_RINGAN => 'rusak_ringan',
            $unit->condition === Condition::RUSAK_BERAT => 'rusak_berat',
            default => 'baik',
        });

        $loansInPeriod = Loan::whereBetween('created_at', [$start, $end])
            ->when($labOnly, fn ($q) => $q->whereHas('loanItems.item.room', fn ($r) => $r->where('is_lab', true)))
            ->when($roomScopedIds !== null, fn ($q) => $q->whereHas('loanItems.item', fn ($i) => $i->whereIn('room_id', $roomScopedIds)));

        if ($scope !== 'SEMUA' && $scopeId) {
            $loansInPeriod->whereHas('loanItems.item', function ($q) use ($scope, $scopeId) {
                $q->when($scope === 'KATEGORI', fn ($qq) => $qq->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($qq) => $qq->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($qq) => $qq->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            });
        }

        $loansInPeriod = $loansInPeriod->get();

        // Kerusakan terjadi pada bulan tersebut (condition_in terisi saat pengembalian).
        $damageRows = LoanItem::whereNotNull('condition_in')
            ->where('condition_in', '!=', 'BAIK')
            ->whereHas('loan', fn ($q) => $q->whereNotNull('returned_at')->whereBetween('returned_at', [$start, $end]))
            ->when($labOnly, fn ($q) => $q->whereHas('item.room', fn ($r) => $r->where('is_lab', true)))
            ->when($roomScopedIds !== null, fn ($q) => $q->whereHas('item', fn ($i) => $i->whereIn('room_id', $roomScopedIds)))
            ->when($scope !== 'SEMUA' && $scopeId, fn ($q) => $q->whereHas('item', function ($qq) use ($scope, $scopeId) {
                $qq->when($scope === 'KATEGORI', fn ($q3) => $q3->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($q3) => $q3->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($q3) => $q3->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            }))
            ->with('item')
            ->get();

        $movements = ItemMovement::whereBetween('moved_at', [$start, $end])
            ->when($labOnly, fn ($q) => $q->whereHas('item.room', fn ($r) => $r->where('is_lab', true)))
            ->when($roomScopedIds !== null, fn ($q) => $q->whereHas('item', fn ($i) => $i->whereIn('room_id', $roomScopedIds)))
            ->when($scope !== 'SEMUA' && $scopeId, fn ($q) => $q->whereHas('item', function ($qq) use ($scope, $scopeId) {
                $qq->when($scope === 'KATEGORI', fn ($q3) => $q3->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($q3) => $q3->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($q3) => $q3->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            }))
            ->with(['item', 'fromRoom', 'toRoom', 'mover'])
            ->get();

        // Cakupan penghapusan mengikuti unit yang benar-benar tercatat pada
        // `write_off_units`; `write_offs.item_id` hanya dipakai untuk transaksi
        // lama yang belum memiliki rincian unit.
        $unitScope = function ($query) use ($scope, $scopeId, $labOnly, $roomScopedIds): void {
            $query->when($labOnly, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('is_lab', true)))
                ->when($roomScopedIds !== null, fn ($q) => $q->whereIn('room_id', $roomScopedIds))
                ->when($scope === 'KATEGORI' && $scopeId, fn ($q) => $q->whereHas('catalog', fn ($c) => $c->where('category_id', $scopeId)))
                ->when($scope === 'RUANGAN' && $scopeId, fn ($q) => $q->where('room_id', $scopeId))
                ->when($scope === 'JURUSAN' && $scopeId, fn ($q) => $q->whereHas('catalog.room', fn ($r) => $r->where('major_id', $scopeId)));
        };

        $legacyScope = function ($query) use ($scope, $scopeId, $labOnly, $roomScopedIds): void {
            $query->when($labOnly, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('is_lab', true)))
                ->when($roomScopedIds !== null, fn ($q) => $q->whereIn('room_id', $roomScopedIds))
                ->when($scope === 'KATEGORI' && $scopeId, fn ($q) => $q->where('category_id', $scopeId))
                ->when($scope === 'RUANGAN' && $scopeId, fn ($q) => $q->where('room_id', $scopeId))
                ->when($scope === 'JURUSAN' && $scopeId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
        };

        $writeOffs = WriteOff::where('status', 'APPROVED')
            ->whereBetween('updated_at', [$start, $end])
            ->where(function ($query) use ($unitScope, $legacyScope): void {
                $query->whereHas('units', fn ($units) => $unitScope($units))
                    ->orWhere(function ($legacy) use ($legacyScope): void {
                        $legacy->whereDoesntHave('units')
                            ->whereHas('item', fn ($item) => $legacyScope($item));
                    });
            })
            ->with([
                'item.room',
                'requester',
                'approver',
                'units.catalog.category',
                'units.catalog.room',
                'units.specification',
                'units.room.building',
            ])
            ->get();

        // Satu baris per unit fisik. Transaksi lama tanpa rincian unit tetap
        // ditampilkan sebagai satu baris katalog dengan penanda legacy — tidak
        // ada unit fiktif yang dibuat.
        $writeOffRows = collect();

        foreach ($writeOffs as $writeOff) {
            if ($writeOff->units->isNotEmpty()) {
                foreach ($writeOff->units as $row) {
                    // Transaksi boleh lintas katalog, tetapi baris yang keluar
                    // dari cakupan peran tidak boleh ikut tampil.
                    if ($labOnly && ! $row->room?->is_lab) {
                        continue;
                    }

                    if ($roomScopedIds !== null && ! in_array((int) $row->room_id, $roomScopedIds, true)) {
                        continue;
                    }

                    if ($scope === 'KATEGORI' && $scopeId && (int) $row->catalog?->category_id !== (int) $scopeId) {
                        continue;
                    }

                    if ($scope === 'RUANGAN' && $scopeId && (int) $row->room_id !== (int) $scopeId) {
                        continue;
                    }

                    if ($scope === 'JURUSAN' && $scopeId && (int) $row->catalog?->room?->major_id !== (int) $scopeId) {
                        continue;
                    }

                    $writeOffRows->push((object) [
                        'write_off_id' => $writeOff->id,
                        'legacy' => false,
                        'date' => $writeOff->updated_at,
                        'catalog' => $row->catalog?->name ?? $writeOff->item?->name,
                        'code' => $row->inventory_code,
                        'brand' => $row->specification?->brandLabel() ?: $row->brand,
                        'model_type' => $row->specification?->modelLabel() ?: $row->model_type,
                        'specification' => $row->specification?->displayName(),
                        'room' => $row->room
                            ? trim(collect([$row->room->code, $row->room->name])->filter()->implode(' — '))
                            : null,
                        'quantity' => 1,
                        'reason' => $writeOff->reason,
                        'requester' => $writeOff->requester?->name,
                        'approver' => $writeOff->approver?->name,
                    ]);
                }

                continue;
            }

            $item = $writeOff->item;

            $writeOffRows->push((object) [
                'write_off_id' => $writeOff->id,
                'legacy' => true,
                'date' => $writeOff->updated_at,
                'catalog' => $item?->name,
                'code' => null,
                'brand' => $item?->brand,
                'model_type' => $item?->model_type,
                'specification' => $item?->specification,
                'room' => $item?->room
                    ? trim(collect([$item->room->code, $item->room->name])->filter()->implode(' — '))
                    : null,
                'quantity' => (int) $writeOff->quantity,
                'reason' => $writeOff->reason,
                'requester' => $writeOff->requester?->name,
                'approver' => $writeOff->approver?->name,
            ]);
        }

        // Pengadaan pada periode: diajukan, diputuskan, atau diterima pada bulan ini.
        $acquisitionRows = Item::query()
            ->with(['room', 'category', 'requester', 'approver'])
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('acquisition_decided_at', [$start, $end])
                    ->orWhereBetween('acquisition_received_at', [$start, $end]);
            })
            ->when($labOnly, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('is_lab', true)))
            ->when($roomScopedIds !== null, fn ($q) => $q->whereIn('room_id', $roomScopedIds))
            ->when($scope === 'KATEGORI' && $scopeId, fn ($q) => $q->where('category_id', $scopeId))
            ->when($scope === 'RUANGAN' && $scopeId, fn ($q) => $q->where('room_id', $scopeId))
            ->when($scope === 'JURUSAN' && $scopeId, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)))
            ->orderBy('created_at')
            ->get();

        // Laporan kerusakan unit (bukan sekadar kerusakan saat pengembalian).
        $damageReportRows = DamageReport::query()
            ->visibleTo($user)
            ->whereBetween('created_at', [$start, $end])
            ->when($conditionFilter !== null, fn ($q) => $q->where('reported_condition', $conditionFilter))
            ->when($scope === 'KATEGORI' && $scopeId, fn ($q) => $q->whereHas('unit.catalog', fn ($c) => $c->where('category_id', $scopeId)))
            ->when($scope === 'RUANGAN' && $scopeId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('room_id', $scopeId)))
            ->when($scope === 'JURUSAN' && $scopeId, fn ($q) => $q->whereHas('unit.room', fn ($r) => $r->where('major_id', $scopeId)))
            ->with(['unit.catalog.category', 'unit.room', 'unit.specification', 'reporter'])
            ->orderBy('created_at')
            ->get();

        return [
            'setting' => InstitutionSetting::current(),
            'period' => $period,
            'periodLabel' => $start->translatedFormat('F Y'),
            'scope' => $scope,
            'scopeLabel' => collect([
                match ($scope) {
                    'KATEGORI' => 'Kategori: '.(Category::find($scopeId)?->name ?? '-'),
                    'RUANGAN' => 'Ruangan: '.(Room::find($scopeId)?->name ?? '-'),
                    'JURUSAN' => 'Jurusan: '.(Major::find($scopeId)?->name ?? '-'),
                    default => $labOnly ? 'Seluruh laboratorium' : 'Seluruh sekolah',
                },
                $conditionFilter !== null
                    ? 'Kondisi: '.(Condition::tryFrom($conditionFilter)?->label() ?? $conditionFilter)
                    : null,
            ])->filter()->implode(' • '),
            'condition' => $conditionFilter,
            'items' => $items,
            'units' => $units,
            'loans' => $loansInPeriod->load([
                'borrower',
                'requester',
                'event',
                'loanItems' => fn ($q) => $q
                    ->when($labOnly, fn ($qq) => $qq->whereHas('item.room', fn ($r) => $r->where('is_lab', true)))
                    ->with('item'),
            ]),
            'summary' => [
                // Jumlah katalog (jenis barang) berbeda dari jumlah unit fisik.
                'total_barang' => $items->count(),
                'total_unit' => $units->count(),
                'baik' => (int) $unitBuckets->get('baik', 0),
                'rusak_ringan' => (int) $unitBuckets->get('rusak_ringan', 0),
                'rusak_berat' => (int) $unitBuckets->get('rusak_berat', 0),
                'hilang' => (int) $unitBuckets->get('hilang', 0),
                'pengajuan_pinjam' => $loansInPeriod->count(),
                'disetujui' => $loansInPeriod->where('status', 'APPROVED')->count()
                    + $loansInPeriod->where('status', 'DIPINJAM')->count()
                    + $loansInPeriod->where('status', 'SELESAI')->count(),
                'selesai' => $loansInPeriod->where('status', 'SELESAI')->count(),
                'ditolak' => $loansInPeriod->where('status', 'DITOLAK')->count(),
                // Hanya pinjaman yang benar-benar disetujui/berjalan atau selesai yang bisa terlambat.
                'terlambat' => $loansInPeriod->filter(fn (Loan $loan) => match ($loan->status) {
                    LoanStatus::SELESAI => $loan->returned_at !== null && $loan->due_date->lt($loan->returned_at),
                    LoanStatus::APPROVED, LoanStatus::DIPINJAM => $loan->isOverdue(),
                    default => false,
                })->count(),
                'kerusakan' => $damageRows->count(),
                'mutasi' => $movements->count(),
                'penghapusan' => (int) $writeOffRows->sum('quantity'),
                'penghapusan_transaksi' => $writeOffs->count(),
                'pengadaan' => $acquisitionRows->count(),
                'pengadaan_tercatat' => $acquisitionRows->filter(fn (Item $item) => $item->isRecordedInInventory())->count(),
                'pengadaan_ditolak' => $acquisitionRows
                    ->where('acquisition_status', AcquisitionStatus::DITOLAK->value)->count(),
                'laporan_kerusakan' => $damageReportRows->count(),
                'laporan_kerusakan_ringan' => $damageReportRows
                    ->where('classification', DamageClassification::RUSAK_RINGAN)->count(),
                'laporan_kerusakan_berat' => $damageReportRows
                    ->where('classification', DamageClassification::RUSAK_BERAT)->count(),
            ],
            'damageRows' => $damageRows,
            'damageReportRows' => $damageReportRows,
            'acquisitionRows' => $acquisitionRows,
            'movements' => $movements,
            'writeOffs' => $writeOffs,
            'writeOffRows' => $writeOffRows,
        ];
    }

    /** Simpan file PDF & Excel laporan bulanan (dipakai scheduler + tombol manual). */
    public function generateMonthly(
        string $period,
        string $scope = 'SEMUA',
        ?int $scopeId = null,
        ?int $userId = null,
        ?User $user = null,
        ?string $condition = null,
    ): Report {
        $data = $this->data($period, $scope, $scopeId, $user, $condition);

        $pdfPath = 'reports/'.sprintf('laporan-%s-%s-%d.pdf', $period, strtolower($scope), $scopeId ?? 0);
        $xlsxPath = 'reports/'.sprintf('laporan-%s-%s-%d.xlsx', $period, strtolower($scope), $scopeId ?? 0);

        $pdf = Pdf::loadView('pdf.report', $data)->setPaper('a4', 'landscape');
        \Storage::disk('local')->put($pdfPath, $pdf->output());

        $excel = app(Excel::class);
        $excel->store(new MonthlyReportExport($data), $xlsxPath, 'local', Excel::XLSX);

        return Report::updateOrCreate(
            ['period' => $period, 'scope' => $scope, 'scope_id' => $scopeId],
            ['pdf_path' => $pdfPath, 'excel_path' => $xlsxPath, 'generated_by' => $userId, 'generated_at' => now()]
        );
    }

    /** Stream PDF laporan on-the-fly. */
    public function streamPdf(array $data, ?string $filename = null)
    {
        return Pdf::loadView('pdf.report', $data)
            ->setPaper('a4', 'landscape')
            ->stream($filename ?? 'laporan.pdf');
    }
}

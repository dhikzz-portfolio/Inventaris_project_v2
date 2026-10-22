<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\Category;
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
    public function data(string $period, string $scope = 'SEMUA', ?int $scopeId = null, ?User $user = null): array
    {
        $start = Carbon::parse($period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Ketua Laboran mengelola seluruh laboratorium, tetapi bukan inventaris umum
        // sekolah. Super Admin (dan pembuatan laporan oleh sistem) mencakup semua ruangan.
        $labOnly = $user !== null && ! $user->isSuperAdmin();

        $items = Item::approved()
            ->with(['room.major', 'category'])
            ->when($labOnly, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('is_lab', true)))
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
            ->with(['catalog.category', 'room'])
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
            ->when($labOnly, fn ($q) => $q->whereHas('loanItems.item.room', fn ($r) => $r->where('is_lab', true)));

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
            ->when($scope !== 'SEMUA' && $scopeId, fn ($q) => $q->whereHas('item', function ($qq) use ($scope, $scopeId) {
                $qq->when($scope === 'KATEGORI', fn ($q3) => $q3->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($q3) => $q3->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($q3) => $q3->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            }))
            ->with('item')
            ->get();

        $movements = ItemMovement::whereBetween('moved_at', [$start, $end])
            ->when($labOnly, fn ($q) => $q->whereHas('item.room', fn ($r) => $r->where('is_lab', true)))
            ->when($scope !== 'SEMUA' && $scopeId, fn ($q) => $q->whereHas('item', function ($qq) use ($scope, $scopeId) {
                $qq->when($scope === 'KATEGORI', fn ($q3) => $q3->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($q3) => $q3->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($q3) => $q3->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            }))
            ->with(['item', 'fromRoom', 'toRoom', 'mover'])
            ->get();

        $writeOffs = WriteOff::where('status', 'APPROVED')
            ->whereBetween('updated_at', [$start, $end])
            ->when($labOnly, fn ($q) => $q->whereHas('item.room', fn ($r) => $r->where('is_lab', true)))
            ->when($scope !== 'SEMUA' && $scopeId, fn ($q) => $q->whereHas('item', function ($qq) use ($scope, $scopeId) {
                $qq->when($scope === 'KATEGORI', fn ($q3) => $q3->where('category_id', $scopeId))
                    ->when($scope === 'RUANGAN', fn ($q3) => $q3->where('room_id', $scopeId))
                    ->when($scope === 'JURUSAN', fn ($q3) => $q3->whereHas('room', fn ($r) => $r->where('major_id', $scopeId)));
            }))
            ->with('item')
            ->get();

        return [
            'setting' => InstitutionSetting::current(),
            'period' => $period,
            'periodLabel' => $start->translatedFormat('F Y'),
            'scope' => $scope,
            'scopeLabel' => match ($scope) {
                'KATEGORI' => 'Kategori: '.(Category::find($scopeId)?->name ?? '-'),
                'RUANGAN' => 'Ruangan: '.(Room::find($scopeId)?->name ?? '-'),
                'JURUSAN' => 'Jurusan: '.(Major::find($scopeId)?->name ?? '-'),
                default => $labOnly ? 'Seluruh laboratorium' : 'Seluruh sekolah',
            },
            'items' => $items,
            'units' => $units,
            'loans' => $loansInPeriod->load([
                'borrower',
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
                'penghapusan' => $writeOffs->count(),
            ],
            'damageRows' => $damageRows,
            'movements' => $movements,
            'writeOffs' => $writeOffs,
        ];
    }

    /** Simpan file PDF & Excel laporan bulanan (dipakai scheduler + tombol manual). */
    public function generateMonthly(string $period, string $scope = 'SEMUA', ?int $scopeId = null, ?int $userId = null, ?User $user = null): Report
    {
        $data = $this->data($period, $scope, $scopeId, $user);

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

<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Tampilan baru hanya ditampilkan untuk role peminjam.
        // Role lain tetap mengikuti dashboard operasional yang sudah ada pada branch sebelumnya.
        if ($user->isPeminjam()) {
            return view('dashboard', $this->personalData($user));
        }

        return view('dashboard', $this->operationalData($user));
    }

    private function operationalData(User $user): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Statistik memakai unit fisik dan menghormati cakupan akses: Super Admin
        // melihat seluruh sekolah, Ketua Laboran seluruh lab, Laboran hanya ruangnya.
        $unitScope = AssetUnit::query()
            ->visibleTo($user)
            ->where('status', '!=', UnitStatus::DIHAPUS->value);

        $catalogCount = Item::approved()
            ->notArchived()
            ->visibleTo($user)
            ->where('status', '!=', ItemStatus::DIHAPUS->value)
            ->count();

        $scopedLoans = Loan::query()
            ->whereHas('loanItems.item', fn ($q) => $q->visibleTo($user));
        $activeLoans = (clone $scopedLoans)
            ->whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value]);

        // Barang paling sering dipinjam bulan ini (kuantitas).
        $topBorrowed = LoanItem::query()
            ->selectRaw('item_id, SUM(qty_borrowed) as total')
            ->whereHas('loan', fn ($q) => $q->whereBetween('created_at', [$startOfMonth, $endOfMonth]))
            ->whereHas('item', fn ($q) => $q->visibleTo($user))
            ->groupBy('item_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('item:id,name')
            ->get();

        // Barang paling sering rusak bulan ini.
        $topDamaged = LoanItem::query()
            ->selectRaw('item_id, COUNT(*) as total')
            ->whereNotNull('condition_in')
            ->where('condition_in', '!=', 'BAIK')
            ->whereHas('loan', fn ($q) => $q->whereNotNull('returned_at')->whereBetween('returned_at', [$startOfMonth, $endOfMonth]))
            ->whereHas('item', fn ($q) => $q->visibleTo($user))
            ->groupBy('item_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('item:id,name')
            ->get();

        // Grafik peminjaman 6 bulan terakhir (satu query, dikelompokkan di PHP).
        $loansPerMonth = $this->monthlyCounts(clone $scopedLoans);

        // Hitungan kondisi unit (dipisah agar portabel di MySQL maupun SQLite).
        $baik = (clone $unitScope)->where('status', UnitStatus::AKTIF->value)
            ->where('condition', Condition::BAIK->value)->count();
        $rusakRingan = (clone $unitScope)
            ->whereNotIn('status', [UnitStatus::HILANG->value, UnitStatus::DIPINJAM->value])
            ->where('condition', Condition::RUSAK_RINGAN->value)->count();
        $rusakBerat = (clone $unitScope)
            ->whereNotIn('status', [UnitStatus::HILANG->value, UnitStatus::DIPINJAM->value])
            ->where('condition', Condition::RUSAK_BERAT->value)->count();
        $hilang = (clone $unitScope)->where('status', UnitStatus::HILANG->value)->count();
        $dipinjam = (clone $unitScope)->where('status', UnitStatus::DIPINJAM->value)->count();
        $unitTotal = $baik + $rusakRingan + $rusakBerat + $hilang + $dipinjam;

        $statusDistribution = collect([
            ['label' => Condition::BAIK->label(), 'value' => $baik],
            ['label' => Condition::RUSAK_RINGAN->label(), 'value' => $rusakRingan],
            ['label' => Condition::RUSAK_BERAT->label(), 'value' => $rusakBerat],
            ['label' => UnitStatus::DIPINJAM->label(), 'value' => $dipinjam],
            ['label' => UnitStatus::HILANG->label(), 'value' => $hilang],
        ]);

        $overdue = $activeLoans->clone()
            ->where('due_date', '<', now())
            ->with(['borrower', 'loanItems.item'])
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $activeCount = (clone $scopedLoans)->whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value])->count();
        $pendingCount = (clone $scopedLoans)->where('status', LoanStatus::PENDING->value)->count();
        $overdueCount = (clone $activeLoans)->where('due_date', '<', now())->count();

        return [
            'mode' => 'operational',
            'scopeLabel' => match (true) {
                $user->isSuperAdmin() => 'seluruh sekolah',
                $user->isKetuaLaboran() => 'semua laboratorium',
                default => 'laboratorium Anda',
            },
            'stats' => [
                ['label' => 'Jenis Barang', 'value' => $catalogCount, 'unit' => 'jenis barang', 'icon' => 'category', 'color' => 'slate'],
                ['label' => 'Jumlah Barang', 'value' => $unitTotal, 'unit' => 'barang', 'icon' => 'inventory_2', 'color' => 'green'],
                ['label' => 'Sedang Dipinjam', 'value' => $activeCount, 'unit' => 'peminjaman', 'icon' => 'swap_horiz', 'color' => 'blue'],
                ['label' => 'Menunggu Jawaban', 'value' => $pendingCount, 'unit' => 'permintaan', 'icon' => 'how_to_reg', 'color' => 'amber'],
                ['label' => 'Terlambat Dikembalikan', 'value' => $overdueCount, 'unit' => 'peminjaman', 'icon' => 'alarm', 'color' => 'red'],
                ['label' => 'Rusak / Hilang', 'value' => $rusakRingan + $rusakBerat + $hilang, 'unit' => 'unit', 'icon' => 'report_problem', 'color' => 'purple'],
            ],
            'topBorrowed' => $topBorrowed,
            'topDamaged' => $topDamaged,
            'loansPerMonth' => $loansPerMonth,
            'statusDistribution' => $statusDistribution,
            'overdue' => $overdue,
            'recentActivity' => Activity::with(['causer', 'subject'])
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('causer_id', $user->id))
                ->latest()
                ->limit(8)
                ->get(),
        ];
    }

    /** Hitung peminjaman 6 bulan terakhir dengan satu query. */
    private function monthlyCounts($query): Collection
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonthsNoOverflow($i));

        $counts = (clone $query)
            ->where('created_at', '>=', $months->first()->copy()->startOfMonth())
            ->pluck('created_at')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        return $months->map(fn ($month) => [
            'label' => $month->translatedFormat('M y'),
            'count' => (int) ($counts[$month->format('Y-m')] ?? 0),
        ])->values();
    }

    private function personalData($user): array
    {
        $mine = Loan::where('borrower_id', $user->id);

        $loansPerMonth = $this->monthlyCounts($mine);

        return [
            'mode' => 'personal',
            'stats' => [
                ['label' => 'Sedang Dipinjam', 'value' => (clone $mine)->whereIn('status', ['APPROVED', 'DIPINJAM'])->count(), 'unit' => 'peminjaman', 'icon' => 'swap_horiz', 'color' => 'blue'],
                ['label' => 'Menunggu Jawaban', 'value' => (clone $mine)->where('status', 'PENDING')->count(), 'unit' => 'permintaan', 'icon' => 'hourglass_top', 'color' => 'amber'],
                ['label' => 'Terlambat Dikembalikan', 'value' => (clone $mine)->whereIn('status', ['APPROVED', 'DIPINJAM'])->where('due_date', '<', now())->count(), 'unit' => 'peminjaman', 'icon' => 'alarm', 'color' => 'red'],
                ['label' => 'Sudah Dikembalikan', 'value' => (clone $mine)->where('status', 'SELESAI')->count(), 'unit' => 'peminjaman', 'icon' => 'task_alt', 'color' => 'green'],
            ],
            'myActive' => (clone $mine)->whereIn('status', ['PENDING', 'APPROVED', 'DIPINJAM'])
                ->with(['loanItems.item', 'event'])
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'loansPerMonth' => $loansPerMonth,
            'recentActivity' => Activity::where('causer_id', $user->id)->latest()->limit(6)->get(),
            'catalogCount' => Item::approved()->notArchived()->where('status', '!=', ItemStatus::DIHAPUS->value)->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
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

        if ($user->canApproveLoans()) {
            return view('dashboard', $this->operationalData($user));
        }

        return view('dashboard', $this->personalData($user));
    }

    private function operationalData($user): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $activeLoans = Loan::whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value]);

        $itemsQuery = Item::approved()->where('status', '!=', ItemStatus::DIHAPUS->value);

        // Barang paling sering dipinjam bulan ini (kuantitas).
        $topBorrowed = LoanItem::query()
            ->selectRaw('item_id, SUM(qty_borrowed) as total')
            ->whereHas('loan', fn ($q) => $q->whereBetween('created_at', [$startOfMonth, $endOfMonth]))
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
            ->groupBy('item_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('item:id,name')
            ->get();

        // Grafik peminjaman 6 bulan terakhir (satu query, dikelompokkan di PHP).
        $loansPerMonth = $this->monthlyCounts(Loan::query());

        // Satu query agregat untuk seluruh status, dipakai kartu statistik & grafik distribusi.
        $statusCounts = $itemsQuery->clone()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusDistribution = collect(ItemStatus::cases())
            ->reject(fn ($s) => $s === ItemStatus::DIHAPUS)
            ->map(fn ($s) => [
                'label' => $s->label(),
                'value' => (int) ($statusCounts[$s->value] ?? 0),
            ])->values();

        $overdue = $activeLoans->clone()
            ->where('due_date', '<', now())
            ->with(['borrower', 'loanItems.item'])
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $lowStock = $itemsQuery->clone()
            ->whereNotNull('min_stock')
            ->whereColumn('quantity', '<=', 'min_stock')
            ->with('room')
            ->limit(6)
            ->get();

        $pendingScope = Loan::where('status', LoanStatus::PENDING->value);
        if ($user->isLaboran() && $user->major_id) {
            $pendingScope->whereHas('loanItems.item.room', fn ($q) => $q->where('major_id', $user->major_id));
        }

        $pendingCount = $pendingScope->count();
        $activeCount = $activeLoans->clone()->count();
        $overdueCount = $activeLoans->clone()->where('due_date', '<', now())->count();
        $lowStockCount = $itemsQuery->clone()->whereNotNull('min_stock')->whereColumn('quantity', '<=', 'min_stock')->count();
        $damagedCount = (int) $statusCounts->only(['RUSAK_RINGAN', 'RUSAK_BERAT', 'HILANG'])->sum();

        return [
            'mode' => 'operational',
            'stats' => [
                ['label' => 'Barang Terdaftar', 'value' => (int) $statusCounts->sum(), 'unit' => 'item', 'icon' => 'inventory_2', 'color' => 'slate'],
                ['label' => 'Sedang Dipinjam', 'value' => $activeCount, 'unit' => 'pinjaman', 'icon' => 'swap_horiz', 'color' => 'blue'],
                ['label' => 'Menunggu Persetujuan', 'value' => $pendingCount, 'unit' => 'request', 'icon' => 'how_to_reg', 'color' => 'amber'],
                ['label' => 'Terlambat', 'value' => $overdueCount, 'unit' => 'pinjaman', 'icon' => 'alarm', 'color' => 'red'],
                ['label' => 'Stok Menipis', 'value' => $lowStockCount, 'unit' => 'barang', 'icon' => 'low_priority', 'color' => 'orange'],
                ['label' => 'Rusak / Hilang', 'value' => $damagedCount, 'unit' => 'barang', 'icon' => 'report_problem', 'color' => 'purple'],
            ],
            'topBorrowed' => $topBorrowed,
            'topDamaged' => $topDamaged,
            'loansPerMonth' => $loansPerMonth,
            'statusDistribution' => $statusDistribution,
            'overdue' => $overdue,
            'lowStock' => $lowStock,
            'recentActivity' => Activity::with(['causer', 'subject'])
                ->latest()
                ->limit(8)
                ->get(),
            'pendingScope' => $pendingCount,
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
                ['label' => 'Pinjaman Aktif', 'value' => (clone $mine)->whereIn('status', ['APPROVED', 'DIPINJAM'])->count(), 'unit' => 'pinjaman', 'icon' => 'swap_horiz', 'color' => 'blue'],
                ['label' => 'Menunggu Persetujuan', 'value' => (clone $mine)->where('status', 'PENDING')->count(), 'unit' => 'request', 'icon' => 'hourglass_top', 'color' => 'amber'],
                ['label' => 'Terlambat', 'value' => (clone $mine)->whereIn('status', ['APPROVED', 'DIPINJAM'])->where('due_date', '<', now())->count(), 'unit' => 'pinjaman', 'icon' => 'alarm', 'color' => 'red'],
                ['label' => 'Selesai', 'value' => (clone $mine)->where('status', 'SELESAI')->count(), 'unit' => 'pinjaman', 'icon' => 'task_alt', 'color' => 'green'],
            ],
            'myActive' => (clone $mine)->whereIn('status', ['PENDING', 'APPROVED', 'DIPINJAM'])
                ->with(['loanItems.item', 'event'])
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'loansPerMonth' => $loansPerMonth,
            'recentActivity' => Activity::where('causer_id', $user->id)->latest()->limit(6)->get(),
            'catalogCount' => Item::approved()->where('status', '!=', ItemStatus::DIHAPUS->value)->count(),
        ];
    }
}

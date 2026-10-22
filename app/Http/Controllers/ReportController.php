<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyReportExport;
use App\Models\Category;
use App\Models\Major;
use App\Models\Report;
use App\Models\Room;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $reports = Report::with('generator')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('generated_by', $user->id))
            ->latest('generated_at')
            ->paginate(10);

        return view('reports.index', [
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::query()
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('is_lab', true))
                ->orderBy('code')
                ->get(),
            'majors' => Major::orderBy('name')->get(),
            'reports' => $reports,
            'currentPeriod' => now()->format('Y-m'),
            'lastPeriod' => now()->subMonth()->format('Y-m'),
        ]);
    }

    /** Ekspor on-the-fly: /laporan/export/{pdf|excel}/{period}?scope=&scope_id= */
    public function export(Request $request, ReportService $service, string $type, string $period)
    {
        abort_unless(in_array($type, ['pdf', 'excel'], true), 404);

        $scope = $request->query('scope', 'SEMUA');
        $scopeId = $request->query('scope_id') ? (int) $request->query('scope_id') : null;
        abort_unless(in_array($scope, ['SEMUA', 'KATEGORI', 'RUANGAN', 'JURUSAN'], true), 404);

        $data = $service->data($period, $scope, $scopeId, $request->user());
        $suffix = $period.'-'.strtolower($scope).($scopeId ? '-'.$scopeId : '');

        if ($type === 'pdf') {
            activity('laporan')->log("Ekspor laporan PDF periode {$period} ({$scope})");

            return $service->streamPdf($data, 'laporan-'.$suffix.'.pdf');
        }

        activity('laporan')->log("Ekspor laporan Excel periode {$period} ({$scope})");

        return Excel::download(
            new MonthlyReportExport($data),
            'laporan-'.$suffix.'.xlsx'
        );
    }

    /** Simpan snapshot laporan bulanan. */
    public function generate(Request $request, ReportService $service): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'scope' => ['required', 'in:SEMUA,KATEGORI,RUANGAN,JURUSAN'],
            'scope_id' => ['nullable', 'integer'],
        ]);

        $report = $service->generateMonthly(
            $data['period'],
            $data['scope'],
            ! empty($data['scope_id']) ? (int) $data['scope_id'] : null,
            $request->user()->id,
            $request->user()
        );

        return back()->with('success', "Laporan {$data['period']} tersimpan (PDF & Excel).");
    }
}

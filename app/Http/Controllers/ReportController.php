<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyReportExport;
use App\Models\Category;
use App\Models\Major;
use App\Models\Report;
use App\Models\Room;
use App\Services\ReportImportService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** Form impor laporan (pemulihan/migrasi). */
    public function importForm(Request $request): View
    {
        $preview = $request->session()->get('report_import_preview');

        return view('reports.import', [
            'preview' => $preview['analysis'] ?? null,
            'token' => $preview['token'] ?? null,
            'filename' => $preview['filename'] ?? null,
        ]);
    }

    /** Pratinjau berkas sebelum perubahan database. */
    public function importPreview(Request $request, ReportImportService $imports): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
            'mode' => ['required', 'in:create,update'],
        ], [
            'file.mimes' => 'Format berkas harus CSV atau XLSX (maksimal 2 MB).',
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
        ]);

        $rows = $imports->parse($request->file('file'));

        if ($rows === []) {
            return back()->withErrors(['file' => 'Berkas tidak memuat baris data yang dapat dibaca.'])->withInput();
        }

        $analysis = $imports->analyze($rows, $data['mode']);
        $token = (string) Str::uuid();

        $request->session()->put('report_import_preview', [
            'token' => $token,
            'filename' => $request->file('file')->getClientOriginalName(),
            'analysis' => $analysis,
        ]);

        activity('laporan')
            ->causedBy($request->user())
            ->withProperties([
                'file' => $request->file('file')->getClientOriginalName(),
                'mode' => $data['mode'],
                'total' => $analysis['summary']['total'],
                'valid' => $analysis['summary']['valid'],
                'invalid' => $analysis['summary']['invalid'],
            ])
            ->log('Pratinjau impor laporan unit');

        return redirect()->route('reports.import.form')
            ->with('success', 'Pratinjau siap. Periksa baris valid dan bermasalah sebelum mengimpor.');
    }

    /** Konfirmasi impor — atomik dan hanya dari pratinjau yang sah. */
    public function importConfirm(Request $request, ReportImportService $imports): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $preview = $request->session()->get('report_import_preview');

        if (! is_array($preview) || ($preview['token'] ?? null) !== $data['token']) {
            return redirect()->route('reports.import.form')
                ->withErrors(['file' => 'Sesi pratinjau tidak ditemukan/kedaluwarsa. Unggah ulang berkas.']);
        }

        $analysis = $preview['analysis'];

        if (! ($analysis['can_import'] ?? false)) {
            return redirect()->route('reports.import.form')
                ->withErrors(['file' => 'Impor diblokir: masih ada baris bermasalah. Perbaiki berkas lalu ulangi pratinjau.']);
        }

        $result = $imports->apply($analysis, $request->user(), $preview['filename']);

        $request->session()->forget('report_import_preview');

        return redirect()->route('reports.index')
            ->with('success', "Impor selesai: {$result['created']} unit dibuat, {$result['updated']} unit diperbarui. Data inventaris tidak dihapus.");
    }

    /**
     * Hapus arsip laporan (soft delete + hapus file). Data inventaris sumber,
     * transaksi, dan unit tidak disentuh.
     */
    public function destroy(Request $request, Report $report): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperAdmin() || (int) $report->generated_by === (int) $user->id, 403);

        $period = $report->period;
        $scope = $report->scope;

        foreach ([$report->pdf_path, $report->excel_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        activity('laporan')
            ->performedOn($report)
            ->causedBy($user)
            ->withProperties([
                'period' => $period,
                'scope' => $scope,
                'scope_id' => $report->scope_id,
                'pdf_path' => $report->pdf_path,
                'excel_path' => $report->excel_path,
            ])
            ->log("Menghapus arsip laporan {$period} ({$scope})");

        $report->delete();

        return back()->with('success', "Arsip laporan periode {$period} dihapus. Data inventaris sumber tidak berubah.");
    }
}

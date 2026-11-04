<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
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
            ->when(! $user->isSuperAdmin() && ! $user->isAdmin(), fn ($q) => $q->where('generated_by', $user->id))
            ->latest('generated_at')
            ->paginate(10);

        return view('reports.index', [
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::query()
                ->when(! $user->isSuperAdmin() && ! $user->isAdmin(), fn ($q) => $q->where('is_lab', true))
                ->when($user->isOperator(), fn ($q) => $q->whereIn('id', $user->roomScopes()->select('room_id')))
                ->orderBy('code')
                ->get(),
            'majors' => Major::orderBy('name')->get(),
            'conditions' => Condition::cases(),
            'reports' => $reports,
            'currentPeriod' => now()->format('Y-m'),
            'lastPeriod' => now()->subMonth()->format('Y-m'),
        ]);
    }

    /** Ekspor on-the-fly: /laporan/export/{pdf|excel}/{period}?scope=&scope_id= */
    public function export(Request $request, ReportService $service, string $type, string $period)
    {
        abort_unless(in_array($type, ['pdf', 'excel'], true), 404);

        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1, 404);

        $scope = $request->query('scope', 'SEMUA');
        $scopeId = $request->query('scope_id') ? (int) $request->query('scope_id') : null;
        abort_unless(in_array($scope, ['SEMUA', 'KATEGORI', 'RUANGAN', 'JURUSAN'], true), 404);

        $condition = $request->query('condition');
        $condition = is_string($condition) && Condition::tryFrom($condition) !== null ? $condition : null;

        $data = $service->data($period, $scope, $scopeId, $request->user(), $condition);
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

    /**
     * Pratinjau Excel memakai definisi tabel yang sama dengan file ekspor.
     * Tidak ada query atau transformasi terpisah agar isi pratinjau identik
     * dengan hasil unduhan.
     */
    public function previewExcel(Request $request, ReportService $service, string $period): View
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $period) === 1, 404);

        $scope = (string) $request->query('scope', 'SEMUA');
        abort_unless(in_array($scope, ['SEMUA', 'KATEGORI', 'RUANGAN', 'JURUSAN'], true), 404);

        $scopeId = $request->query('scope_id') ? (int) $request->query('scope_id') : null;
        $condition = $request->query('condition');
        $condition = is_string($condition) && Condition::tryFrom($condition) !== null ? $condition : null;

        $data = $service->data($period, $scope, $scopeId, $request->user(), $condition);
        $tables = MonthlyReportExport::tables($data);

        $sheetIndex = max(0, min((int) $request->query('sheet', 0), count($tables) - 1));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $allRows = $tables[$sheetIndex]['rows'];

        return view('reports.preview-excel', [
            'period' => $period,
            'periodLabel' => $data['periodLabel'],
            'scope' => $scope,
            'scopeId' => $scopeId,
            'scopeLabel' => $data['scopeLabel'],
            'condition' => $condition,
            'tables' => $tables,
            'sheetIndex' => $sheetIndex,
            'rows' => array_slice($allRows, ($page - 1) * $perPage, $perPage),
            'totalRows' => count($allRows),
            'page' => $page,
            'perPage' => $perPage,
            'summary' => $data['summary'],
        ]);
    }

    /**
     * Contoh file impor sesuai kolom yang benar-benar dibaca
     * ReportImportService. Dua baris contoh wajib diganti pengguna.
     */
    public function importTemplate()
    {
        $headings = [
            'kode_inventaris', 'nama_barang', 'kategori', 'gedung', 'lantai', 'no_ruangan',
            'kondisi', 'status', 'tahun', 'sumber_dana', 'no_register',
        ];

        $examples = [
            ['G1-01-01', 'Komputer Lab (contoh)', 'Elektronik', 'G', '1', '1', 'Baik', 'Aktif', '2024', 'BOS', 'REG-001'],
            ['G1-01-02', 'Komputer Lab (contoh)', 'Elektronik', 'G', '1', '1', 'Rusak Ringan', 'Aktif', '2024', 'BOS', 'REG-002'],
        ];

        return response()->streamDownload(function () use ($headings, $examples): void {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);

            foreach ($examples as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'contoh-impor-unit.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Simpan snapshot laporan bulanan. */
    public function generate(Request $request, ReportService $service): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'scope' => ['required', 'in:SEMUA,KATEGORI,RUANGAN,JURUSAN'],
            'scope_id' => ['nullable', 'integer'],
            'condition' => ['nullable', 'in:'.implode(',', array_column(Condition::cases(), 'value'))],
        ]);

        $report = $service->generateMonthly(
            $data['period'],
            $data['scope'],
            ! empty($data['scope_id']) ? (int) $data['scope_id'] : null,
            $request->user()->id,
            $request->user(),
            $data['condition'] ?? null,
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

        abort_unless($user->isAdmin() || (int) $report->generated_by === (int) $user->id, 403);

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

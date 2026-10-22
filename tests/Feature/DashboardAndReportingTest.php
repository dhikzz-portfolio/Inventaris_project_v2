<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Jobs\CheckOverdueLoans;
use App\Jobs\GenerateMonthlyReport;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardAndReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->guru = User::where('username', 'guru.budi')->firstOrFail();
    }

    public function test_performa_dashboard_kurang_dari_satu_detik(): void
    {
        $this->actingAs($this->wakasek);

        $start = microtime(true);
        $response = $this->get(route('dashboard'));
        $duration = microtime(true) - $start;

        $response->assertOk();
        $response->assertViewHas('mode', 'operational');
        $this->assertLessThan(1.0, $duration, "Waktu respon dashboard ({$duration}s) melebihi batas 1 detik");
    }

    public function test_dashboard_peminjam_menampilkan_data_personal(): void
    {
        $this->actingAs($this->guru);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertViewHas('mode', 'personal');
        $response->assertSee(route('catalogs.index'), false);
    }

    public function test_dashboard_pengelola_menghitung_unit_sesuai_cakupan_akses(): void
    {
        $wakasek = User::where('username', 'wakasek')->firstOrFail();
        $admin = User::where('username', 'admin.sarpras')->firstOrFail();
        $laboran = User::where('username', 'laboran.tkj')->firstOrFail();

        $unitStat = function ($response): int {
            return (int) collect($response->viewData('stats'))->firstWhere('label', 'Unit Inventaris')['value'];
        };

        $wakasekResponse = $this->actingAs($wakasek)->get(route('dashboard'))->assertOk();
        $adminResponse = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $laboranResponse = $this->actingAs($laboran)->get(route('dashboard'))->assertOk();

        $visible = fn (User $user) => AssetUnit::query()->visibleTo($user)->where('status', '!=', 'DIHAPUS')->count();

        $this->assertSame($visible($wakasek), $unitStat($wakasekResponse));
        $this->assertSame($visible($admin), $unitStat($adminResponse));
        $this->assertSame($visible($laboran), $unitStat($laboranResponse));
        $this->assertLessThan($unitStat($wakasekResponse), $unitStat($laboranResponse));

        $wakasekResponse->assertSee('seluruh sekolah');
        $adminResponse->assertSee('seluruh laboratorium');
        $laboranResponse->assertSee('laboratorium dalam cakupan Anda');
    }

    public function test_menu_laboran_tidak_menautkan_mutasi_yang_bukan_kewenangannya(): void
    {
        $laboran = User::where('username', 'laboran.tkj')->firstOrFail();

        $this->actingAs($laboran)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('movements.index'), false)
            ->assertSee(route('labels.index'), false);
    }

    public function test_job_check_overdue_loans_mengirim_notifikasi(): void
    {
        // Buat pinjaman yang sudah lewat tenggat (due_date kemarin)
        $normalItem = Item::where('is_high_value', false)->firstOrFail();
        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $normalItem->id, 'qty' => 1]],
            'Pinjaman lewat batas',
            now()->subDays(5)->format('Y-m-d H:i'),
            now()->subDay()->format('Y-m-d H:i')
        );
        $loan->update(['status' => 'DIPINJAM']);

        // Jalankan Job CheckOverdueLoans
        (new CheckOverdueLoans)->handle();

        // Verifikasi notifikasi terkirim ke peminjam
        $this->assertDatabaseHas('notifications_app', [
            'user_id' => $this->guru->id,
            'type' => NotificationType::TERLAMBAT->value,
        ]);
    }

    public function test_job_generate_monthly_report_membuat_pdf_dan_excel(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $period = now()->format('Y-m');

        (new GenerateMonthlyReport($period))->handle();

        $report = Report::where('period', $period)->where('scope', 'SEMUA')->firstOrFail();
        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->excel_path);

        Storage::disk('local')->assertExists($report->pdf_path);
        Storage::disk('local')->assertExists($report->excel_path);
    }
}

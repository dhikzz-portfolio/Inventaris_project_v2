<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Jobs\CheckLowStock;
use App\Jobs\CheckOverdueLoans;
use App\Jobs\GenerateMonthlyReport;
use App\Models\Category;
use App\Models\Item;
use App\Models\Report;
use App\Models\Room;
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

    private Item $consumableItem;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->guru = User::where('username', 'guru.budi')->firstOrFail();
        $this->room = Room::firstOrFail();

        $consumableCat = Category::where('is_consumable', true)->firstOrFail();

        $this->consumableItem = Item::create([
            'internal_id' => 'KRT-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.03.02.01.01',
            'register_no' => '4401',
            'name' => 'Kertas Foto Glossy',
            'category_id' => $consumableCat->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2026,
            'funding_source' => 'BOS',
            'quantity' => 2, // Di bawah min_stock
            'unit' => 'pak',
            'min_stock' => 5,
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);
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

    public function test_job_check_low_stock_mengirim_notifikasi_ke_pengelola(): void
    {
        // Jalankan Job CheckLowStock
        (new CheckLowStock)->handle();

        // Verifikasi notifikasi terkirim ke Wakasek/Admin
        $this->assertDatabaseHas('notifications_app', [
            'user_id' => $this->wakasek->id,
            'type' => NotificationType::STOK_MENIPIS->value,
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

<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Services\BastService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventAndBastTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private Item $item;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin.sarpras')->firstOrFail();
        $this->guru = User::where('username', 'guru.budi')->firstOrFail();

        $category = Category::where('is_consumable', false)->firstOrFail();
        $room = Room::firstOrFail();

        $this->item = Item::create([
            'internal_id' => 'EVT-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '5501',
            'name' => 'Kamera Liputan Event',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);

        $this->event = Event::create([
            'name' => 'Lomba Kompetensi Siswa (LKS) Tingkat Provinsi',
            'pic_name' => 'Drs. Supriyanto, M.Pd',
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(8),
            'description' => 'Ajang kompetisi kejuruan SMK',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_buat_kegiatan_dan_tampilkan_detail(): void
    {
        $response = $this->actingAs($this->admin)->post(route('events.store'), [
            'name' => 'Workshop IoT dan AI',
            'pic_name' => 'Bambang Sutrisno, M.Kom',
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'description' => 'Pelatihan guru produktif TKJ & RPL',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('events', ['name' => 'Workshop IoT dan AI', 'pic_name' => 'Bambang Sutrisno, M.Kom']);

        $event = Event::where('name', 'Workshop IoT dan AI')->firstOrFail();
        $this->actingAs($this->admin)->get(route('events.show', $event))->assertOk();
    }

    public function test_generate_bast_peminjaman_menghasilkan_pdf_dan_path(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Dokumentasi LKS',
            now()->addDays(5)->format('Y-m-d H:i'),
            now()->addDays(8)->format('Y-m-d H:i'),
            $this->event->id
        );

        $path = app(BastService::class)->generateForLoan($loan);

        $this->assertNotNull($path);
        $this->assertSame($path, $loan->refresh()->bast_path);
        Storage::disk('local')->assertExists($path);

        // Unduh BAST oleh peminjam
        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_peminjaman_rutin_memakai_nomor_sp_dan_judul_surat_peminjaman(): void
    {
        Storage::fake('local');

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Keperluan praktikum kelas',
            now()->addDays(2)->format('Y-m-d H:i'),
            now()->addDays(3)->format('Y-m-d H:i')
        );

        $service = app(BastService::class);

        $this->assertSame('SP/'.$loan->id.'/'.now()->format('m/Y'), $service->documentNumber($loan));
        $this->assertSame('SURAT PEMINJAMAN BARANG', $service->documentTitle($loan));

        $html = view('pdf.bast', [
            'loan' => $loan->load(['loanItems.item.category', 'borrower', 'event', 'approvals.approver']),
            'setting' => InstitutionSetting::current(),
            'title' => $service->documentTitle($loan),
            'documentNumber' => $service->documentNumber($loan),
        ])->render();

        $this->assertStringContainsString('Nomor: SP/'.$loan->id, $html);
        $this->assertStringContainsString('Demikian Surat Peminjaman barang ini', $html);
        $this->assertStringNotContainsString('BAST/', $html);

        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertDownload('Surat-Peminjaman-'.$loan->id.'.pdf');
    }

    public function test_peminjaman_kegiatan_memakai_nomor_bast(): void
    {
        Storage::fake('local');

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Dokumentasi LKS',
            now()->addDays(5)->format('Y-m-d H:i'),
            now()->addDays(8)->format('Y-m-d H:i'),
            $this->event->id
        );

        $service = app(BastService::class);

        $this->assertSame('BAST/'.$loan->id.'/'.now()->format('m/Y'), $service->documentNumber($loan));
        $this->assertSame('BERITA ACARA SERAH TERIMA (BAST)', $service->documentTitle($loan));

        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertDownload('BAST-'.$loan->id.'.pdf');
    }

    public function test_loan_dibatalkan_tidak_ikut_bast_gabungan_event(): void
    {
        Storage::fake('local');

        $active = $this->buatLoanEvent('Dokumentasi utama');

        $cancelled = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Dokumentasi cadangan',
            now()->addDays(10)->format('Y-m-d H:i'),
            now()->addDays(11)->format('Y-m-d H:i'),
            $this->event->id
        );
        $cancelled->update([
            'status' => LoanStatus::DIBATALKAN,
            'bast_path' => 'bast/BAST-PINJAM'.$cancelled->id.'.pdf',
        ]);

        $service = app(BastService::class);
        $loans = $service->loansForEvent($this->event);

        $this->assertSame([$active->id], $loans->pluck('id')->all());
        $this->assertNotNull($service->generateForEvent($this->event));
    }

    public function test_generate_bast_gabungan_event(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Dokumentasi Utama LKS',
            now()->addDays(5)->format('Y-m-d H:i'),
            now()->addDays(8)->format('Y-m-d H:i'),
            $this->event->id
        );
        $loan->update(['status' => 'APPROVED']);

        $path = app(BastService::class)->generateForEvent($this->event);

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->admin)
            ->get(route('events.bast', $this->event))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** Loan kegiatan yang sudah disetujui (lolos filter BAST gabungan). */
    private function buatLoanEvent(string $purpose): Loan
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            $purpose,
            now()->addDays(6)->format('Y-m-d H:i'),
            now()->addDays(7)->format('Y-m-d H:i'),
            $this->event->id
        );
        $loan->update(['status' => LoanStatus::APPROVED]);

        return $loan;
    }
}

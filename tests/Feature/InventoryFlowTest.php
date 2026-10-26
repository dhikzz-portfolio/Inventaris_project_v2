<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Exceptions\AvailabilityException;
use App\Livewire\Catalog\ItemCatalog;
use App\Livewire\Loan\LoanRequest;
use App\Livewire\Loan\ReturnForm;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $laboran;

    private User $wakasek;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->guru = User::where('username', 'guru.budi')->firstOrFail();
        $this->laboran = User::where('username', 'laboran.tp')->firstOrFail();
        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();

        $this->item = Item::create([
            'internal_id' => 'TEST-001',
            'location_code' => 'TEST-LOK',
            'register_no' => 'REG-001',
            'name' => 'Proyektor Uji Coba',
            'category_id' => Category::firstOrFail()->id,
            'room_id' => Room::firstOrFail()->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'is_high_value' => true,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_login_memakai_username(): void
    {
        $this->post('/login', ['username' => 'guru.budi', 'password' => 'guru123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->guru);
    }

    public function test_hak_akses_master_hanya_untuk_pengelola(): void
    {
        $this->actingAs($this->laboran)->get('/master')->assertForbidden();
        $this->actingAs($this->wakasek)->get('/master')->assertOk();
    }

    public function test_request_peminjaman_multi_item_dan_anti_bentrok(): void
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );

        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertTrue($loan->needs_high_value_approval);
        $this->assertCount(1, $loan->loanItems);

        $this->expectException(AvailabilityException::class);

        $service->requestLoan(
            User::where('username', 'guru.sari')->firstOrFail(),
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat lain',
            '2026-11-02',
            '2026-11-04'
        );
    }

    public function test_tanggal_tidak_bentrok_tetap_boleh_diajukan(): void
    {
        $service = app(LoanService::class);

        $service->requestLoan($this->guru, [['item_id' => $this->item->id, 'qty' => 1]], 'Rapat A', '2026-11-01', '2026-11-03');

        $lanjut = User::where('username', 'guru.sari')->firstOrFail();
        $loan = $service->requestLoan($lanjut, [['item_id' => $this->item->id, 'qty' => 1]], 'Rapat B', '2026-11-05', '2026-11-07');

        $this->assertSame(LoanStatus::PENDING, $loan->status);
    }

    public function test_approval_berjenjang_level_1_lalu_wakasek(): void
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );

        $service->decide($loan, $this->laboran, Decision::APPROVED);
        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);

        $service->decide($loan, $this->wakasek, Decision::APPROVED, 'Disetujui wakasek');
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertSame(2, $loan->approvals()->count());
    }

    public function test_pengembalian_dengan_selisih_menandai_barang_hilang(): void
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);

        $loanItem = $loan->loanItems()->firstOrFail();

        $service->processReturn($loan, $this->laboran, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 0,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $this->item->room_id,
        ]]);

        $this->assertSame(ItemStatus::HILANG, $this->item->refresh()->status);
        $this->assertNotSame(LoanStatus::SELESAI, $loan->refresh()->status);
    }

    public function test_kerusakan_wajib_foto_bukti(): void
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);

        $loanItem = $loan->loanItems()->firstOrFail();

        $this->expectException(ValidationException::class);

        $service->processReturn($loan, $this->laboran, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $this->item->room_id,
        ]]);
    }

    public function test_form_livewire_peminjaman_membuat_request(): void
    {
        $this->actingAs($this->guru);

        $component = Livewire::test(LoanRequest::class)
            ->set('search', 'Proyektor')
            ->call('add', $this->item->id)
            ->assertSet('cart.0.item_id', $this->item->id)
            ->set('purpose', 'Rapat komite')
            ->set('start_date', '2026-11-01T07:00')
            ->set('due_date', '2026-11-03T15:00')
            ->call('submit')
            ->assertHasNoErrors();

        $loan = Loan::latest('id')->firstOrFail();

        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertSame($this->guru->id, $loan->borrower_id);
        $component->assertRedirect(route('loans.show', $loan));
    }

    public function test_form_livewire_pengembalian_menyelesaikan_peminjaman(): void
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan->fresh()])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('loans.show', $loan));

        $this->assertSame(LoanStatus::SELESAI, $loan->refresh()->status);
        $this->assertSame(ItemStatus::BAIK, $this->item->refresh()->status);
    }

    public function test_katalog_livewire_bisa_dicari_dan_difilter(): void
    {
        $this->actingAs($this->guru);

        Livewire::test(ItemCatalog::class)
            ->set('search', 'Proyektor Uji')
            ->assertSee('Proyektor Uji Coba')
            ->set('search', 'barang-tidak-ada')
            ->assertDontSee('Proyektor Uji Coba')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->set('search', 'Proyektor Uji')
            ->set('status', ItemStatus::BAIK->value)
            ->assertSee('Proyektor Uji Coba');
    }

    public function test_label_barang_dicetak_sebagai_pdf(): void
    {
        $this->actingAs($this->laboran)
            ->get(route('items.label', $this->item))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_laporan_pdf_dan_excel_dapat_diunduh(): void
    {
        $this->actingAs($this->wakasek)
            ->get(route('reports.export', ['type' => 'pdf', 'period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->wakasek)
            ->get(route('reports.export', ['type' => 'excel', 'period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_bast_dapat_dicetak_untuk_peminjaman_yang_masih_pending(): void
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );

        $this->assertSame(LoanStatus::PENDING, $loan->status);

        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bast_di_generate_ulang_setiap_diunduh(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Rapat komite',
            '2026-11-01',
            '2026-11-03'
        );

        $loan->forceFill(['bast_path' => 'bast/lama.pdf'])->save();
        Storage::disk('local')->put('bast/lama.pdf', 'lama');

        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotSame('bast/lama.pdf', $loan->refresh()->bast_path);
        Storage::disk('local')->assertMissing('bast/lama.pdf');
        Storage::disk('local')->assertExists($loan->bast_path);
    }

    public function test_wakasek_dapat_membuat_pengguna_dengan_username_bertitik(): void
    {
        $this->actingAs($this->wakasek)
            ->post(route('users.store'), [
                'name' => 'Guru Baru',
                'username' => 'guru.baru',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => 'PEMINJAM',
                'major_id' => $this->guru->major_id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'guru.baru', 'role' => 'PEMINJAM']);
    }

    public function test_generate_laporan_bulanan_tanpa_scope_id(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->wakasek)
            ->post(route('reports.generate'), [
                'period' => now()->format('Y-m'),
                'scope' => 'SEMUA',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report = Report::where('period', now()->format('Y-m'))->where('scope', 'SEMUA')->firstOrFail();

        Storage::disk('local')->assertExists($report->pdf_path);
        Storage::disk('local')->assertExists($report->excel_path);
    }
}

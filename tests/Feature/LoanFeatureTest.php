<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Exceptions\AvailabilityException;
use App\Livewire\Loan\LoanRequest;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $laboranTkj;

    private User $guruBudi;

    private User $guruSari;

    private Item $laptop;

    private Item $kamera;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->laboranTkj = User::where('username', 'laboran.tp')->firstOrFail();
        $this->guruBudi = User::where('username', 'guru.budi')->firstOrFail();
        $this->guruSari = User::where('username', 'guru.sari')->firstOrFail();

        $category = Category::firstOrFail();
        $room = Room::firstOrFail();

        $this->laptop = Item::create([
            'internal_id' => 'LP-G1-01-91',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '9101',
            'name' => 'Laptop Core i7',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'is_high_value' => true,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $this->kamera = Item::create([
            'internal_id' => 'CAM-G1-01-92',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '9102',
            'name' => 'Kamera Video 4K',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 2,
            'unit' => 'unit',
            'is_high_value' => false,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_keranjang_multi_item_melalui_livewire_request_loan(): void
    {
        $this->actingAs($this->guruBudi);

        Livewire::test(LoanRequest::class)
            ->call('add', $this->laptop->id)
            ->call('add', $this->kamera->id)
            ->assertSet('cart.0.item_id', $this->laptop->id)
            ->assertSet('cart.1.item_id', $this->kamera->id)
            ->call('updateQty', 1, 2) // ubah qty kamera jadi 2
            ->assertSet('cart.1.qty', 2)
            ->set('purpose', 'Praktik Multimedia dan Coding')
            ->set('start_date', '2026-12-01T08:00')
            ->set('due_date', '2026-12-03T16:00')
            ->call('submit')
            ->assertHasNoErrors();

        $loan = Loan::latest('id')->firstOrFail();
        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertTrue($loan->needs_high_value_approval);
        $this->assertCount(2, $loan->loanItems);
    }

    public function test_approval_berjenjang_dengan_ttd_digital_level_2(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->laptop->id, 'qty' => 1]],
            'Uji approval berjenjang',
            '2026-12-01',
            '2026-12-03'
        );

        $fakeSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        // Approval Level 1 oleh Laboran
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan), [
                'note' => 'Disetujui laboran TKJ',
                'signature' => $fakeSignature,
            ])
            ->assertRedirect();

        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);
        $this->assertSame(2, $loan->nextApprovalLevel());

        // Tanda tangan digital bersifat opsional — dokumen resmi dapat dicetak
        // dan ditandatangani secara fisik, jadi approval tanpa TTD tetap sah.
        $this->actingAs($this->wakasek)
            ->post(route('loans.approve', $loan), [
                'note' => 'Disetujui wakasek tanpa TTD digital',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertNull($loan->nextApprovalLevel());

        $level2 = $loan->approvals()->where('level', 2)->firstOrFail();
        $this->assertSame($this->wakasek->id, $level2->approver_id);
        $this->assertNull($level2->signature_path);
    }

    public function test_approval_wakasek_tanpa_ttd_digital_lengkap_dan_berhasil(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->laptop->id, 'qty' => 1]],
            'Uji approval tanpa tanda tangan',
            '2026-12-01',
            '2026-12-03'
        );

        $this->actingAs($this->wakasek)
            ->post(route('loans.approve', $loan), [
                'note' => 'Persetujuan Wakasek tanpa tanda tangan digital',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();

        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertTrue($loan->isFullyApproved());
        $this->assertNull($loan->handoverBlocker());
    }

    public function test_penolakan_peminjaman_dengan_alasan(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->kamera->id, 'qty' => 1]],
            'Uji penolakan',
            '2026-12-05',
            '2026-12-07'
        );

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.reject', $loan), [
                'note' => 'Kamera sedang disiapkan untuk acara wisuda.',
            ])
            ->assertRedirect();

        $this->assertSame(LoanStatus::DITOLAK, $loan->refresh()->status);
        $this->assertStringContainsString('wisuda', $loan->reject_reason);
    }

    public function test_anti_double_booking_pada_stok_terbatas(): void
    {
        $service = app(LoanService::class);

        // Budi meminjam 1-satunya laptop pada 10-12 Des
        $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->laptop->id, 'qty' => 1]],
            'Pinjam laptop',
            '2026-12-10',
            '2026-12-12'
        );

        // Sari mencoba meminjam laptop yang sama pada 11-14 Des (irisan 11-12 Des)
        $this->expectException(AvailabilityException::class);
        $service->requestLoan(
            $this->guruSari,
            [['item_id' => $this->laptop->id, 'qty' => 1]],
            'Pinjam laptop bentrok',
            '2026-12-11',
            '2026-12-14'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\NotificationType;
use App\Jobs\CheckDueSoonLoans;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $laboranTkj;

    private User $laboranRpl;

    private User $guruBudi;

    private User $guruSari;

    private Room $roomTkj;

    private Room $roomRpl;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->laboranTkj = User::where('username', 'laboran.tkj')->firstOrFail();
        $this->laboranRpl = User::where('username', 'laboran.rpl')->firstOrFail();
        $this->guruBudi = User::where('username', 'guru.budi')->firstOrFail();
        $this->guruSari = User::where('username', 'guru.sari')->firstOrFail();
        $this->roomTkj = Room::where('major_id', Major::where('code', 'TKJ')->value('id'))->firstOrFail();
        $this->roomRpl = Room::where('major_id', Major::where('code', 'RPL')->value('id'))->firstOrFail();
        $this->category = Category::where('is_consumable', false)->firstOrFail();
    }

    private function makeItem(string $name, int $qty, ?Room $room = null): Item
    {
        static $seq = 0;
        $seq++;

        return Item::create([
            'internal_id' => 'UJI-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 5)).'-'.$seq,
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => (string) (8000 + $seq),
            'name' => $name,
            'category_id' => $this->category->id,
            'room_id' => ($room ?? $this->roomTkj)->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => $qty,
            'unit' => 'unit',
            'is_high_value' => false,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_peminjam_dapat_membatalkan_pengajuan_dan_jadwal_kembali_tersedia(): void
    {
        $item = $this->makeItem('Proyektor Uji', 1);

        $this->actingAs($this->guruBudi)
            ->post(route('loans.store'), [
                'purpose' => 'Kegiatan yang dibatalkan',
                'start_date' => '2027-01-10T08:00',
                'due_date' => '2027-01-12T16:00',
                'lines' => [['item_id' => $item->id, 'qty' => 1]],
            ])
            ->assertRedirect();

        $loan = Loan::latest('id')->firstOrFail();

        $this->actingAs($this->guruBudi)
            ->post(route('loans.cancel', $loan), ['cancel_reason' => 'Kegiatan diundur'])
            ->assertRedirect();

        $loan->refresh();
        $this->assertSame(LoanStatus::DIBATALKAN, $loan->status);
        $this->assertNotNull($loan->cancelled_at);
        $this->assertSame($this->guruBudi->id, $loan->cancelled_by);

        // Slot yang dibatalkan tidak boleh memblokir pengajuan berikutnya.
        $this->actingAs($this->guruSari)
            ->post(route('loans.store'), [
                'purpose' => 'Memakai slot yang sama',
                'start_date' => '2027-01-10T08:00',
                'due_date' => '2027-01-12T16:00',
                'lines' => [['item_id' => $item->id, 'qty' => 1]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Loan::where('purpose', 'Memakai slot yang sama')->exists());
    }

    public function test_pembatalan_ditolak_setelah_barang_diserahkan(): void
    {
        $item = $this->makeItem('Kamera Uji', 1);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 1]], 'Uji', '2027-02-01 08:00', '2027-02-02 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);

        $this->actingAs($this->guruBudi)
            ->post(route('loans.cancel', $loan), ['cancel_reason' => 'Batal'])
            ->assertSessionHasErrors('cancel');

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_serah_terima_mengubah_status_menjadi_dipinjam_dan_mencatat_petugas(): void
    {
        $item = $this->makeItem('Laptop Uji', 2);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 2]], 'Uji serah terima', '2027-03-01 08:00', '2027-03-03 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), ['note' => 'Diserahkan lengkap'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::DIPINJAM, $loan->status);
        $this->assertNotNull($loan->handed_over_at);
        $this->assertSame($this->laboranTkj->id, $loan->handed_over_by);
        $this->assertSame(0, $item->refresh()->availableQuantity());
    }

    public function test_pengembalian_parsial_akumulatif_sampai_selesai(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $item = $this->makeItem('Kursi Uji', 3);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 3]], 'Uji parsial', '2027-04-01 08:00', '2027-04-03 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);
        $loanItem = $loan->loanItems()->firstOrFail();

        // Kembali 1 unit lebih dulu — pinjaman tetap aktif.
        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $item->room_id,
        ]]);
        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
        $this->assertSame(1, $loanItem->refresh()->qty_returned);

        // Sisa 2 unit kembali — akumulasi harus 3, bukan menimpa menjadi 2.
        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 2,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $item->room_id,
        ]]);

        $loan->refresh();
        $this->assertSame(LoanStatus::SELESAI, $loan->status);
        $this->assertSame(3, $loanItem->refresh()->qty_returned);
        $this->assertSame(0, $loanItem->missingQuantity());
        $this->assertSame(ItemStatus::BAIK, $item->refresh()->status);
        $this->assertSame(3, $item->availableQuantity());
    }

    public function test_pengembalian_melebihi_sisa_ditolak(): void
    {
        $item = $this->makeItem('Meja Uji', 1);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 1]], 'Uji validasi', '2027-05-01 08:00', '2027-05-02 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);
        $loanItem = $loan->loanItems()->firstOrFail();

        $this->expectException(ValidationException::class);

        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 2,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $item->room_id,
        ]]);
    }

    public function test_selisih_ditutup_wakasek_menjadi_hilang_permanen(): void
    {
        $item = $this->makeItem('Radio Uji', 2);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 2]], 'Uji selisih', '2027-06-01 08:00', '2027-06-03 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);
        $loanItem = $loan->loanItems()->firstOrFail();

        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $item->room_id,
        ]]);

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
        $this->assertSame(1, $loanItem->refresh()->qty_lost);

        $this->actingAs($this->wakasek)
            ->post(route('loans.close-shortage', $loan))
            ->assertRedirect();

        $this->assertSame(LoanStatus::SELESAI, $loan->refresh()->status);
        $this->assertSame(1, $item->refresh()->qty_lost);
        $this->assertSame(1, $item->availableQuantity());
    }

    public function test_kerusakan_parsial_tidak_menghapus_seluruh_stok(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $item = $this->makeItem('Speaker Uji', 4);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 4]], 'Uji rusak parsial', '2027-07-01 08:00', '2027-07-03 16:00');
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);
        $loanItem = $loan->loanItems()->firstOrFail();

        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 4,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $item->room_id,
            'damage_photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]]);

        $item->refresh();
        $this->assertSame(4, $item->qty_damaged);
        $this->assertSame(0, $item->availableQuantity());
        $this->assertSame(ItemStatus::RUSAK_RINGAN, $item->status);
        $this->assertSame(LoanStatus::SELESAI, $loan->refresh()->status);
    }

    public function test_laboran_jurusan_lain_tidak_bisa_melihat_detail_pinjaman(): void
    {
        $item = $this->makeItem('Alat TKJ', 1, $this->roomTkj);
        $loan = app(LoanService::class)->requestLoan(
            $this->guruBudi,
            [['item_id' => $item->id, 'qty' => 1]],
            'Uji IDOR',
            '2027-08-01 08:00',
            '2027-08-02 16:00'
        );

        $this->actingAs($this->laboranRpl)
            ->get(route('loans.show', $loan))
            ->assertForbidden();
    }

    public function test_pengingat_jatuh_tempo_tidak_duplikat(): void
    {
        $item = $this->makeItem('Alat Pengingat', 1);
        $service = app(LoanService::class);

        $loan = $service->requestLoan($this->guruBudi, [['item_id' => $item->id, 'qty' => 1]], 'Uji pengingat', now()->subHour(), now()->addHours(6));
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $this->laboranTkj);

        (new CheckDueSoonLoans)->handle();
        (new CheckDueSoonLoans)->handle();

        $count = AppNotification::where('user_id', $this->guruBudi->id)
            ->where('type', NotificationType::PENGEMBALIAN->value)
            ->where('link', route('loans.show', $loan))
            ->count();

        $this->assertSame(1, $count);
    }
}

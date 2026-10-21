<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Livewire\Loan\ReturnForm;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnFormLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $guru;

    private Item $item;

    private Room $origRoom;

    private Room $targetRoom;

    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('username', 'laboran.tkj')->firstOrFail();
        $this->guru = User::where('username', 'guru.budi')->firstOrFail();

        $category = Category::firstOrFail();
        $rooms = Room::all();
        $this->origRoom = $rooms[0];
        $this->targetRoom = $rooms[1];

        $this->item = Item::create([
            'internal_id' => 'RET-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '7701',
            'name' => 'Barang Uji Pengembalian',
            'category_id' => $category->id,
            'room_id' => $this->origRoom->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $this->loan = app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $this->item->id, 'qty' => 1]],
            'Uji checklist pengembalian',
            '2026-11-01',
            '2026-11-03'
        );
        $this->loan->update(['status' => LoanStatus::DIPINJAM]);
    }

    public function test_pengembalian_normal_sukses_menyelesaikan_pinjaman(): void
    {
        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $this->loan])
            ->set('rows.0.qty_returned', 1)
            ->set('rows.0.condition_in', Condition::BAIK->value)
            ->set('rows.0.returned_room_id', $this->origRoom->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('loans.show', $this->loan));

        $this->assertSame(LoanStatus::SELESAI, $this->loan->refresh()->status);
        $this->assertSame(ItemStatus::BAIK, $this->item->refresh()->status);
    }

    public function test_pengembalian_ruangan_berbeda_menampilkan_peringatan_dan_wajib_konfirmasi(): void
    {
        $this->actingAs($this->laboran);

        // 1) Ganti ruangan ke ruangan berbeda tanpa centang confirmMismatch -> Harus error
        Livewire::test(ReturnForm::class, ['loan' => $this->loan])
            ->set('rows.0.returned_room_id', $this->targetRoom->id)
            ->call('submit')
            ->assertHasErrors(['confirmMismatch']);

        // 2) Centang konfirmasi -> Sukses, mutasi tercatat, ruangan dan ID internal diperbarui
        $oldInternalId = $this->item->internal_id;

        Livewire::test(ReturnForm::class, ['loan' => $this->loan])
            ->set('rows.0.returned_room_id', $this->targetRoom->id)
            ->set('confirmMismatch', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->item->refresh();
        $this->assertSame($this->targetRoom->id, $this->item->room_id);
        $this->assertNotSame($oldInternalId, $this->item->internal_id);
    }

    public function test_pengembalian_kondisi_rusak_validasi_foto_bukti(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs($this->laboran);

        // Kondisi rusak tanpa upload foto -> Harus error validasi foto
        Livewire::test(ReturnForm::class, ['loan' => $this->loan])
            ->set('rows.0.condition_in', Condition::RUSAK_BERAT->value)
            ->set('rows.0.damage_photo', null)
            ->call('submit')
            ->assertHasErrors(['rows.0.damage_photo']);

        // Upload foto bukti -> Harus sukses
        $file = UploadedFile::fake()->image('rusak_berat.jpg');
        Livewire::test(ReturnForm::class, ['loan' => $this->loan])
            ->set('rows.0.condition_in', Condition::RUSAK_BERAT->value)
            ->set('rows.0.damage_photo', $file)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(ItemStatus::RUSAK_BERAT, $this->item->refresh()->status);
    }
}

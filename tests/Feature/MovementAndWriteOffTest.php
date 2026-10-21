<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use App\Services\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovementAndWriteOffTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private Item $item;

    private Room $room1;

    private Room $room2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->admin = User::where('username', 'admin.sarpras')->firstOrFail();

        $rooms = Room::all();
        $this->room1 = $rooms[0];
        $this->room2 = $rooms[1];

        $category = Category::firstOrFail();

        $this->item = Item::create([
            'internal_id' => 'MOV-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '6601',
            'name' => 'Server Rack Unit',
            'category_id' => $category->id,
            'room_id' => $this->room1->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_mutasi_lokasi_memperbarui_ruangan_dan_id_internal_dalam_transaksi(): void
    {
        $oldInternalId = $this->item->internal_id;

        $response = $this->actingAs($this->admin)->post(route('movements.store'), [
            'item_id' => $this->item->id,
            'to_room_id' => $this->room2->id,
            'note' => 'Pemindahan untuk pemeliharaan server',
        ]);

        $response->assertRedirect(route('movements.index'));
        $response->assertSessionHas('success');

        $this->item->refresh();
        $this->assertSame($this->room2->id, $this->item->room_id);
        $this->assertNotSame($oldInternalId, $this->item->internal_id);
        $this->assertStringStartsWith(IdGenerator::roomPrefix($this->room2), $this->item->internal_id);

        $this->assertDatabaseHas('item_movements', [
            'item_id' => $this->item->id,
            'from_room_id' => $this->room1->id,
            'to_room_id' => $this->room2->id,
            'moved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'mutasi',
            'subject_id' => $this->item->id,
        ]);
    }

    public function test_mutasi_ke_ruangan_yang_sama_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('movements.store'), [
            'item_id' => $this->item->id,
            'to_room_id' => $this->room1->id, // ruangan yang sama
        ])->assertStatus(422);
    }

    public function test_write_off_lengkap_dengan_berita_acara_approval_dan_audit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $ba = UploadedFile::fake()->create('Berita_Acara_Kerusakan.pdf', 200, 'application/pdf');

        // Pengajuan oleh Admin Sarpras
        $this->actingAs($this->admin)->post(route('write-offs.store'), [
            'item_id' => $this->item->id,
            'quantity' => 1,
            'reason' => 'Komponen terbakar korsleting listrik fatal',
            'berita_acara' => $ba,
        ])->assertRedirect(route('write-offs.index'));

        $writeOff = WriteOff::where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame('PENDING', $writeOff->status);
        Storage::disk('local')->assertExists($writeOff->berita_acara_path);

        // Approval oleh Wakasek
        $this->actingAs($this->wakasek)->post(route('write-offs.approve', $writeOff))
            ->assertRedirect();

        $this->assertSame('APPROVED', $writeOff->refresh()->status);
        $this->assertSame(ItemStatus::DIHAPUS, $this->item->refresh()->status);
        $this->assertSame(0, $this->item->quantity);

        // Data barang tetap tersimpan di database (tidak di-hard-delete)
        $this->assertDatabaseHas('items', ['id' => $this->item->id]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'penghapusan',
            'subject_id' => $writeOff->id,
        ]);
    }
}

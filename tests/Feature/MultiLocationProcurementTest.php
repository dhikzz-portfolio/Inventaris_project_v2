<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MultiLocationProcurementTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketua;

    private Category $category;

    /** @var Collection<int, Room> */
    private $rooms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->ketua = User::where('username', 'ketua.laboran')->firstOrFail();
        $this->category = Category::firstOrFail();
        $this->rooms = Room::where('is_lab', true)->orderBy('code')->take(3)->get();

        $this->assertCount(3, $this->rooms);
    }

    /** @param array<int, array{room_id: int, quantity: int}> $allocations */
    private function payload(array $allocations, int $quantity): array
    {
        return [
            'name' => 'Kursi Uji Alokasi '.uniqid(),
            'category_id' => $this->category->id,
            'specification' => 'Kursi uji distribusi multi-lokasi',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => $quantity,
            'unit' => 'unit',
            'allocations' => $allocations,
        ];
    }

    public function test_pengadaan_40_unit_dibagi_20_15_5_ke_tiga_lokasi(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 20],
            ['room_id' => $this->rooms[1]->id, 'quantity' => 15],
            ['room_id' => $this->rooms[2]->id, 'quantity' => 5],
        ], 40);

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)
            ->assertRedirect(route('catalogs.index'))
            ->assertSessionHasNoErrors();

        $catalog = Item::where('name', $payload['name'])->firstOrFail();

        $this->assertSame(3, $catalog->allocations()->count());
        $this->assertSame(40, $catalog->units()->count());
        $this->assertSame(40, $catalog->allocations()->sum('quantity'));

        foreach ($this->rooms as $index => $room) {
            $expected = [20, 15, 5][$index];
            $units = $catalog->units()->where('room_id', $room->id)->get();

            $this->assertCount($expected, $units);
            $this->assertTrue($units->every(
                fn (AssetUnit $unit) => str_starts_with($unit->inventory_code, $room->buildingCode().(int) $room->floor.'-'.str_pad((string) $room->room_no, 2, '0', STR_PAD_LEFT))
            ));
        }

        $this->assertSame(
            $catalog->units()->count(),
            $catalog->units()->pluck('inventory_code')->unique()->count()
        );
        $this->assertSame('APPROVED', $catalog->acquisition_status);
    }

    public function test_total_alokasi_melebihi_jumlah_pengadaan_ditolak(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 20],
            ['room_id' => $this->rooms[1]->id, 'quantity' => 15],
            ['room_id' => $this->rooms[2]->id, 'quantity' => 6],
        ], 40);

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)
            ->assertSessionHasErrors('allocations');

        $this->assertDatabaseMissing('item_catalogs', ['name' => $payload['name']]);
    }

    public function test_alokasi_kurang_dari_jumlah_pengadaan_ditolak(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 20],
        ], 40);

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)
            ->assertSessionHasErrors('allocations');

        $this->assertDatabaseMissing('item_catalogs', ['name' => $payload['name']]);
    }

    public function test_ruangan_duplikat_pada_alokasi_ditolak(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 1],
            ['room_id' => $this->rooms[0]->id, 'quantity' => 1],
        ], 2);

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)
            ->assertSessionHasErrors();
    }

    public function test_pengadaan_ketua_laboran_menunggu_approval_lalu_unit_dibuat_sesuai_alokasi(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 3],
            ['room_id' => $this->rooms[1]->id, 'quantity' => 2],
        ], 5);

        $this->actingAs($this->ketua)->post(route('items.store'), $payload)
            ->assertRedirect(route('catalogs.index'));

        $catalog = Item::where('name', $payload['name'])->firstOrFail();

        $this->assertSame('PENDING', $catalog->acquisition_status);
        $this->assertSame(0, $catalog->units()->count());
        $this->assertSame(5, $catalog->allocations()->sum('quantity'));

        $this->actingAs($this->wakasek)
            ->post(route('items.approve', $catalog))
            ->assertRedirect();

        $this->assertSame('APPROVED', $catalog->refresh()->acquisition_status);
        $this->assertSame(3, $catalog->units()->where('room_id', $this->rooms[0]->id)->count());
        $this->assertSame(2, $catalog->units()->where('room_id', $this->rooms[1]->id)->count());
    }

    public function test_kode_inventaris_selalu_dibuat_backend_dan_tidak_dapat_diisi_manual(): void
    {
        $payload = $this->payload([
            ['room_id' => $this->rooms[0]->id, 'quantity' => 1],
        ], 1);

        $payload['inventory_code'] = 'Z9-99-99';

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)
            ->assertSessionHasNoErrors();

        $catalog = Item::where('name', $payload['name'])->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->assertNotSame('Z9-99-99', $unit->inventory_code);
        $this->assertTrue(app(InventoryCodeService::class)->isValidFormat($unit->inventory_code));
        $this->assertTrue($unit->inventory_code !== '' && str_starts_with($unit->inventory_code, $this->rooms[0]->buildingCode()));
    }
}

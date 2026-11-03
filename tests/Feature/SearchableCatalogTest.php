<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $wakasek;

    private Category $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
        $this->room = Room::where('code', 'H1-01')->firstOrFail();
    }

    public function test_form_pengadaan_menyediakan_pencarian_katalog_dan_empty_state(): void
    {
        $catalog = Item::query()->approved()->firstOrFail();

        $this->actingAs($this->laboran)
            ->get(route('items.create'))
            ->assertOk()
            ->assertSee('catalog_search')
            ->assertSee('Tidak ada katalog yang cocok')
            ->assertSee($catalog->name);
    }

    public function test_pilihan_katalog_direvalidasi_ulang_di_backend(): void
    {
        // Katalog yang belum tercatat sebagai inventaris tidak boleh dipilih.
        $draft = Item::create([
            'internal_id' => 'DRAFT-SEARCH-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '7701',
            'name' => 'Katalog Draft Uji',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'acquisition_status' => 'DRAFT',
        ]);

        $this->actingAs($this->laboran)->post(route('items.store'), [
            'catalog_id' => $draft->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->room->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('catalog_id');

        // ID tidak sah / teks pencarian tidak boleh diperlakukan sebagai ID.
        $this->actingAs($this->laboran)->post(route('items.store'), [
            'catalog_id' => 999999,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->room->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('catalog_id');

        $response = $this->actingAs($this->laboran)->post(route('items.store'), [
            'catalog_id' => 'Kursi Plastik',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->room->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('catalog_id');
    }

    public function test_katalog_valid_dapat_dipilih_tanpa_menduplikasi_katalog(): void
    {
        $catalog = Item::query()->approved()->firstOrFail();
        $catalogCount = Item::count();

        $this->actingAs($this->laboran)->post(route('items.store'), [
            'catalog_id' => $catalog->id,
            'brand' => 'Merek Pencarian Uji',
            'model_type' => 'Tipe Pencarian Uji',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->room->id, 'quantity' => 1]],
        ])->assertRedirect(route('items.acquisitions'))->assertSessionHasNoErrors();

        $this->assertSame($catalogCount, Item::count());
        $this->assertDatabaseHas('acquisition_allocations', [
            'item_catalog_id' => $catalog->id,
            'room_id' => $this->room->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Livewire\Catalog\SpecificationDirectory;
use App\Livewire\Catalog\SpecificationUnits;
use App\Livewire\Distribution\DistributionForm;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Distribution;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpecificationDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $laboranRpl;

    private Room $labRoom;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->laboranRpl = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->labRoom = Room::where('code', 'H1-01')->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function procurementPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kursi Uji Spesifikasi',
            'category_id' => $this->category->id,
            'brand' => 'Futura',
            'model_type' => 'Kursi Plastik Tipe A',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 3,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->labRoom->id, 'quantity' => 3]],
        ], $overrides);
    }

    public function test_pengadaan_menyimpan_merek_dan_tipe_model_sebagai_spesifikasi(): void
    {
        $this->actingAs($this->wakasek)
            ->post(route('items.store'), $this->procurementPayload())
            ->assertRedirect(route('catalogs.index'))
            ->assertSessionHasNoErrors();

        $catalog = Item::where('name', 'Kursi Uji Spesifikasi')->firstOrFail();
        $specification = $catalog->specifications()->firstOrFail();

        $this->assertSame('Futura', $specification->brand);
        $this->assertSame('Kursi Plastik Tipe A', $specification->model_type);
        $this->assertSame(3, $specification->units()->count());
        $this->assertSame(3, $catalog->units()->where('specification_id', $specification->id)->count());
    }

    public function test_pengadaan_tanpa_merek_dan_tipe_memakai_nilai_khusus(): void
    {
        $this->actingAs($this->wakasek)
            ->post(route('items.store'), $this->procurementPayload([
                'name' => 'Kursi Tanpa Merek Uji',
                'brand' => '',
                'model_type' => '',
            ]))
            ->assertSessionHasNoErrors();

        $specification = Item::where('name', 'Kursi Tanpa Merek Uji')->firstOrFail()
            ->specifications()->firstOrFail();

        $this->assertSame(Specification::BRAND_UNSPECIFIED, $specification->brand);
        $this->assertSame(Specification::MODEL_UNSPECIFIED, $specification->model_type);
    }

    public function test_satu_katalog_dapat_memiliki_beberapa_spesifikasi_tanpa_katalog_baru(): void
    {
        $this->actingAs($this->wakasek)->post(route('items.store'), $this->procurementPayload())->assertSessionHasNoErrors();

        $catalog = Item::where('name', 'Kursi Uji Spesifikasi')->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('items.store'), $this->procurementPayload([
            'catalog_id' => $catalog->id,
            'name' => null,
            'category_id' => null,
            'brand' => 'Chitose',
            'model_type' => 'Kursi Susun',
            'quantity' => 2,
            'allocations' => [['room_id' => $this->labRoom->id, 'quantity' => 2]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Item::where('name', 'Kursi Uji Spesifikasi')->count());
        $this->assertSame(2, $catalog->specifications()->count());
        $this->assertSame(5, $catalog->units()->count());
    }

    public function test_distribusi_mengambil_unit_dari_spesifikasi_yang_dipilih_saja(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $futura = $catalog->specifications()->where('brand', 'Futura')->firstOrFail();
        $chitose = $catalog->specifications()->where('brand', 'Chitose')->firstOrFail();
        $destination = Room::where('code', 'G2-02')->firstOrFail();

        $chitoseBefore = $chitose->units()->count();

        Livewire::actingAs($this->wakasek)
            ->test(DistributionForm::class)
            ->set('catalogId', (string) $catalog->id)
            ->set('specificationId', (string) $futura->id)
            ->set('toRoomId', (string) $destination->id)
            ->set('quantity', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $distribution = Distribution::latest('id')->firstOrFail();

        $this->assertSame(5, $distribution->quantity);
        $this->assertSame($futura->id, $distribution->specification_id);
        $this->assertCount(5, $distribution->units);
        $this->assertSame(5, AssetUnit::where('room_id', $destination->id)
            ->where('specification_id', $futura->id)->count());
        // Stok merek lain tidak ikut terpakai.
        $this->assertSame($chitoseBefore, $chitose->units()->count());

        // Riwayat distribusi dapat ditelusuri hingga unit dan kode barunya.
        $line = $distribution->units->first();
        $this->assertSame($line->new_code, $line->unit->inventory_code);
        $this->assertSame($destination->id, $line->unit->room_id);
    }

    public function test_distribusi_tidak_boleh_melebihi_stok_spesifikasi(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $chitose = $catalog->specifications()->where('brand', 'Chitose')->firstOrFail();
        $destination = Room::where('code', 'G2-02')->firstOrFail();

        $available = $chitose->distributableCount($this->wakasek);

        Livewire::actingAs($this->wakasek)
            ->test(DistributionForm::class)
            ->set('catalogId', (string) $catalog->id)
            ->set('specificationId', (string) $chitose->id)
            ->set('toRoomId', (string) $destination->id)
            ->set('quantity', $available + 1)
            ->call('submit')
            ->assertHasErrors('quantity');

        $this->assertSame(0, Distribution::count());
    }

    public function test_unit_yang_sudah_didistribusikan_tidak_dialokasikan_lagi(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $futura = $catalog->specifications()->where('brand', 'Futura')->firstOrFail();
        $first = Room::where('code', 'G2-02')->firstOrFail();
        $second = Room::where('code', 'G2-01')->firstOrFail();

        $component = Livewire::actingAs($this->wakasek);
        $component->test(DistributionForm::class)
            ->set('catalogId', (string) $catalog->id)
            ->set('specificationId', (string) $futura->id)
            ->set('toRoomId', (string) $first->id)
            ->set('quantity', 20)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(0, $futura->distributableCount($this->wakasek));

        Livewire::actingAs($this->wakasek)
            ->test(DistributionForm::class)
            ->set('catalogId', (string) $catalog->id)
            ->set('specificationId', (string) $futura->id)
            ->set('toRoomId', (string) $second->id)
            ->set('quantity', 1)
            ->call('submit')
            ->assertHasErrors('quantity');
    }

    public function test_halaman_katalog_menampilkan_spesifikasi_dan_unit_spesifikasi(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $futura = $catalog->specifications()->where('brand', 'Futura')->firstOrFail();
        $chitose = $catalog->specifications()->where('brand', 'Chitose')->firstOrFail();

        $this->actingAs($this->wakasek)
            ->get(route('catalogs.show', $catalog))
            ->assertOk()
            ->assertSee('Futura')
            ->assertSee('Kursi Plastik Tipe A')
            ->assertSee('Chitose')
            ->assertSee('Kursi Susun')
            ->assertSee('Tanpa Merek')
            ->assertSee('Tidak Ditentukan');

        $futuraCode = $futura->units()->orderBy('inventory_code')->value('inventory_code');
        $chitoseCode = $chitose->units()->orderBy('inventory_code')->value('inventory_code');

        $this->actingAs($this->wakasek)
            ->get(route('specifications.show', $futura))
            ->assertOk()
            ->assertSee($futuraCode)
            ->assertDontSee($chitoseCode);
    }

    public function test_search_merek_dan_tipe_serta_filter_dapat_digunakan_bersamaan(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $chitose = $catalog->specifications()->where('brand', 'Chitose')->firstOrFail();

        Livewire::actingAs($this->wakasek)
            ->test(SpecificationDirectory::class, ['catalogId' => $catalog->id])
            ->set('search', 'Chitose')
            ->assertSee('Kursi Susun')
            ->assertDontSee('Kursi Plastik Tipe A')
            ->assertSee((string) $chitose->units()->count());

        Livewire::actingAs($this->wakasek)
            ->test(SpecificationDirectory::class, ['catalogId' => $catalog->id])
            ->set('brand', 'Chitose')
            ->set('condition', Condition::RUSAK_RINGAN->value)
            ->set('building', 'I')
            ->assertSee('Chitose')
            ->assertSee('Kursi Susun')
            ->assertDontSee('Kursi Plastik Tipe A');
    }

    public function test_pencarian_kode_inventaris_pada_halaman_unit_spesifikasi(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();
        $futura = $catalog->specifications()->where('brand', 'Futura')->firstOrFail();
        $codes = $futura->units()->orderBy('inventory_code')->pluck('inventory_code');

        Livewire::actingAs($this->wakasek)
            ->test(SpecificationUnits::class, ['specificationId' => $futura->id])
            ->set('search', $codes[0])
            ->assertSee($codes[0])
            ->assertDontSee($codes[1]);
    }

    public function test_jumlah_katalog_dan_spesifikasi_sesuai_unit_aktual(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();

        $this->assertSame(
            $catalog->units()->where('status', '!=', 'DIHAPUS')->count(),
            (int) $catalog->specifications->sum(fn (Specification $specification) => $specification->units()->where('status', '!=', 'DIHAPUS')->count())
        );
    }

    public function test_unit_dan_transaksi_lama_tetap_terhubung_setelah_spesifikasi_dibuat(): void
    {
        $loanItem = LoanItem::query()->firstOrFail();
        $catalog = $loanItem->item;

        $this->assertNotNull($catalog);
        $this->assertTrue($catalog->specifications()->exists());

        $unit = $catalog->units()->firstOrFail();
        $originalCode = $unit->inventory_code;
        $originalCatalog = $unit->item_catalog_id;

        // Menambah merek/tipe baru tidak boleh mengubah identitas unit lama.
        Specification::resolveFor($catalog, 'Merek Baru', 'Tipe Baru');

        $unit->refresh();
        $this->assertSame($originalCode, $unit->inventory_code);
        $this->assertSame($originalCatalog, $unit->item_catalog_id);
        $this->assertNotNull($unit->specification);
        $this->assertSame($catalog->id, $loanItem->fresh()->item->id);
    }

    public function test_seeder_dapat_dijalankan_berulang_tanpa_duplikasi(): void
    {
        $snapshot = [
            Item::count(),
            Specification::count(),
            AssetUnit::count(),
        ];

        $this->seed(MasterDataSeeder::class);
        $this->seed(DemoInventorySeeder::class);

        $this->assertSame($snapshot, [
            Item::count(),
            Specification::count(),
            AssetUnit::count(),
        ]);
    }

    public function test_hak_akses_berlaku_pada_halaman_spesifikasi(): void
    {
        $otherSpecification = AssetUnit::whereHas('room', fn ($room) => $room->where('code', 'G1-01'))
            ->firstOrFail()
            ->specification;

        $this->actingAs($this->laboranRpl)
            ->get(route('specifications.show', $otherSpecification))
            ->assertNotFound();

        $ownSpecification = AssetUnit::whereHas('room', fn ($room) => $room->where('code', 'H1-01'))
            ->firstOrFail()
            ->specification;

        $this->actingAs($this->laboranRpl)
            ->get(route('specifications.show', $ownSpecification))
            ->assertOk();
    }
}

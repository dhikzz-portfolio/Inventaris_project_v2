<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Exceptions\AvailabilityException;
use App\Livewire\Catalog\CatalogDirectory;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketuaLaboran;

    private User $laboran;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketuaLaboran = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
    }

    public function test_katalog_tanpa_unit_dan_transaksi_dapat_dihapus_permanen(): void
    {
        $catalog = $this->makeCatalogWithoutUnits('Katalog Baru Tanpa Unit');

        $this->actingAs($this->wakasek)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertRedirect(route('catalogs.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($catalog);
    }

    public function test_katalog_berunit_dinonaktifkan_dan_tidak_ditawarkan_untuk_peminjaman_baru(): void
    {
        $catalog = Item::where('name', 'Komputer PC')->firstOrFail();
        $unitCount = $catalog->units()->count();

        $this->actingAs($this->wakasek)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertRedirect(route('catalogs.index'))
            ->assertSessionHas('success');

        $catalog->refresh();
        $this->assertModelExists($catalog);
        $this->assertNotNull($catalog->archived_at);
        $this->assertSame($unitCount, $catalog->units()->count());

        $this->actingAs($this->guru)->get(route('catalogs.show', $catalog))->assertNotFound();

        $this->expectException(AvailabilityException::class);
        app(LoanService::class)->requestLoan(
            $this->guru,
            [['item_id' => $catalog->id, 'qty' => 1]],
            'Meminjam katalog nonaktif',
            '2027-09-01 08:00',
            '2027-09-02 16:00'
        );
    }

    public function test_ketua_laboran_tidak_dapat_menghapus_katalog_nonlaboratorium(): void
    {
        $catalog = Item::where('name', 'Proyektor Epson')->firstOrFail();

        $this->actingAs($this->ketuaLaboran)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertNotFound();

        $this->assertNull($catalog->refresh()->archived_at);
    }

    public function test_laboran_dan_peminjam_tidak_berwenang_menghapus_katalog(): void
    {
        $catalog = Item::where('name', 'Komputer PC')->firstOrFail();

        $this->actingAs($this->laboran)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertForbidden();

        $this->actingAs($this->guru)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertForbidden();

        $this->assertNull($catalog->refresh()->archived_at);
    }

    public function test_katalog_diarsipkan_tidak_tampil_di_daftar_katalog(): void
    {
        $catalog = Item::where('name', 'Komputer PC')->firstOrFail();

        Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('search', 'Komputer PC')
            ->assertSee('Komputer PC');

        $catalog->update(['archived_at' => now(), 'archive_reason' => 'Uji arsip']);

        Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('search', 'Komputer PC')
            ->assertDontSee('Komputer PC');
    }

    private function makeCatalogWithoutUnits(string $name): Item
    {
        $room = Room::where('code', 'H1-01')->firstOrFail();
        $category = Category::firstOrFail();

        return Item::create([
            'internal_id' => 'DEL-'.strtoupper(substr(md5($name), 0, 6)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '9901',
            'name' => $name,
            'category_id' => $category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2026,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }
}

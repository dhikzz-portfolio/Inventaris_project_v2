<?php

namespace Tests\Feature;

use App\Livewire\Catalog\CatalogDirectory;
use App\Livewire\Catalog\SpecificationDirectory;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_kartu_katalog_hanya_menampilkan_nama_dan_kategori(): void
    {
        Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('search', 'Kursi')
            ->assertSee('Kursi Umum')
            ->assertDontSee('Lemari Arsip')
            ->assertDontSee('Chitose');
    }

    public function test_pencarian_merek_dilakukan_di_detail_katalog(): void
    {
        $catalog = Item::where('name', 'Kursi Umum')->firstOrFail();

        Livewire::actingAs($this->wakasek)
            ->test(SpecificationDirectory::class, ['catalogId' => $catalog->id])
            ->set('brand', 'Chitose')
            ->assertSee('Chitose');
    }

    public function test_perubahan_gedung_mereset_lantai_dan_ruangan(): void
    {
        $component = Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('building', 'G')
            ->set('floor', '1')
            ->set('room', '1');

        $this->assertSame('1', $component->get('room'));

        $component->set('building', 'H');

        $this->assertSame('', $component->get('floor'));
        $this->assertSame('', $component->get('room'));
    }

    public function test_filter_kondisi_dan_status_menggunakan_kolom_terpisah(): void
    {
        Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('condition', 'RUSAK_RINGAN')
            ->assertSee('Kursi Umum')
            ->assertSee('Filter');

        Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('status', 'AKTIF')
            ->assertSee('Kursi Umum');
    }

    public function test_reset_filter_mengosongkan_seluruh_pilihan(): void
    {
        $component = Livewire::actingAs($this->wakasek)
            ->test(CatalogDirectory::class)
            ->set('condition', 'BAIK')
            ->set('status', 'AKTIF');

        $component->call('resetFilters');

        foreach (['search', 'category', 'building', 'floor', 'room', 'condition', 'status', 'availability'] as $field) {
            $this->assertSame('', $component->get($field), "Filter {$field} tidak kosong setelah reset.");
        }
    }
}

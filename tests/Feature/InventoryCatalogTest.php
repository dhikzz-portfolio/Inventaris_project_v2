<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Livewire\Catalog\ItemCatalog;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\IdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $wakasek;

    private User $guru;

    private Category $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin.sarpras')->firstOrFail();
        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->guru = User::where('username', 'guru.budi')->firstOrFail();
        $this->category = Category::where('is_consumable', false)->firstOrFail();
        $this->room = Room::firstOrFail();
    }

    public function test_generator_id_internal_dan_nomor_register(): void
    {
        $id1 = IdGenerator::internalId('Laptop Lenovo Thinkpad', $this->room->code);
        $this->assertStringStartsWith('LAPTOP-', $id1);
        $this->assertStringContainsString($this->room->code, $id1);

        $reg1 = IdGenerator::register($this->category->code);
        $this->assertSame($this->category->code, $reg1['asset_code']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $reg1['register_no']);
    }

    public function test_tambah_barang_pengadaan_dengan_upload_foto(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $photo = UploadedFile::fake()->image('laptop_baru.jpg');

        $this->actingAs($this->admin)->post(route('items.store'), [
            'name' => 'Laptop Asus ROG Strix',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'specification' => 'Ryzen 9, 32GB RAM, RTX 4070',
            'acquisition_year' => 2025,
            'funding_source' => 'BOS Kinerja',
            'quantity' => 2,
            'unit' => 'unit',
            'min_stock' => 1,
            'is_high_value' => true,
            'location_code' => InstitutionSetting::current()->location_code,
            'photo' => $photo,
        ])->assertRedirect(route('items.index'))->assertSessionHasNoErrors();

        $item = Item::where('name', 'Laptop Asus ROG Strix')->firstOrFail();
        $this->assertSame('PENDING', $item->acquisition_status);
        $this->assertNotNull($item->photo_path);
        Storage::disk('public')->assertExists($item->photo_path);

        // Approval oleh Wakasek
        $this->actingAs($this->wakasek)->post(route('items.approve', $item))
            ->assertRedirect();
        $this->assertSame('APPROVED', $item->refresh()->acquisition_status);
    }

    public function test_unique_constraint_internal_id(): void
    {
        Item::create([
            'internal_id' => 'UNIQUE-ITEM-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '8801',
            'name' => 'Barang Unik 1',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $this->expectException(QueryException::class);

        Item::create([
            'internal_id' => 'UNIQUE-ITEM-01', // Duplikat internal_id
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '8802',
            'name' => 'Barang Duplikat ID',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_unique_constraint_asset_code_dan_register_no(): void
    {
        Item::create([
            'internal_id' => 'ASSET-ITEM-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.99',
            'register_no' => '9999',
            'name' => 'Barang Reg 1',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $this->expectException(QueryException::class);

        Item::create([
            'internal_id' => 'ASSET-ITEM-02',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.99',
            'register_no' => '9999', // Duplikat asset_code + register_no
            'name' => 'Barang Reg 2',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_performa_pencarian_katalog_kurang_dari_setengah_detik(): void
    {
        // Masukkan batch 100 item untuk verifikasi kecepatan query
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = [
                'internal_id' => 'PERF-'.$this->room->code.'-'.$i,
                'location_code' => '12.24.13.08.01.07.01.01',
                'asset_code' => '01.01.11.04.01',
                'register_no' => str_pad((string) (7000 + $i), 4, '0', STR_PAD_LEFT),
                'name' => 'Perangkat Batch Uji '.$i,
                'category_id' => $this->category->id,
                'room_id' => $this->room->id,
                'specification' => 'Spesifikasi batch uji performa',
                'acquisition_year' => 2024,
                'funding_source' => 'BOS',
                'quantity' => 1,
                'unit' => 'unit',
                'is_high_value' => false,
                'status' => 'BAIK',
                'acquisition_status' => 'APPROVED',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Item::insert($items);

        $this->actingAs($this->guru);

        // Pemanasan: kompilasi Blade & boot komponen Livewire tidak dihitung sebagai
        // waktu pencarian. Yang diukur adalah request pencarian seperti yang dialami pengguna.
        Livewire::test(ItemCatalog::class)->set('search', 'tidak-ada-hasil');

        $start = microtime(true);
        Livewire::test(ItemCatalog::class)
            ->set('search', 'Perangkat Batch Uji 50')
            ->assertSee('Perangkat Batch Uji 50');
        $duration = microtime(true) - $start;

        // Pencarian harus < 0.5 detik
        $this->assertLessThan(0.5, $duration, "Waktu pencarian katalog ({$duration}s) melebihi batas 0.5 detik");
    }
}

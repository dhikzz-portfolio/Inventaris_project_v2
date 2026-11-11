<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Category;
use App\Models\Item;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryCodeService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportImportAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    private function csv(array $rows, string $header = 'kode_inventaris,nama_barang,gedung,lantai,no_ruangan,kondisi,status'): UploadedFile
    {
        $lines = array_map(fn (array $row) => implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row)), $rows);

        return UploadedFile::fake()->createWithContent('laporan-unit.csv', $header."\n".implode("\n", $lines)."\n");
    }

    public function test_pratinjau_menampilkan_ringkasan_tanpa_mengubah_data(): void
    {
        $unit = AssetUnit::firstOrFail();
        $before = $unit->only(['condition', 'status']);

        $this->actingAs($this->wakasek)
            ->post(route('reports.import.preview'), [
                'file' => $this->csv([[$unit->inventory_code, $unit->catalog->name, '', '', '', 'Rusak Ringan', 'Aktif']]),
                'mode' => 'update',
            ])
            ->assertRedirect(route('reports.import.review'))
            ->assertSessionHas('report_import_preview');

        $preview = session('report_import_preview')['analysis'];
        $this->assertSame(1, $preview['summary']['total']);
        $this->assertTrue($preview['can_import']);

        // Pratinjau tidak mengubah database.
        $this->assertSame($before, $unit->refresh()->only(['condition', 'status']));

        $this->actingAs($this->wakasek)
            ->get(route('reports.import.review'))
            ->assertOk()
            ->assertSee('Pemeriksaan Berkas Impor')
            ->assertSee('laporan-unit.csv');
    }

    public function test_pratinjau_membersihkan_sel_yang_berisi_markup_html(): void
    {
        $room = Room::query()
            ->whereNotNull('room_no')
            ->where('room_no', '>', 0)
            ->where('floor', '>', 0)
            ->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[
                '',
                '<b>Tang</b> <span class="text-slate-100">buaya</span>',
                $room->buildingCode(),
                (int) $room->floor,
                (int) $room->room_no,
                'Baik',
                'Aktif',
            ]]),
            'mode' => 'create',
        ])->assertRedirect(route('reports.import.review'));

        $row = session('report_import_preview')['analysis']['rows'][0];

        $this->assertSame('Tang buaya', $row['name']);
        $this->assertSame($room->roomLabel(), $row['room_label']);
    }

    public function test_filter_hanya_baris_bermasalah_menyembunyikan_baris_valid(): void
    {
        $room = Room::query()
            ->whereNotNull('room_no')
            ->where('room_no', '>', 0)
            ->where('floor', '>', 0)
            ->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([
                ['', 'Barang Baik Sekali', $room->buildingCode(), (int) $room->floor, (int) $room->room_no, 'Baik', 'Aktif'],
                ['', 'Barang Bermasalah', $room->buildingCode(), (int) $room->floor, (int) $room->room_no, 'Rusak Parah', 'Aktif'],
            ]),
            'mode' => 'create',
        ])->assertRedirect(route('reports.import.review'));

        $this->assertSame(1, session('report_import_preview')['analysis']['summary']['invalid']);

        $this->actingAs($this->wakasek)->get(route('reports.import.review'))
            ->assertOk()
            ->assertSee('Barang Baik Sekali')
            ->assertSee('Barang Bermasalah');

        $this->actingAs($this->wakasek)->get(route('reports.import.review', ['filter' => 'problems']))
            ->assertOk()
            ->assertSee('Hanya bermasalah')
            ->assertDontSee('Barang Baik Sekali')
            ->assertSee('Barang Bermasalah');
    }

    public function test_halaman_impor_menampilkan_riwayat_impor_terakhir(): void
    {
        $unit = AssetUnit::query()->where('condition', Condition::BAIK->value)->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[$unit->inventory_code, $unit->catalog->name, '', '', '', 'Rusak Ringan', 'Aktif']]),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview');

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => $preview['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $this->actingAs($this->wakasek)->get(route('reports.import.form'))
            ->assertOk()
            ->assertSee('Laporan Impor')
            ->assertSee('laporan-unit.csv')
            ->assertSee($this->wakasek->name);
    }

    public function test_konfirmasi_impor_mode_perbarui_mengubah_unit_cocok(): void
    {
        $unit = AssetUnit::query()->where('condition', Condition::BAIK->value)->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[$unit->inventory_code, $unit->catalog->name, '', '', '', 'Rusak Berat', 'Hilang']]),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview');

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => $preview['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $unit->refresh();
        $this->assertSame(Condition::RUSAK_BERAT, $unit->condition);
        $this->assertSame('HILANG', $unit->status->value);
        $this->assertSame(1, AssetUnit::where('inventory_code', $unit->inventory_code)->count());
    }

    public function test_mode_perbarui_tidak_membuat_unit_baru(): void
    {
        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([['Z9-99-99', 'Tidak Ada', '', '', '', 'Baik', 'Aktif']]),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview');

        $this->assertFalse($preview['analysis']['can_import']);
        $this->assertSame(1, $preview['analysis']['summary']['invalid']);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => $preview['token'],
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('asset_units', ['inventory_code' => 'Z9-99-99']);
    }

    public function test_mode_buat_menolak_kode_yang_sudah_ada_dan_tidak_mengubah_unit(): void
    {
        $unit = AssetUnit::query()->where('condition', Condition::BAIK->value)->firstOrFail();
        $before = $unit->only(['condition', 'status']);

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[$unit->inventory_code, $unit->catalog->name, '', '', '', 'Rusak Berat', 'Hilang']]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview');

        $this->assertFalse($preview['analysis']['can_import']);
        $this->assertSame(1, $preview['analysis']['summary']['invalid']);
        $this->assertStringContainsString('sudah terdaftar', $preview['analysis']['rows'][0]['errors'][0]);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => $preview['token'],
        ])->assertSessionHasErrors('file');

        $this->assertSame($before, $unit->refresh()->only(['condition', 'status']));
    }

    public function test_mode_pemulihan_membuat_unit_baru_dengan_kode_dan_ruangan_valid(): void
    {
        $room = Room::where('is_lab', true)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();
        $code = app(InventoryCodeService::class)->preview($room, null, $catalog);

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[
                $code,
                $catalog->name,
                $room->buildingCode(),
                $room->floor,
                $room->room_no,
                'Baik',
                'Aktif',
            ]]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview');
        $this->assertTrue($preview['analysis']['can_import'], json_encode($preview['analysis']['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => $preview['token'],
        ])->assertRedirect(route('reports.index'));

        $this->assertDatabaseHas('asset_units', [
            'inventory_code' => $code,
            'room_id' => $room->id,
            'item_catalog_id' => $catalog->id,
        ]);
        $this->assertDatabaseHas('asset_unit_codes', ['inventory_code' => $code]);
    }

    public function test_mode_buat_membuat_kode_otomatis_saat_kode_dikosongkan(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([['', $catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $row = $preview['rows'][0];

        $this->assertTrue($preview['can_import'], json_encode($row));
        $this->assertTrue($row['auto_code']);
        $this->assertMatchesRegularExpression(InventoryCodeService::PATTERN, $row['code']);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('asset_units', [
            'inventory_code' => $row['code'],
            'room_id' => $room->id,
            'item_catalog_id' => $catalog->id,
        ]);
        $this->assertDatabaseHas('asset_unit_codes', [
            'inventory_code' => $row['code'],
            'item_type_code' => $catalog->item_type_code,
        ]);
    }

    public function test_mode_buat_menyimpan_merek_dan_tipe_unit_sebagai_spesifikasi_katalog(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();
        $code = app(InventoryCodeService::class)->preview($room, null, $catalog);

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$code, $catalog->name, 'Epson', 'EB-X51', $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']],
                'kode_inventaris,nama_barang,merek,tipe_model,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $unit = AssetUnit::where('inventory_code', $code)->firstOrFail();
        $this->assertNotNull($unit->specification_id);
        $this->assertSame('Epson', $unit->specification->brand);
        $this->assertSame('EB-X51', $unit->specification->model_type);

        // Merek per unit harus muncul di daftar merek halaman katalog.
        $this->actingAs($this->wakasek)
            ->get(route('catalogs.show', $catalog))
            ->assertOk()
            ->assertSee('Epson')
            ->assertSee('EB-X51');
    }

    public function test_mode_perbarui_melengkapi_spesifikasi_unit_lama_yang_kosong(): void
    {
        $unit = AssetUnit::query()->whereNotNull('specification_id')->firstOrFail();
        $unit->forceFill(['specification_id' => null])->save();

        $room = $unit->room;

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$unit->inventory_code, $unit->catalog->name, 'Merek Impor', 'Tipe Impor', $room->buildingCode(), $room->floor, $room->room_no, $unit->condition->label(), $unit->status->label()]],
                'kode_inventaris,nama_barang,merek,tipe_model,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $unit->refresh();
        $this->assertNotNull($unit->specification_id);
        $this->assertSame('Merek Impor', $unit->specification->brand);
        $this->assertSame('Tipe Impor', $unit->specification->model_type);
    }

    public function test_mode_buat_membuat_katalog_baru_saat_nama_barang_belum_terdaftar(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $name = 'Proyektor Impor Uji';
        $category = 'Elektronik Uji';

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [['', $name, $category, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']],
                'kode_inventaris,nama_barang,kategori,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $row = $preview['rows'][0];

        $this->assertTrue($preview['can_import'], json_encode($row));
        $this->assertTrue($row['auto_catalog']);
        $this->assertTrue($row['auto_code']);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $catalog = Item::where('name', $name)->firstOrFail();
        $this->assertSame($category, $catalog->category->name);
        $this->assertSame(1, $catalog->quantity);
        $this->assertNotNull($catalog->item_type_code);
        $this->assertSame($catalog->item_type_code, explode('-', $row['code'])[2]);

        $this->assertDatabaseHas('asset_units', [
            'inventory_code' => $row['code'],
            'item_catalog_id' => $catalog->id,
        ]);
    }

    public function test_mode_buat_mengisi_kategori_katalog_lama_yang_masih_tanpa_kategori(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();
        $placeholder = Category::firstOrCreate(['name' => 'Tanpa Kategori']);
        $catalog->forceFill(['category_id' => $placeholder->id])->save();

        $categoryName = 'Alat Masak Konvensional Uji';
        $this->assertDatabaseMissing('categories', ['name' => $categoryName]);

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [['', $catalog->name, $categoryName, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']],
                'kode_inventaris,nama_barang,kategori,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));
        $this->assertStringContainsString($categoryName, implode(' ', $preview['rows'][0]['warnings']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $this->assertSame($categoryName, $catalog->refresh()->category->name);
        $this->assertDatabaseHas('categories', ['name' => $categoryName]);
    }

    public function test_mode_perbarui_mengisi_kategori_katalog_dari_kolom_kategori(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $placeholder = Category::firstOrCreate(['name' => 'Tanpa Kategori']);
        $unit->catalog->forceFill(['category_id' => $placeholder->id])->save();

        $categoryName = 'Alat Masak Konvensional Uji';

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$unit->inventory_code, $unit->catalog->name, $categoryName, '', '', '', $unit->condition->label(), $unit->status->label()]],
                'kode_inventaris,nama_barang,kategori,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $this->assertSame($categoryName, $unit->catalog->refresh()->category->name);
        $this->assertDatabaseHas('categories', ['name' => $categoryName]);
    }

    public function test_mode_perbarui_tidak_menimpa_kategori_katalog_yang_sudah_terisi(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $originalCategory = $unit->catalog->category->name;
        $newCategory = 'Kategori Lain Uji';

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$unit->inventory_code, $unit->catalog->name, $newCategory, '', '', '', $unit->condition->label(), $unit->status->label()]],
                'kode_inventaris,nama_barang,kategori,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $this->assertSame($originalCategory, $unit->catalog->refresh()->category->name);
        $this->assertDatabaseMissing('categories', ['name' => $newCategory]);
    }

    public function test_mode_buat_memberi_nomor_unit_berurutan_untuk_beberapa_baris(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([
                ['', $catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif'],
                ['', $catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Rusak Ringan', 'Aktif'],
            ]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $first = (int) explode('-', $preview['rows'][0]['code'])[3];
        $second = (int) explode('-', $preview['rows'][1]['code'])[3];
        $this->assertSame($first + 1, $second);
        $this->assertNotSame($preview['rows'][0]['code'], $preview['rows'][1]['code']);
    }

    public function test_mode_buat_menerima_berkas_tanpa_kolom_kode_inventaris(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']],
                'nama_barang,gedung,lantai,no_ruangan,kondisi,status',
            ),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];

        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));
        $this->assertTrue($preview['rows'][0]['auto_code']);
        $this->assertMatchesRegularExpression(InventoryCodeService::PATTERN, $preview['rows'][0]['code']);
    }

    public function test_mode_buat_memakai_kode_jenis_sama_untuk_katalog_tanpa_kode_jenis(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();
        $catalog->forceFill(['item_type_code' => null])->save();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([
                ['', $catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif'],
                ['', $catalog->name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif'],
            ]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $firstType = explode('-', $preview['rows'][0]['code'])[2];
        $secondType = explode('-', $preview['rows'][1]['code'])[2];
        $this->assertSame($firstType, $secondType);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $this->assertSame($firstType, $catalog->refresh()->item_type_code);
    }

    public function test_mode_buat_menyesuaikan_kode_jenis_yang_bentrok_dengan_katalog_lain(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $existing = Item::approved()->whereNotNull('item_type_code')->firstOrFail();
        $name = 'Alat Ukur Bentrok Uji';
        $providedCode = app(InventoryCodeService::class)->preview($room, null, $existing);
        $oldTypeCode = $existing->item_type_code;
        $secondProvidedCode = app(InventoryCodeService::class)->format(
            $room,
            (int) AssetUnitCode::query()->where('room_id', $room->id)->max('unit_no') + 2,
            $oldTypeCode,
        );

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([
                [$providedCode, $name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif'],
                [$secondProvidedCode, $name, $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif'],
            ]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $row = $preview['rows'][0];
        $secondRow = $preview['rows'][1];

        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));
        $this->assertTrue($row['auto_code']);
        $this->assertTrue($secondRow['auto_code']);
        $this->assertNotSame($providedCode, $row['code']);
        $this->assertNotSame($secondProvidedCode, $secondRow['code']);
        $this->assertNotSame($oldTypeCode, explode('-', $row['code'])[2]);
        $this->assertSame(explode('-', $row['code'])[2], explode('-', $secondRow['code'])[2]);
        $this->assertStringContainsString('sudah dipakai', implode(' ', $row['warnings']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $catalog = Item::where('name', $name)->firstOrFail();

        $this->assertSame(explode('-', $row['code'])[2], $catalog->item_type_code);
        $this->assertSame($oldTypeCode, $existing->refresh()->item_type_code);
        $this->assertDatabaseHas('asset_units', [
            'inventory_code' => $row['code'],
            'item_catalog_id' => $catalog->id,
        ]);
        $this->assertDatabaseHas('asset_units', [
            'inventory_code' => $secondRow['code'],
            'item_catalog_id' => $catalog->id,
        ]);
    }

    public function test_mode_buat_menemukan_ruangan_legacy_berdasarkan_huruf_gedung(): void
    {
        $room = Room::create([
            'name' => 'Ruang Legacy',
            'code' => 'Z3-04',
            'building_letter' => 'Z',
            'floor' => 3,
            'room_no' => 4,
        ]);
        $catalog = Item::approved()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([['', $catalog->name, 'Z', 3, 4, 'Baik', 'Aktif']]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];

        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));
        $this->assertSame($room->id, $preview['rows'][0]['room_id']);
        $this->assertTrue($preview['rows'][0]['auto_code']);
    }

    public function test_mode_buat_menolak_baris_contoh_template(): void
    {
        $room = Room::where('is_lab', true)->where('floor', '>', 0)->where('room_no', '>', 0)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([['', $catalog->name.' (contoh)', $room->buildingCode(), $room->floor, $room->room_no, 'Baik', 'Aktif']]),
            'mode' => 'create',
        ]);

        $preview = session('report_import_preview')['analysis'];

        $this->assertFalse($preview['can_import']);
        $this->assertStringContainsString('Baris contoh template', $preview['rows'][0]['errors'][0]);
    }

    public function test_mode_perbarui_menolak_kode_inventaris_kosong(): void
    {
        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([['', 'Komputer', 'G', 1, 1, 'Baik', 'Aktif']]),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview')['analysis'];

        $this->assertFalse($preview['can_import']);
        $this->assertStringContainsString('Kode inventaris kosong', $preview['rows'][0]['errors'][0]);
    }

    public function test_contoh_file_impor_tersedia_dalam_format_excel(): void
    {
        $this->actingAs($this->wakasek)
            ->get(route('reports.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_mode_perbarui_menyimpan_kolom_keterangan_ke_catatan_unit(): void
    {
        $unit = AssetUnit::query()->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv(
                [[$unit->inventory_code, $unit->catalog->name, '', '', '', $unit->condition->label(), $unit->status->label(), 'Catatan ruang laboratorium']],
                'kode_inventaris,nama_barang,gedung,lantai,no_ruangan,kondisi,status,keterangan',
            ),
            'mode' => 'update',
        ]);

        $preview = session('report_import_preview')['analysis'];
        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $this->assertSame('Catatan ruang laboratorium', $unit->refresh()->notes);
    }

    public function test_mode_perbarui_tidak_mengosongkan_keterangan_bila_sel_kosong(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $unit->forceFill(['notes' => 'Keterangan lama'])->save();

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $this->csv([[$unit->inventory_code, $unit->catalog->name, '', '', '', $unit->condition->label(), $unit->status->label(), '']]),
            'mode' => 'update',
        ]);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'));

        $this->assertSame('Keterangan lama', $unit->refresh()->notes);
    }

    public function test_file_excel_laporan_bulanan_dapat_diimpor_kembali(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $unit->forceFill(['status' => 'DIPINJAM', 'notes' => 'Catatan ekspor uji'])->save();

        $data = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->wakasek);
        $this->assertTrue($data['units']->contains(
            fn (AssetUnit $row) => $row->inventory_code === $unit->inventory_code
        ));

        $unitTable = MonthlyReportExport::tables($data)[0];
        $notesIndex = array_search('Keterangan', $unitTable['headings'], true);
        $exportedRow = collect($unitTable['rows'])->first(fn (array $row) => $row[0] === $unit->inventory_code);

        $this->assertNotFalse($notesIndex);
        $this->assertSame('Catatan ekspor uji', $exportedRow[$notesIndex]);

        $binary = Excel::raw(new MonthlyReportExport($data), ExcelWriter::XLSX);

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('laporan-bulanan.xlsx', $binary),
            'mode' => 'update',
        ])->assertRedirect(route('reports.import.review'));

        $preview = session('report_import_preview')['analysis'];

        $this->assertTrue($preview['can_import'], json_encode($preview['rows']));
        $this->assertSame($data['units']->count(), $preview['summary']['total']);
        $this->assertSame($data['units']->count(), $preview['summary']['update']);

        $this->actingAs($this->wakasek)->post(route('reports.import.confirm'), [
            'token' => session('report_import_preview')['token'],
        ])->assertRedirect(route('reports.index'))->assertSessionHas('success');

        $this->assertSame('DIPINJAM', $unit->refresh()->status->value);
        $this->assertSame('Catatan ekspor uji', $unit->notes);
    }

    public function test_format_berkas_tidak_didukung_ditolak(): void
    {
        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
            'mode' => 'update',
        ])->assertSessionHasErrors('file');
    }

    public function test_penghapusan_arsip_laporan_soft_delete_dan_tidak_menghapus_unit(): void
    {
        Storage::fake('local');

        $unitCount = AssetUnit::count();

        Storage::disk('local')->put('reports/laporan-2026-10-semua-0.pdf', 'pdf');
        Storage::disk('local')->put('reports/laporan-2026-10-semua-0.xlsx', 'xlsx');

        $report = Report::create([
            'period' => '2026-10',
            'scope' => 'SEMUA',
            'scope_id' => null,
            'pdf_path' => 'reports/laporan-2026-10-semua-0.pdf',
            'excel_path' => 'reports/laporan-2026-10-semua-0.xlsx',
            'generated_by' => $this->wakasek->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($this->wakasek)
            ->delete(route('reports.destroy', $report))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('reports', ['id' => $report->id]);
        Storage::disk('local')->assertMissing('reports/laporan-2026-10-semua-0.pdf');
        Storage::disk('local')->assertMissing('reports/laporan-2026-10-semua-0.xlsx');
        $this->assertSame($unitCount, AssetUnit::count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'laporan',
            'description' => 'Menghapus arsip laporan 2026-10 (SEMUA)',
        ]);
    }

    public function test_ketua_laboran_tidak_dapat_menghapus_laporan_milik_pengguna_lain(): void
    {
        $ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();

        $report = Report::create([
            'period' => '2026-09',
            'scope' => 'SEMUA',
            'pdf_path' => null,
            'excel_path' => null,
            'generated_by' => $this->wakasek->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($ketua)
            ->delete(route('reports.destroy', $report))
            ->assertForbidden();
    }
}

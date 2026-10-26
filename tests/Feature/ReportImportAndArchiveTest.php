<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Services\InventoryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportImportAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
    }

    private function csv(array $rows): UploadedFile
    {
        $header = 'kode_inventaris,nama_barang,gedung,lantai,no_ruangan,kondisi,status';
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
            ->assertRedirect(route('reports.import.form'))
            ->assertSessionHas('report_import_preview');

        $preview = session('report_import_preview')['analysis'];
        $this->assertSame(1, $preview['summary']['total']);
        $this->assertTrue($preview['can_import']);

        // Pratinjau tidak mengubah database.
        $this->assertSame($before, $unit->refresh()->only(['condition', 'status']));

        $this->actingAs($this->wakasek)
            ->get(route('reports.import.form'))
            ->assertOk()
            ->assertSee('Pratinjau: laporan-unit.csv');
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

    public function test_mode_pemulihan_membuat_unit_baru_dengan_kode_dan_ruangan_valid(): void
    {
        $room = Room::where('is_lab', true)->firstOrFail();
        $catalog = Item::approved()->firstOrFail();
        $code = app(InventoryCodeService::class)->preview($room);

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
        $ketua = User::where('username', 'ketua.laboran')->firstOrFail();

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

<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\Building;
use App\Models\Category;
use App\Models\Event;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use App\Models\WriteOff;
use App\Models\WriteOffUnit;
use App\Services\AssetUnitService;
use App\Services\ReportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevisionV4Test extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketua;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
    }

    private function makeRoom(int $roomNo): Room
    {
        $building = Building::query()->firstOrFail();

        return Room::create([
            'building_id' => $building->id,
            'floor' => 8,
            'room_no' => $roomNo,
            'code' => sprintf('V8-%02d', $roomNo),
            'name' => 'Ruang Revisi '.$roomNo,
            'building_letter' => $building->code,
            'is_lab' => true,
        ]);
    }

    /**
     * @return array{0: Item, 1: array<int, AssetUnit>}
     */
    private function makeCatalog(string $name, Room $room, int $quantity, string $brand = 'Futura', string $model = 'Tipe A'): array
    {
        $catalog = Item::create([
            'internal_id' => 'V4'.Str::upper(Str::random(6)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(100000, 999999),
            'name' => $name,
            'brand' => $brand,
            'model_type' => $model,
            'category_id' => Category::query()->firstOrFail()->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $units = [];
        for ($i = 0; $i < $quantity; $i++) {
            $units[] = app(AssetUnitService::class)->create($catalog, $room);
        }

        return [$catalog, $units];
    }

    private function upload(string $name = 'berita-acara.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function unitWriteOff(array $unitIds, string $reason = 'Rusak berat'): void
    {
        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => $unitIds,
            'confirm_delete' => 1,
            'reason' => $reason,
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_report_reads_every_catalog_from_write_off_units_and_includes_legacy_rows(): void
    {
        Storage::fake('local');

        $roomA = $this->makeRoom(1);
        $roomB = $this->makeRoom(2);

        [$catalogA, $unitsA] = $this->makeCatalog('Kursi Laporan A', $roomA, 1, 'Futura', 'Tipe A');
        [$catalogB, $unitsB] = $this->makeCatalog('Meja Laporan B', $roomB, 1, 'Chitose', 'Tipe B');

        $this->unitWriteOff([$unitsA[0]->id, $unitsB[0]->id], 'Dua katalog dalam satu pengajuan');

        $writeOff = WriteOff::query()->latest('id')->firstOrFail();
        $this->assertSame('APPROVED', $writeOff->status);
        $this->assertSame(2, $writeOff->units()->count());
        $this->assertSame([$catalogA->id, $catalogB->id], $writeOff->units()->pluck('item_catalog_id')->sort()->values()->all());

        // Transaksi lama tanpa rincian unit tetap dihitung sekali.
        $legacy = WriteOff::create([
            'item_id' => $catalogA->id,
            'quantity' => 3,
            'mode' => 'CATALOG',
            'reason' => 'Data lama sebelum snapshot unit',
            'berita_acara_path' => 'write-offs/legacy.pdf',
            'status' => 'APPROVED',
            'requested_by' => $this->ketua->id,
            'approved_by' => $this->wakasek->id,
        ]);
        $legacy->forceFill(['approved_at' => now(), 'updated_at' => now()])->save();

        $data = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->wakasek);

        $this->assertSame(5, $data['summary']['penghapusan']);
        $this->assertSame(2, $data['summary']['penghapusan_transaksi']);
        $this->assertCount(3, $data['writeOffRows']);

        $codes = $data['writeOffRows']->pluck('code')->filter()->all();
        $this->assertContains($unitsA[0]->inventory_code, $codes);
        $this->assertContains($unitsB[0]->inventory_code, $codes);

        $catalogs = $data['writeOffRows']->pluck('catalog')->all();
        $this->assertContains('Kursi Laporan A', $catalogs);
        $this->assertContains('Meja Laporan B', $catalogs);

        $legacyRow = $data['writeOffRows']->firstWhere('legacy', true);
        $this->assertNotNull($legacyRow);
        $this->assertSame(3, $legacyRow->quantity);

        // PDF dan Excel memakai sumber baris yang sama.
        $html = view('pdf.report', $data)->render();
        $this->assertStringContainsString('Kursi Laporan A', $html);
        $this->assertStringContainsString('Meja Laporan B', $html);
        $this->assertStringContainsString('data lama tanpa rincian unit', $html);

        $sheets = (new MonthlyReportExport($data))->sheets();
        $sheet = $sheets[4];
        $this->assertContains('Kode Unit', $sheet->headings());
        $this->assertCount(3, $sheet->array());
    }

    public function test_report_scope_excludes_units_outside_lab_scope_in_a_mixed_write_off(): void
    {
        Storage::fake('local');

        $labRoom = $this->makeRoom(7);
        $generalRoom = $this->makeRoom(8);
        $generalRoom->update(['is_lab' => false]);

        [, $labUnits] = $this->makeCatalog('Kursi Lab Campuran', $labRoom, 1);
        [, $generalUnits] = $this->makeCatalog('Meja Umum Campuran', $generalRoom, 1);

        $this->unitWriteOff([$labUnits[0]->id, $generalUnits[0]->id], 'Transaksi lintas cakupan');

        $ketuaData = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->ketua);
        $ketuaCodes = $ketuaData['writeOffRows']->pluck('code')->filter()->all();

        $this->assertContains($labUnits[0]->inventory_code, $ketuaCodes);
        $this->assertNotContains($generalUnits[0]->inventory_code, $ketuaCodes);
        $this->assertSame(1, $ketuaData['summary']['penghapusan']);

        $superData = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->wakasek);
        $this->assertCount(2, $superData['writeOffRows']);
        $this->assertSame(2, $superData['summary']['penghapusan']);
    }

    public function test_one_unit_cannot_have_two_active_write_off_records(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom(3);
        [, $units] = $this->makeCatalog('Kursi Duplikat', $room, 1);
        $unit = $units[0];

        $first = WriteOff::create([
            'item_id' => $unit->item_catalog_id,
            'quantity' => 1,
            'mode' => 'UNIT',
            'reason' => 'Pertama',
            'berita_acara_path' => 'write-offs/first.pdf',
            'status' => 'PENDING',
            'requested_by' => $this->ketua->id,
        ]);

        WriteOffUnit::create([
            'write_off_id' => $first->id,
            'asset_unit_id' => $unit->id,
            'is_active' => 1,
            'item_catalog_id' => $unit->item_catalog_id,
            'specification_id' => $unit->specification_id,
            'inventory_code' => $unit->inventory_code,
            'room_id' => $unit->room_id,
            'status_before' => $unit->status->value,
        ]);

        // Endpoint menolak unit yang sudah masuk pengajuan aktif.
        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$unit->id],
            'confirm_delete' => 1,
            'reason' => 'Kedua',
            'berita_acara' => $this->upload(),
        ])->assertSessionHasErrors('unit_ids');

        $this->assertSame(1, WriteOff::count());
        $this->assertSame(1, WriteOffUnit::count());

        // Constraint database juga menolak duplikasi meski validasi dilewati.
        $second = WriteOff::create([
            'item_id' => $unit->item_catalog_id,
            'quantity' => 1,
            'mode' => 'UNIT',
            'reason' => 'Transaksi lain',
            'berita_acara_path' => 'write-offs/second.pdf',
            'status' => 'PENDING',
            'requested_by' => $this->ketua->id,
        ]);

        try {
            WriteOffUnit::create([
                'write_off_id' => $second->id,
                'asset_unit_id' => $unit->id,
                'is_active' => 1,
                'item_catalog_id' => $unit->item_catalog_id,
                'inventory_code' => $unit->inventory_code,
            ]);
            $this->fail('Unique (asset_unit_id, is_active) seharusnya menolak duplikasi.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
        }

        // Penolakan melepas kunci sehingga unit dapat diajukan kembali.
        $this->actingAs($this->wakasek)->post(route('write-offs.reject', $first), [
            'decision_note' => 'Perbaiki dokumen',
        ])->assertRedirect();

        $this->assertNull($first->units()->firstOrFail()->is_active);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$unit->id],
            'confirm_delete' => 1,
            'reason' => 'Pengajuan ulang',
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_stock_and_quantity_stay_consistent_between_unit_and_catalog_modes(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom(4);
        [$catalog, $units] = $this->makeCatalog('Lemari Stok', $room, 5);
        $this->assertSame(5, $catalog->refresh()->quantity);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$units[0]->id, $units[1]->id],
            'confirm_delete' => 1,
            'reason' => 'Sebagian rusak',
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $unitWriteOff = WriteOff::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $unitWriteOff->units()->count());
        $this->assertSame(2, $unitWriteOff->quantity);
        $this->assertSame(3, $catalog->refresh()->quantity);
        $this->assertSame(3, $catalog->units()->where('status', '!=', UnitStatus::DIHAPUS->value)->count());

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'CATALOG',
            'catalog_id' => $catalog->id,
            'confirm_delete' => 1,
            'confirm_catalog' => 1,
            'reason' => 'Sisa unit tidak layak',
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $catalogWriteOff = WriteOff::query()->latest('id')->firstOrFail();
        $this->assertSame(3, $catalogWriteOff->units()->count());
        $this->assertSame(3, $catalogWriteOff->quantity);
        $this->assertSame(0, $catalog->refresh()->quantity);
        $this->assertGreaterThanOrEqual(0, $catalog->quantity);
        $this->assertSame(ItemStatus::DIHAPUS, $catalog->status);
        $this->assertSame(5, AssetUnit::where('item_catalog_id', $catalog->id)->where('status', UnitStatus::DIHAPUS->value)->count());
        $this->assertSame(0, AssetUnit::where('item_catalog_id', $catalog->id)->where('status', '!=', UnitStatus::DIHAPUS->value)->count());
    }

    public function test_backfill_dry_run_writes_csv_without_changing_data_and_apply_is_idempotent(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom(5);
        $catalog = $this->makeCatalog('Katalog Backfill', $room, 0, 'Merek Netral', 'Tipe Netral')[0];
        Specification::resolveFor($catalog, 'Merek A', 'Tipe A');
        Specification::resolveFor($catalog, 'Merek B', 'Tipe B');
        $unit = app(AssetUnitService::class)->create($catalog, $room);
        $unit->forceFill(['specification_id' => null])->save();

        // Merek/tipe katalog tidak lagi menunjuk satu kandidat.
        $catalog->update(['brand' => 'Tanpa Kecocokan', 'model_type' => 'Tanpa Kecocokan']);

        Artisan::call('inventory:backfill-specifications', ['--report' => 'reports/dry.csv']);
        $this->assertNull($unit->refresh()->specification_id);

        $csv = Storage::disk('local')->get('reports/dry.csv');
        $this->assertStringContainsString('candidate_specification_id', $csv);
        $this->assertStringContainsString('TIDAK_DIPETAKAN', $csv);
        $this->assertStringContainsString('manual_check', $csv);

        $specification = Specification::resolveFor($catalog, 'Merek C', 'Tipe C');
        $catalog->update(['brand' => 'Merek C', 'model_type' => 'Tipe C']);

        Artisan::call('inventory:backfill-specifications', ['--apply' => true, '--report' => 'reports/apply.csv']);

        $this->assertSame($specification->id, $unit->refresh()->specification_id);
        $this->assertStringContainsString('DIPETAKAN', Storage::disk('local')->get('reports/apply.csv'));

        Artisan::call('inventory:backfill-specifications', ['--apply' => true, '--report' => 'reports/apply-again.csv']);
        $this->assertSame($specification->id, $unit->refresh()->specification_id);
        $this->assertStringContainsString('SUDAH_BENAR', Storage::disk('local')->get('reports/apply-again.csv'));
    }

    public function test_password_change_keeps_current_session_and_invalidates_old_session_hash(): void
    {
        $this->actingAs($this->siswa);

        $oldHash = $this->siswa->password;

        $this->put(route('password.update'), [
            'current_password' => 'siswa123',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(auth()->check());
        $this->get('/dashboard')->assertOk();

        // Sesi lain masih membawa hash password lama.
        $this->withSession([
            'password_hash_'.config('auth.defaults.guard', 'web') => $oldHash,
        ])->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_event_deletion_respects_ownership_admin_scope_and_loans(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan Otorisasi',
            'pic_name' => $this->guru->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_by' => $this->guru->id,
        ]);

        // Editor/Validator tidak memiliki permission hapus kegiatan.
        $this->actingAs($this->ketua)
            ->delete(route('events.destroy', $event))
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        // PIC bukan pemilik kegiatan.
        $event->update(['pic_user_id' => $this->ketua->id, 'pic_name' => $this->ketua->name]);

        $this->actingAs($this->ketua)
            ->delete(route('events.destroy', $event))
            ->assertForbidden();

        Loan::create([
            'borrower_id' => $this->siswa->id,
            'event_id' => $event->id,
            'purpose' => 'Kegiatan',
            'start_date' => now()->addDay(),
            'due_date' => now()->addDays(2),
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->wakasek)
            ->delete(route('events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_qr_payload_mismatch_and_archived_unit_use_database_as_source_of_truth(): void
    {
        $room = $this->makeRoom(6);
        [$catalog, $units] = $this->makeCatalog('Kursi QR Keamanan', $room, 1, 'Futura', 'Tipe Aman');
        $unit = $units[0];

        $this->actingAs($this->wakasek)
            ->get('/scan?code=KODE-PALSU&u='.$unit->id.'&r=2&s=999999')
            ->assertOk()
            ->assertSee('Kode pada QR')
            ->assertSee('Futura')
            ->assertSee('Tipe Aman');

        $unit->update([
            'status' => UnitStatus::DIHAPUS,
            'archived_at' => now(),
            'condition' => Condition::RUSAK_BERAT,
        ]);

        $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id.'&r=2')
            ->assertOk()
            ->assertSee('sudah diarsipkan')
            ->assertSee('Futura');
    }
}

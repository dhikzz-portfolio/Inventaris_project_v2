<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\User;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelAndQrTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->item = Item::query()
            ->whereHas('units', fn ($query) => $query->visibleTo($this->laboran))
            ->firstOrFail();
    }

    public function test_qr_data_url_tergenerate_sebagai_base64_png(): void
    {
        $service = app(LabelService::class);
        $dataUrl = $service->qrDataUrl($this->item);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUrl);
        $base64 = substr($dataUrl, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64);

        $this->assertNotEmpty($binary);
        // Signature PNG: 89 50 4E 47 0D 0A 1A 0A
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($binary, 0, 8));
    }

    public function test_split_location_code_memecah_kode_panjang_menjadi_dua_baris(): void
    {
        $service = app(LabelService::class);

        $this->assertSame(
            ['12.24.13.08', '01.07.01.01'],
            $service->splitLocationCode('12.24.13.08.01.07.01.01')
        );
        $this->assertSame(['12.24', '13.08'], $service->splitLocationCode('12.24.13.08'));
        $this->assertSame(['LOKASI-A'], $service->splitLocationCode('LOKASI-A'));
    }

    public function test_cetak_label_satuan_menghasilkan_pdf(): void
    {
        $response = $this->actingAs($this->laboran)
            ->get(route('items.label', $this->item));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Pastikan last_label_printed_at tercatat
        $this->assertNotNull($this->item->refresh()->last_label_printed_at);
    }

    public function test_cetak_label_massal_per_unit_menghasilkan_pdf(): void
    {
        $units = AssetUnit::query()
            ->visibleTo($this->laboran)
            ->limit(3)
            ->pluck('id')
            ->all();

        $this->assertCount(3, $units);

        $response = $this->actingAs($this->laboran)
            ->post(route('labels.print'), [
                'unit_ids' => $units,
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Setiap unit yang dicetak mencatat waktu cetak terakhirnya sendiri.
        foreach ($units as $unitId) {
            $this->assertNotNull(AssetUnit::find($unitId)->last_label_printed_at);
        }
    }

    public function test_label_massal_tidak_mencetak_unit_di_luar_cakupan_laboran(): void
    {
        $inScope = AssetUnit::query()->visibleTo($this->laboran)->firstOrFail();
        $outOfScopeIds = AssetUnit::query()
            ->whereDoesntHave('room', fn ($query) => $query->whereIn('id', $this->laboran->roomScopes()->select('room_id')))
            ->limit(2)
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($outOfScopeIds);

        $this->actingAs($this->laboran)
            ->post(route('labels.print'), [
                'unit_ids' => array_merge([$inScope->id], $outOfScopeIds),
            ])
            ->assertOk();

        $this->assertNotNull($inScope->refresh()->last_label_printed_at);

        foreach ($outOfScopeIds as $unitId) {
            $this->assertNull(AssetUnit::find($unitId)->last_label_printed_at);
        }
    }

    public function test_cetak_massal_dengan_select_all_hanya_mencetak_unit_sesuai_filter_dan_cakupan(): void
    {
        $this->actingAs($this->laboran)
            ->post(route('labels.print'), ['select_all' => 1])
            ->assertOk();

        $scoped = AssetUnit::query()->visibleTo($this->laboran)->get();
        $this->assertTrue($scoped->every(fn ($unit) => $unit->last_label_printed_at !== null));

        $outside = AssetUnit::query()
            ->whereDoesntHave('room', fn ($query) => $query->whereIn('id', $this->laboran->roomScopes()->select('room_id')))
            ->get();
        $this->assertNotEmpty($outside);
        $this->assertTrue($outside->every(fn ($unit) => $unit->last_label_printed_at === null));
    }

    public function test_halaman_cetak_label_membatasi_daftar_unit_sesuai_cakupan(): void
    {
        $response = $this->actingAs($this->laboran)->get(route('labels.index'));

        $response->assertOk();

        foreach (AssetUnit::query()->visibleTo($this->laboran)->limit(5)->get() as $unit) {
            $response->assertSee($unit->inventory_code);
        }

        $outsideCode = AssetUnit::query()
            ->whereDoesntHave('room', fn ($query) => $query->whereIn('id', $this->laboran->roomScopes()->select('room_id')))
            ->value('inventory_code');

        $this->assertNotNull($outsideCode);
        $response->assertDontSee($outsideCode);
    }

    public function test_pratinjau_label_di_detail_barang_mencerminkan_label_cetak(): void
    {
        $service = app(LabelService::class);
        $dataUrl = $service->qrDataUrl($this->item);

        $response = $this->actingAs($this->laboran)->get(route('items.show', $this->item));
        $response->assertOk();

        // Kode lokasi ditampilkan terpecah dua baris, persis seperti template pdf.label_single.
        foreach ($service->splitLocationCode((string) $this->item->location_code) as $line) {
            $response->assertSee($line, false);
        }
        // Nama barang sebagai header label, QR, dan footer nama instansi ikut tampil.
        $response->assertSee($this->item->name, false);
        $response->assertSee(substr($dataUrl, 0, 60), false);
        $response->assertSee(InstitutionSetting::current()->institution_name, false);
    }

    public function test_scan_menampilkan_hasil_pada_halaman_yang_sama(): void
    {
        $unit = AssetUnit::where('item_catalog_id', $this->item->id)->firstOrFail();

        // QR modern membawa kode inventaris + ID unit stabil (param u).
        $response = $this->actingAs($this->laboran)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id);

        // Tidak dialihkan ke katalog/detail: hasil tampil di halaman scan.
        $response->assertOk();
        $response->assertSee($unit->inventory_code);
        $response->assertSee($unit->catalog->name);

        // Stiker lama berisi ID internal katalog tetap terbaca di halaman scan.
        $this->actingAs($this->laboran)
            ->get(route('scan.resolve', ['internalId' => $this->item->internal_id]))
            ->assertOk()
            ->assertSee($this->item->name, false);
    }

    public function test_scan_kode_tidak_dikenal_menampilkan_pesan_error(): void
    {
        $response = $this->actingAs($this->laboran)
            ->get(route('scan.resolve', ['internalId' => 'Z9-99-99']));

        $response->assertOk();
        $response->assertSee('tidak ditemukan', false);
    }
}

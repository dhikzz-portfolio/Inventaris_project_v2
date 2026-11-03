<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $ketua;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_cakupan_laporan_mengikuti_role(): void
    {
        $service = app(ReportService::class);
        $period = now()->format('Y-m');

        $laboranData = $service->data($period, 'SEMUA', null, $this->laboran);
        $scopeIds = $this->laboran->roomScopes()->pluck('room_id')->all();

        $this->assertCount(0, $laboranData['units']->reject(
            fn (AssetUnit $unit) => in_array($unit->room_id, $scopeIds, true)
        ));

        $ketuaData = $service->data($period, 'SEMUA', null, $this->ketua);
        $this->assertTrue($ketuaData['units']->every(fn (AssetUnit $unit) => (bool) $unit->room?->is_lab));

        $wakasekData = $service->data($period, 'SEMUA', null, $this->wakasek);
        $this->assertGreaterThanOrEqual($ketuaData['units']->count(), $wakasekData['units']->count());
        $this->assertTrue($wakasekData['units']->contains(fn (AssetUnit $unit) => ! $unit->room?->is_lab));
    }

    public function test_filter_ruangan_tidak_dapat_menembus_cakupan_laboran(): void
    {
        $otherLab = Room::where('is_lab', true)
            ->whereNotIn('id', $this->laboran->roomScopes()->select('room_id'))
            ->firstOrFail();

        $data = app(ReportService::class)->data(now()->format('Y-m'), 'RUANGAN', $otherLab->id, $this->laboran);

        $this->assertCount(0, $data['units']);
        $this->assertCount(0, $data['movements']);
    }

    public function test_laporan_memuat_pengadaan_dan_laporan_kerusakan(): void
    {
        // Pengadaan yang sudah tercatat pada periode ini.
        $catalog = Item::query()->approved()->firstOrFail();
        $catalog->forceFill(['acquisition_received_at' => now()])->save();

        $unit = AssetUnit::where('item_catalog_id', $catalog->id)->firstOrFail();

        DamageReport::create([
            'asset_unit_id' => $unit->id,
            'reported_by' => $this->laboran->id,
            'classification' => 'RUSAK_RINGAN',
            'previous_condition' => 'BAIK',
            'reported_condition' => 'RUSAK_RINGAN',
            'description' => 'Uji laporan kerusakan pada laporan bulanan.',
            'status' => 'DILAPORKAN',
        ]);

        $data = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->wakasek);

        $this->assertArrayHasKey('acquisitionRows', $data);
        $this->assertArrayHasKey('damageReportRows', $data);
        $this->assertArrayHasKey('laporan_kerusakan', $data['summary']);
        $this->assertArrayHasKey('pengadaan', $data['summary']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['laporan_kerusakan']);
        $this->assertSame(1, $data['summary']['laporan_kerusakan_ringan']);
        $this->assertContains($unit->inventory_code, $data['damageReportRows']->map(fn ($row) => $row->unit?->inventory_code)->all());
    }

    public function test_laboran_dapat_membuka_halaman_laporan_tetapi_tidak_dapat_menyimpan_arsip(): void
    {
        $this->actingAs($this->laboran)->get(route('reports.index'))->assertOk();
        $this->actingAs($this->laboran)->post(route('reports.generate'), [
            'period' => now()->format('Y-m'),
            'scope' => 'SEMUA',
        ])->assertForbidden();
    }
}

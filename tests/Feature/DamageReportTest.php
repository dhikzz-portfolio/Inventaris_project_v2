<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Models\AssetUnit;
use App\Models\DamageReport;
use App\Models\DamageReportLog;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $laboranRpl;

    private User $laboranDkv;

    private User $ketua;

    private AssetUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboranRpl = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->laboranDkv = User::where('email', 'laboran.dkv@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();

        $roomIds = $this->laboranRpl->roomScopes()->pluck('room_id');

        $this->unit = AssetUnit::query()
            ->whereIn('room_id', $roomIds)
            ->where('condition', Condition::BAIK->value)
            ->firstOrFail();
    }

    private function report(string $classification = 'RUSAK_RINGAN'): DamageReport
    {
        $this->actingAs($this->laboranRpl)->post(route('damage-reports.store'), [
            'asset_unit_id' => $this->unit->id,
            'classification' => $classification,
            'description' => 'Layar pecah dan tombol tidak berfungsi.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return DamageReport::latest('id')->firstOrFail();
    }

    public function test_laboran_melaporkan_rusak_ringan_tanpa_menghapus_unit(): void
    {
        $unitCount = AssetUnit::count();
        $writeOffCount = WriteOff::count();
        $roomId = $this->unit->room_id;
        $previous = $this->unit->condition;

        $report = $this->report();

        $this->assertSame('RUSAK_RINGAN', $report->classification->value);
        $this->assertSame('DILAPORKAN', $report->status->value);
        $this->assertSame($previous, $report->previous_condition);
        $this->assertSame($this->laboranRpl->id, $report->reported_by);
        $this->assertSame($this->unit->id, $report->asset_unit_id);

        $this->assertSame($unitCount, AssetUnit::count());
        $this->assertSame($writeOffCount, WriteOff::count());
        $this->assertSame($roomId, $this->unit->refresh()->room_id);
        $this->assertSame(Condition::RUSAK_RINGAN, $this->unit->condition);

        $this->assertDatabaseHas('damage_report_logs', [
            'damage_report_id' => $report->id,
            'action' => 'Membuat laporan kerusakan',
            'status_after' => 'DILAPORKAN',
        ]);
        $this->assertTrue(DamageReportLog::where('damage_report_id', $report->id)->exists());
    }

    public function test_rusak_berat_dapat_dipantau_ketua_dan_dibatasi_lintas_jurusan(): void
    {
        $report = $this->report('RUSAK_BERAT');

        $this->assertSame(Condition::RUSAK_BERAT, $this->unit->refresh()->condition);

        $this->actingAs($this->ketua)
            ->get(route('damage-reports.index'))
            ->assertOk()
            ->assertSee($this->unit->inventory_code);

        $this->actingAs($this->ketua)
            ->get(route('damage-reports.show', $report))
            ->assertOk()
            ->assertSee('Rusak Berat');

        $this->actingAs($this->laboranDkv)
            ->get(route('damage-reports.show', $report))
            ->assertNotFound();

        $this->actingAs($this->laboranDkv)
            ->get(route('damage-reports.index'))
            ->assertOk()
            ->assertDontSee($this->unit->inventory_code);
    }

    public function test_laporan_ganda_untuk_unit_yang_sama_ditolak(): void
    {
        $this->report();

        $this->actingAs($this->laboranRpl)->post(route('damage-reports.store'), [
            'asset_unit_id' => $this->unit->id,
            'classification' => 'RUSAK_RINGAN',
            'description' => 'Laporan kedua untuk unit yang sama.',
        ])->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(1, DamageReport::where('asset_unit_id', $this->unit->id)->count());
    }

    public function test_status_penanganan_hanya_bertransisi_sesuai_aturan(): void
    {
        $report = $this->report();

        $this->actingAs($this->laboranRpl)->patch(route('damage-reports.update', $report), [
            'status' => 'DIPERIKSA',
            'note' => 'Sudah diperiksa teknisi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('DIPERIKSA', $report->refresh()->status->value);
        $this->assertSame($this->laboranRpl->id, $report->handled_by);

        // SELESAI bersifat final; transisi lain harus ditolak.
        $this->actingAs($this->laboranRpl)->patch(route('damage-reports.update', $report), [
            'status' => 'SELESAI',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->laboranRpl)->patch(route('damage-reports.update', $report), [
            'status' => 'DALAM_PERBAIKAN',
        ])->assertSessionHasErrors('status');

        $this->assertSame('SELESAI', $report->refresh()->status->value);
    }

    public function test_unit_di_luar_cakupan_tidak_dapat_dilaporkan(): void
    {
        $otherRoom = Room::where('is_lab', true)
            ->whereNotIn('id', $this->laboranRpl->roomScopes()->select('room_id'))
            ->firstOrFail();

        $otherUnit = AssetUnit::where('room_id', $otherRoom->id)->firstOrFail();

        $this->actingAs($this->laboranRpl)->post(route('damage-reports.store'), [
            'asset_unit_id' => $otherUnit->id,
            'classification' => 'RUSAK_RINGAN',
            'description' => 'Upaya melaporkan unit lab lain.',
        ])->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, DamageReport::where('asset_unit_id', $otherUnit->id)->count());
    }

    public function test_laporan_kerusakan_mengirim_notifikasi_hanya_ke_pihak_berwenang(): void
    {
        $this->report('RUSAK_BERAT');

        $wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();

        $this->assertTrue($this->ketua->notificationsApp()->where('type', 'KERUSAKAN')->exists());
        $this->assertTrue($wakasek->notificationsApp()->where('type', 'KERUSAKAN')->exists());

        // Laboran lain dan pelapor tidak menerima notifikasi lintas lingkup.
        $this->assertFalse($this->laboranDkv->notificationsApp()->where('type', 'KERUSAKAN')->exists());
        $this->assertFalse($this->laboranRpl->notificationsApp()->where('type', 'KERUSAKAN')->exists());
    }
}

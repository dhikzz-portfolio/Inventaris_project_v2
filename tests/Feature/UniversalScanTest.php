<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\User;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalScanTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketua;

    private User $laboran;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->laboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
    }

    public function test_semua_role_yang_login_dapat_membuka_halaman_scan(): void
    {
        foreach ([$this->wakasek, $this->ketua, $this->laboran, $this->siswa] as $user) {
            $this->actingAs($user)->get(route('scan'))->assertOk();
        }
    }

    public function test_scan_kode_menampilkan_hasil_di_halaman_scan_tanpa_redirect(): void
    {
        $unit = AssetUnit::query()->with('catalog')->firstOrFail();

        $response = $this->actingAs($this->wakasek)
            ->get(route('scan', ['code' => strtolower($unit->inventory_code)]));

        $response->assertOk();
        $response->assertSee($unit->inventory_code);
        $response->assertSee($unit->catalog->name);
        $response->assertSee('Kode Inventaris');
    }

    public function test_kode_tidak_valid_menampilkan_pesan_format(): void
    {
        $this->actingAs($this->wakasek)
            ->get(route('scan', ['code' => 'BUKAN-KODE']))
            ->assertOk()
            ->assertSee('Format kode tidak valid', false);
    }

    public function test_peminjam_tidak_melihat_informasi_internal_unit(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $unit->update(['acquisition_value' => 1234567, 'notes' => 'CATATAN-RAHASIA-INTERNAL']);

        $response = $this->actingAs($this->siswa)
            ->get(route('scan', ['code' => $unit->inventory_code]));

        $response->assertOk();
        $response->assertSee($unit->inventory_code);
        $response->assertDontSee('CATATAN-RAHASIA-INTERNAL');
        $response->assertDontSee('1.234.567');
    }

    public function test_pengelola_melihat_informasi_internal_sesuai_kewenangan(): void
    {
        $unit = AssetUnit::query()
            ->visibleTo($this->ketua)
            ->firstOrFail();
        $unit->update(['acquisition_value' => 1234567, 'notes' => 'CATATAN-INTERNAL-LAB']);

        $this->actingAs($this->ketua)
            ->get(route('scan', ['code' => $unit->inventory_code]))
            ->assertOk()
            ->assertSee('CATATAN-INTERNAL-LAB')
            ->assertSee('1.234.567');
    }

    public function test_unit_di_luar_cakupan_tidak_membocorkan_detail(): void
    {
        $outOfScope = AssetUnit::query()
            ->whereDoesntHave('room', fn ($query) => $query->whereIn('id', $this->laboran->roomScopes()->select('room_id')))
            ->with('catalog')
            ->firstOrFail();

        $response = $this->actingAs($this->laboran)
            ->get(route('scan', ['code' => $outOfScope->inventory_code]));

        $response->assertOk()
            ->assertSee('di luar cakupan', false)
            ->assertDontSee($outOfScope->catalog->specification ?: '###tidak-ada###', false);
    }

    public function test_scan_tidak_mengubah_data_inventaris(): void
    {
        $unit = AssetUnit::query()->firstOrFail();
        $before = [
            'status' => $unit->status->value,
            'condition' => $unit->condition->value,
            'room_id' => $unit->room_id,
            'inventory_code' => $unit->inventory_code,
            'updated_at' => $unit->updated_at?->toDateTimeString(),
        ];

        $this->actingAs($this->wakasek)->get(route('scan', ['code' => $unit->inventory_code]))->assertOk();

        $unit->refresh();

        $this->assertSame($before, [
            'status' => $unit->status->value,
            'condition' => $unit->condition->value,
            'room_id' => $unit->room_id,
            'inventory_code' => $unit->inventory_code,
            'updated_at' => $unit->updated_at?->toDateTimeString(),
        ]);
    }

    public function test_payload_qr_memuat_release_id_stabil_dan_kode_unit(): void
    {
        $unit = AssetUnit::query()->with('room.building')->firstOrFail();
        $payload = app(LabelService::class)->unitQrPayload($unit);

        $query = [];
        parse_str((string) parse_url($payload, PHP_URL_QUERY), $query);

        $this->assertStringContainsString(route('scan'), $payload);
        $this->assertSame((string) LabelService::QR_RELEASE, (string) $query['r']);
        $this->assertSame((string) $unit->id, (string) $query['u']);
        $this->assertSame($unit->inventory_code, $query['c']);
        $this->assertSame((string) $unit->unit_no, (string) $query['un']);
        $this->assertArrayNotHasKey('password', $query);
        $this->assertArrayNotHasKey('token', $query);

        // Metadata lokasi pada QR (§14) — sumber kebenaran tetap database.
        $this->assertNotNull($unit->room);
        $this->assertSame((string) ($unit->room->building?->name ?? ''), (string) $query['b']);
        $this->assertSame((string) $unit->room->floor, (string) $query['f']);
        $this->assertSame((string) $unit->room->room_no, (string) $query['rn']);
    }

    public function test_stiker_id_internal_katalog_lama_mengarah_ke_katalog_tanpa_redirect(): void
    {
        // Stiker lama memuat ID internal katalog yang bukan format kode unit.
        $catalog = Item::query()->firstOrFail();
        $catalog->update(['internal_id' => 'INV-LAMA-001']);

        $response = $this->actingAs($this->wakasek)
            ->get(route('scan.resolve', ['internalId' => 'INV-LAMA-001']));

        // Hasil tampil di halaman scan, bukan pengalihan paksa ke katalog.
        $response->assertOk();
        $response->assertSee('ID internal katalog lama', false);
        $response->assertSee($catalog->name, false);
        $response->assertSee(route('items.show', $catalog), false);
    }
}

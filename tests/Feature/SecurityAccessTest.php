<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_foto_bukti_kerusakan_hanya_bisa_diakses_petugas_berwenang(): void
    {
        Storage::fake('local');
        $this->seed();

        $laboranTkj = User::where('username', 'laboran.tp')->firstOrFail();
        $laboranRpl = User::where('username', 'laboran.rpl')->firstOrFail();
        $guru = User::where('username', 'guru.budi')->firstOrFail();
        $wakasek = User::where('username', 'wakasek')->firstOrFail();

        $room = Room::where('major_id', Major::where('code', 'TP')->value('id'))->firstOrFail();
        $category = Category::firstOrFail();

        $item = Item::create([
            'internal_id' => 'SEC-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '7777',
            'name' => 'Alat Uji Keamanan',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $service = app(LoanService::class);
        $loan = $service->requestLoan($guru, [['item_id' => $item->id, 'qty' => 1]], 'Uji akses file', '2027-09-01 08:00', '2027-09-02 16:00');
        $service->decide($loan, $laboranTkj, Decision::APPROVED);
        $service->handover($loan->refresh(), $laboranTkj);
        $loanItem = $loan->loanItems()->firstOrFail();

        $service->processReturn($loan->refresh(), $laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $room->id,
            'damage_photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]]);

        $path = $loanItem->refresh()->damage_photo_path;
        $this->assertNotNull($path);

        // Laboran jurusan lain & peminjam tidak berhak.
        $this->actingAs($laboranRpl)->get(route('files.show', ['path' => $path]))->assertForbidden();
        $this->actingAs($guru)->get(route('files.show', ['path' => $path]))->assertForbidden();

        // Petugas dalam cakupan dan pengelola berhak.
        $this->actingAs($laboranTkj)->get(route('files.show', ['path' => $path]))->assertOk();
        $this->actingAs($wakasek)->get(route('files.show', ['path' => $path]))->assertOk();

        // Path traversal tidak diizinkan.
        $this->actingAs($wakasek)->get(route('files.show', ['path' => '../../.env']))->assertNotFound();
    }

    public function test_laboran_tidak_dapat_mengakses_laporan_seluruh_sekolah(): void
    {
        $this->seed();
        $laboran = User::where('username', 'laboran.tp')->firstOrFail();

        $this->actingAs($laboran)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($laboran)->get(route('reports.export', ['type' => 'pdf', 'period' => now()->format('Y-m')]))->assertForbidden();
    }

    public function test_katalog_di_luar_cakupan_tidak_dapat_dibuka_lewat_url_legacy(): void
    {
        $this->seed();
        $admin = User::where('username', 'ketua.laboran')->firstOrFail();

        $nonLabItem = Item::where('name', 'Proyektor Epson')->firstOrFail();
        $labItem = Item::where('name', 'Komputer PC')->firstOrFail();

        $this->actingAs($admin)->get(route('items.show', $nonLabItem))->assertNotFound();
        $this->actingAs($admin)->get(route('items.show', $labItem))->assertOk();
    }

    public function test_ketua_laboran_tidak_dapat_mengajukan_pengadaan_ke_ruang_non_lab(): void
    {
        $this->seed();
        $admin = User::where('username', 'ketua.laboran')->firstOrFail();
        $nonLabRoom = Room::where('is_lab', false)->firstOrFail();

        $this->actingAs($admin)->get(route('items.create'))
            ->assertOk()
            ->assertDontSee($nonLabRoom->name);

        $this->actingAs($admin)->post(route('items.store'), [
            'name' => 'Barang Umum Sekolah',
            'category_id' => Category::firstOrFail()->id,
            'room_id' => $nonLabRoom->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'location_code' => '12.24.13.08.01.07.01.01',
        ])->assertSessionHasErrors('room_id');

        $this->assertDatabaseMissing('item_catalogs', ['name' => 'Barang Umum Sekolah']);
    }

    public function test_peminjam_tidak_melihat_nilai_perolehan_catatan_dan_riwayat_mutasi_unit(): void
    {
        $this->seed();
        $guru = User::where('username', 'guru.budi')->firstOrFail();
        $admin = User::where('username', 'ketua.laboran')->firstOrFail();

        $unit = AssetUnit::query()
            ->whereHas('room', fn ($query) => $query->where('is_lab', true))
            ->firstOrFail();
        $unit->update(['acquisition_value' => 1234567, 'notes' => 'Catatan internal unit']);

        $this->actingAs($guru)->get(route('units.show', $unit))
            ->assertOk()
            ->assertDontSee('1.234.567')
            ->assertDontSee('Catatan internal unit')
            ->assertDontSee('Riwayat Perpindahan')
            ->assertDontSee('Riwayat Kode');

        $this->actingAs($admin)->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee('1.234.567')
            ->assertSee('Catatan internal unit')
            ->assertSee('Riwayat Perpindahan');
    }

    public function test_manajer_dapat_mengekspor_laporan_pdf_dan_excel(): void
    {
        $this->seed();
        $admin = User::where('username', 'ketua.laboran')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'pdf', 'period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'excel', 'period' => now()->format('Y-m')]))
            ->assertOk();
    }
}

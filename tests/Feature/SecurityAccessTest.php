<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
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

        $laboranTkj = User::where('username', 'laboran.tkj')->firstOrFail();
        $laboranRpl = User::where('username', 'laboran.rpl')->firstOrFail();
        $guru = User::where('username', 'guru.budi')->firstOrFail();
        $wakasek = User::where('username', 'wakasek')->firstOrFail();

        $room = Room::where('major_id', Major::where('code', 'TKJ')->value('id'))->firstOrFail();
        $category = Category::firstOrFail();

        $item = Item::create([
            'internal_id' => 'SEC-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
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
        $laboran = User::where('username', 'laboran.tkj')->firstOrFail();

        $this->actingAs($laboran)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($laboran)->get(route('reports.export', ['type' => 'pdf', 'period' => now()->format('Y-m')]))->assertForbidden();
    }

    public function test_manajer_dapat_mengekspor_laporan_pdf_dan_excel(): void
    {
        $this->seed();
        $admin = User::where('username', 'admin.sarpras')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'pdf', 'period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'excel', 'period' => now()->format('Y-m')]))
            ->assertOk();
    }
}

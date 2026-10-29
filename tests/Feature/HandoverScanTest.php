<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItemUnit;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QR pada serah terima hanya memasukkan unit ke daftar pilihan setelah
 * diverifikasi terhadap database, transaksi, kelayakan, dan batas jumlah.
 */
class HandoverScanTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $guru;

    private Room $room;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->room = Room::where('major_id', Major::where('code', 'TP')->value('id'))->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    /** @return array{0: Item, 1: Collection<int, AssetUnit>} */
    private function makeCatalog(string $name, int $qty): array
    {
        $catalog = Item::create([
            'internal_id' => 'SCAN-'.Str::upper(Str::random(8)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(7000, 9999),
            'name' => $name,
            'specification' => 'Unit uji scan serah terima',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'is_high_value' => false,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $service = app(AssetUnitService::class);
        $units = collect();

        for ($index = 0; $index < $qty; $index++) {
            $units->push($service->create($catalog, $this->room, [], $this->laboran));
        }

        return [$catalog->refresh(), $units];
    }

    private function approvedLoan(Item $catalog, int $qty): Loan
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guru,
            [['item_id' => $catalog->id, 'qty' => $qty]],
            'Uji scan QR serah terima',
            '2027-10-01 08:00',
            '2027-10-03 16:00'
        );

        $service->decide($loan, $this->laboran, Decision::APPROVED);

        return $loan->refresh();
    }

    public function test_halaman_serah_terima_menyediakan_pilih_semua_filter_dan_scan(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Halaman Serah Terima', 3);
        $loan = $this->approvedLoan($catalog, 2);

        $this->actingAs($this->laboran)
            ->get(route('loans.handover-form', $loan))
            ->assertOk()
            ->assertSee('Pilih Semua Unit yang Dapat Diserahkan')
            ->assertSee('Batal Pilih Semua')
            ->assertSee('Scan QR Unit')
            ->assertSee('data-unit-card', false)
            ->assertSee('Serahkan Barang + Tandai Unit Dipinjam')
            ->assertSee($units->first()->inventory_code)
            ->assertSee('Cari unit');
    }

    public function test_qr_unit_valid_ditambahkan_tanpa_menyerahkan_barang(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Scan Valid', 3);
        $loan = $this->approvedLoan($catalog, 2);
        $unit = $units->first();

        $response = $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', ['loan' => $loan, 'code' => $unit->inventory_code]));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('unit.id', $unit->id)
            ->assertJsonPath('unit.inventory_code', $unit->inventory_code);

        // Scan tidak boleh mengubah status transaksi atau menugaskan unit.
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertNull($loan->handed_over_at);
        $this->assertSame(0, LoanItemUnit::whereHas('loanItem', fn ($query) => $query->where('loan_id', $loan->id))->count());
    }

    public function test_qr_unit_yang_sudah_dipilih_tidak_ditambahkan_dua_kali(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Scan Duplikat', 3);
        $loan = $this->approvedLoan($catalog, 2);
        $unit = $units->first();

        $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', [
                'loan' => $loan,
                'code' => $unit->inventory_code,
                'selected' => [$unit->id],
            ]))
            ->assertStatus(409)
            ->assertJsonPath('status', 'duplicate');
    }

    public function test_qr_unit_di_luar_peminjaman_ditolak(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Transaksi Ini', 2);
        [$otherCatalog, $otherUnits] = $this->makeCatalog('Meja Transaksi Lain', 2);
        $loan = $this->approvedLoan($catalog, 1);

        $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', [
                'loan' => $loan,
                'code' => $otherUnits->first()->inventory_code,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'not_in_loan');

        $this->assertSame(0, LoanItemUnit::count());
        $this->assertSame(UnitStatus::AKTIF, $otherUnits->first()->refresh()->status);
    }

    public function test_qr_unit_rusak_tidak_memenuhi_syarat_penyerahan(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Rusak', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $units->first()->forceFill(['condition' => Condition::RUSAK_RINGAN])->save();

        $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', [
                'loan' => $loan,
                'code' => $units->first()->inventory_code,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'ineligible');
    }

    public function test_qr_menghormati_batas_jumlah_penyerahan_per_baris(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Batas', 4);
        $loan = $this->approvedLoan($catalog, 2);

        $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', [
                'loan' => $loan,
                'code' => $units[2]->inventory_code,
                'selected' => [$units[0]->id, $units[1]->id],
            ]))
            ->assertStatus(409)
            ->assertJsonPath('status', 'limit');
    }

    public function test_payload_qr_berupa_url_dibaca_menggunakan_identitas_dari_database(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi QR URL', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $unit = $units->first();

        $url = route('scan', ['u' => $unit->id, 'c' => 'KODE-PALSU', 'r' => 1]);

        $this->actingAs($this->laboran)
            ->getJson(route('loans.handover-scan', ['loan' => $loan, 'code' => $url]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('unit.id', $unit->id)
            ->assertJsonPath('unit.inventory_code', $unit->inventory_code);
    }

    public function test_petugas_di_luar_cakupan_tidak_dapat_memindai(): void
    {
        [$catalog, $units] = $this->makeCatalog('Kursi Cakupan', 2);
        $loan = $this->approvedLoan($catalog, 1);

        $otherLaboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();

        $this->actingAs($otherLaboran)
            ->getJson(route('loans.handover-scan', [
                'loan' => $loan,
                'code' => $units->first()->inventory_code,
            ]))
            ->assertForbidden();
    }
}

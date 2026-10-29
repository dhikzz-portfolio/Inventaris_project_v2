<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\ReturnStatus;
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
 * Rincian barang pada detail peminjaman: kartu per unit, filter berbasis
 * kolom nyata, dan popup foto bukti yang terikat pada unit yang benar.
 */
class LoanItemDetailTest extends TestCase
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
    private function createCatalog(int $count, string $name): array
    {
        $catalog = Item::create([
            'internal_id' => 'DETAIL-'.Str::upper(Str::random(8)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(7000, 9999),
            'name' => $name,
            'specification' => 'Spesifikasi uji rincian barang',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $service = app(AssetUnitService::class);
        $units = collect();

        for ($index = 0; $index < $count; $index++) {
            $units->push($service->create($catalog, $this->room));
        }

        return [$catalog->refresh(), $units];
    }

    /** @param array<int, array{0: Item, 1: Collection<int, AssetUnit>}> $catalogUnits */
    private function createLoan(array $catalogUnits): Loan
    {
        $lines = array_map(
            fn (array $entry) => ['item_id' => $entry[0]->id, 'qty' => $entry[1]->count()],
            $catalogUnits
        );

        $loan = app(LoanService::class)->requestLoan(
            $this->guru,
            $lines,
            'Uji rincian barang per unit',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);

        foreach ($catalogUnits as [$catalog, $units]) {
            $loanItem = $loan->loanItems()->where('item_id', $catalog->id)->firstOrFail();

            foreach ($units as $unit) {
                $unit->forceFill(['status' => UnitStatus::DIPINJAM])->save();

                LoanItemUnit::create([
                    'loan_item_id' => $loanItem->id,
                    'asset_unit_id' => $unit->id,
                    'assigned_at' => now(),
                    'opened_at' => now(),
                    'condition_out' => Condition::BAIK,
                ]);
            }
        }

        return $loan->refresh();
    }

    public function test_kartu_unit_menampilkan_identitas_dan_popup_foto_hanya_untuk_unit_berfoto(): void
    {
        [$catalog, $units] = $this->createCatalog(2, 'Kursi Detail Foto');
        $loan = $this->createLoan([[$catalog, $units]]);

        $damaged = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();
        $damaged->forceFill([
            'returned_at' => now()->subDay(),
            'condition_in' => Condition::RUSAK_RINGAN,
            'return_status' => ReturnStatus::SUDAH_DIKONFIRMASI,
            'return_notes' => 'Retak pada rangka',
            'confirmed_by' => $this->laboran->id,
            'confirmed_at' => now()->subDay(),
            'damage_photo_path' => 'damage/loan-'.$loan->id.'/bukti-unit-satu.jpg',
        ])->save();

        $healthy = LoanItemUnit::where('asset_unit_id', $units[1]->id)->firstOrFail();

        $response = $this->actingAs($this->laboran)->get(route('loans.show', $loan));

        $response->assertOk()
            ->assertSee($units[0]->inventory_code)
            ->assertSee($units[1]->inventory_code)
            ->assertSee('Retak pada rangka')
            ->assertSee('Rusak Ringan');

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Lihat foto bukti kerusakan'));

        // Foto melekat pada unit yang rusak, bukan pada unit lain.
        $this->assertSame('damage/loan-'.$loan->id.'/bukti-unit-satu.jpg', $damaged->refresh()->damage_photo_path);
        $this->assertNull($healthy->refresh()->damage_photo_path);
    }

    public function test_filter_foto_dan_pencarian_unit_bekerja_bersama(): void
    {
        [$catalogA, $unitsA] = $this->createCatalog(1, 'Kursi Filter Foto');
        [$catalogB, $unitsB] = $this->createCatalog(1, 'Meja Filter Foto');
        $loan = $this->createLoan([[$catalogA, $unitsA], [$catalogB, $unitsB]]);

        LoanItemUnit::where('asset_unit_id', $unitsA[0]->id)->firstOrFail()->forceFill([
            'condition_in' => Condition::RUSAK_BERAT,
            'returned_at' => now()->subDay(),
            'return_status' => ReturnStatus::SUDAH_DIKONFIRMASI,
            'damage_photo_path' => 'damage/loan-'.$loan->id.'/bukti.jpg',
        ])->save();

        $this->actingAs($this->laboran);

        $this->get(route('loans.show', [$loan, 'item_photo' => 'ada']))
            ->assertOk()
            ->assertSee($unitsA[0]->inventory_code)
            ->assertDontSee($unitsB[0]->inventory_code)
            ->assertSee('Menampilkan 1–1 dari 1 unit sesuai filter.');

        $this->get(route('loans.show', [$loan, 'item_search' => $unitsB[0]->inventory_code]))
            ->assertOk()
            ->assertSee($unitsB[0]->inventory_code)
            ->assertDontSee($unitsA[0]->inventory_code);

        $this->get(route('loans.show', [$loan, 'item_condition' => Condition::RUSAK_BERAT->value]))
            ->assertOk()
            ->assertSee($unitsA[0]->inventory_code)
            ->assertDontSee($unitsB[0]->inventory_code);
    }

    public function test_filter_tanpa_hasil_menampilkan_empty_state_dan_reset(): void
    {
        [$catalog, $units] = $this->createCatalog(1, 'Kursi Tanpa Hasil');
        $loan = $this->createLoan([[$catalog, $units]]);

        $this->actingAs($this->laboran)
            ->get(route('loans.show', [$loan, 'item_search' => 'barang-yang-tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ada unit fisik yang cocok')
            ->assertSee('Reset filter');
    }

    public function test_rentang_tanggal_pemeriksaan_dan_pagination_tetap_akurat(): void
    {
        [$catalog, $units] = $this->createCatalog(25, 'Kursi Pagination');
        $loan = $this->createLoan([[$catalog, $units]]);

        $first = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();
        $first->forceFill([
            'returned_at' => now()->subDay(),
            'condition_in' => Condition::BAIK,
            'return_status' => ReturnStatus::SUDAH_DIKONFIRMASI,
            'confirmed_at' => '2026-10-01 10:00:00',
        ])->save();

        $this->actingAs($this->laboran)
            ->get(route('loans.show', [$loan, 'item_from' => '2026-10-01', 'item_to' => '2026-10-01']))
            ->assertOk()
            ->assertSee($units[0]->inventory_code)
            ->assertSee('Menampilkan 1–1 dari 1 unit sesuai filter.');

        $this->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Menampilkan 24 dari 25 unit.');

        $this->get(route('loans.show', [$loan, 'page' => 2]))
            ->assertOk()
            ->assertSee($units[24]->inventory_code);
    }
}

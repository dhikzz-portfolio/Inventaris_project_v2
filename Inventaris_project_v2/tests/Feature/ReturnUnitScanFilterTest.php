<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Livewire\Loan\ReturnForm;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman validasi pengembalian: pencarian, filter, dan scan QR hanya
 * menemukan/memilih unit — konfirmasi tetap memerlukan aksi petugas.
 */
class ReturnUnitScanFilterTest extends TestCase
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
            'internal_id' => 'RET-SCAN-'.Str::upper(Str::random(8)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(7000, 9999),
            'name' => $name,
            'specification' => 'Spesifikasi uji pencarian validasi',
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
            'Uji pencarian dan scan validasi',
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

    /** @return array{0: Loan, 1: LoanItem, 2: Collection<int, AssetUnit>} */
    private function makeUnitLoan(int $count): array
    {
        [$catalog, $units] = $this->createCatalog($count, 'Kursi Validasi '.Str::upper(Str::random(4)));
        $loan = $this->createLoan([[$catalog, $units]]);
        $loanItem = $loan->loanItems()->where('item_id', $catalog->id)->firstOrFail();

        return [$loan, $loanItem, $units];
    }

    public function test_pencarian_dan_filter_menampilkan_unit_yang_tepat(): void
    {
        [$catalogA, $unitsA] = $this->createCatalog(2, 'Kursi Filter A');
        [$catalogB, $unitsB] = $this->createCatalog(2, 'Meja Filter B');
        $loan = $this->createLoan([[$catalogA, $unitsA], [$catalogB, $unitsB]]);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('search', 'Kursi Filter A')
            ->assertSee($catalogA->name)
            ->assertDontSee($catalogB->name)
            ->assertSee('Menampilkan 2 dari 4 unit')
            ->set('search', '')
            ->set('filterReturnState', 'belum')
            ->assertSee('Menampilkan 4 dari 4 unit')
            ->set('filterCondition', Condition::RUSAK_RINGAN->value)
            ->assertSee('Menampilkan 0 dari 4 unit')
            ->assertSee('Tidak ada unit yang cocok');
    }

    public function test_scan_qr_memilih_dan_menyorot_unit_tanpa_mengonfirmasi(): void
    {
        [$loan, $loanItem, $units] = $this->makeUnitLoan(2);
        $target = $units[1];

        $this->actingAs($this->laboran);

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('scanCode', $target->inventory_code)
            ->call('scanUnitCode')
            ->assertSet('scanError', '')
            ->assertSet('focusedIndex', 1)
            ->assertSee('ditemukan dan dipilih')
            ->assertSee('1 unit dipilih')
            ->assertDispatched('focus-unit');

        $this->assertSame([1], $component->get('selected'));

        // Scan tidak boleh menutup penugasan atau mengubah kondisi unit.
        $assignment = LoanItemUnit::where('asset_unit_id', $target->id)->firstOrFail();
        $this->assertNull($assignment->returned_at);
        $this->assertSame(0, (int) $loanItem->refresh()->qty_returned);
        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_scan_qr_unit_di_luar_transaksi_ditolak(): void
    {
        [$loan] = $this->makeUnitLoan(1);
        [$otherCatalog, $otherUnits] = $this->createCatalog(1, 'Unit Transaksi Lain');

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('scanCode', $otherUnits->first()->inventory_code)
            ->call('scanUnitCode')
            ->assertSee('tidak termasuk dalam transaksi')
            ->assertSet('selected', []);
    }

    public function test_scan_qr_unit_yang_sudah_dikonfirmasi_ditolak(): void
    {
        [$loan, $loanItem, $units] = $this->makeUnitLoan(1);

        $this->actingAs($this->laboran);

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->call('confirmOne', 0)
            ->assertHasNoErrors();

        $this->assertNotNull(LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail()->returned_at);

        $component->set('scanCode', $units[0]->inventory_code)
            ->call('scanUnitCode')
            ->assertSee('sudah dikonfirmasi')
            ->assertSet('selected', []);
    }

    public function test_scan_payload_url_qr_menggunakan_identitas_unit_dari_database(): void
    {
        [$loan, , $units] = $this->makeUnitLoan(1);
        $unit = $units->first();

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('scanCode', route('scan', ['u' => $unit->id, 'c' => 'KODE-PALSU']))
            ->call('scanUnitCode')
            ->assertSet('scanError', '')
            ->assertSet('focusedIndex', 0)
            ->assertSee('ditemukan dan dipilih');
    }
}

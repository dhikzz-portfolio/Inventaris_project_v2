<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Alur peminjaman berbasis unit fisik: penugasan unit saat serah terima,
 * keterlacakan kode inventaris, dan pengembalian per unit.
 */
class LoanUnitFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $laboranTkj;

    private User $guruBudi;

    private Room $roomTkj;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboranTkj = User::where('username', 'laboran.tp')->firstOrFail();
        $this->guruBudi = User::where('username', 'guru.budi')->firstOrFail();
        $this->roomTkj = Room::where('major_id', Major::where('code', 'TP')->value('id'))->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    private function makeCatalog(string $name, int $qty = 0): Item
    {
        static $seq = 0;
        $seq++;

        $catalog = Item::create([
            'internal_id' => 'UNIT-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 5)).'-'.$seq,
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => (string) (9000 + $seq),
            'name' => $name,
            'category_id' => $this->category->id,
            'room_id' => $this->roomTkj->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'is_high_value' => false,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        if ($qty > 0) {
            $service = app(AssetUnitService::class);
            for ($i = 0; $i < $qty; $i++) {
                $service->create($catalog, $this->roomTkj, [], $this->laboranTkj);
            }
        }

        return $catalog->refresh();
    }

    private function approvedLoan(Item $catalog, int $qty, string $purpose = 'Uji unit'): Loan
    {
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $catalog->id, 'qty' => $qty]],
            $purpose,
            '2027-09-01 08:00',
            '2027-09-05 16:00'
        );

        $service->decide($loan, $this->laboranTkj, Decision::APPROVED);

        return $loan->refresh();
    }

    public function test_form_serah_terima_unit_dapat_diakses_dan_menampilkan_kode_inventaris(): void
    {
        $catalog = $this->makeCatalog('Kursi Serah Terima', 4);
        $loan = $this->approvedLoan($catalog, 2);

        $response = $this->actingAs($this->laboranTkj)->get(route('loans.handover-form', $loan));

        $response->assertOk();
        $response->assertSee($catalog->units()->orderBy('inventory_code')->first()->inventory_code);
    }

    public function test_serah_terima_menugaskan_unit_dan_mencatat_kode_inventaris(): void
    {
        $catalog = $this->makeCatalog('Kursi Ditugaskan', 4);
        $loan = $this->approvedLoan($catalog, 2);
        $loanItem = $loan->loanItems()->firstOrFail();

        $units = $catalog->units()->orderBy('inventory_code')->take(2)->pluck('id')->all();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [
                    $loanItem->id => ['unit_ids' => $units],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
        $this->assertSame(2, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());

        foreach ($units as $unitId) {
            $assignment = LoanItemUnit::where('asset_unit_id', $unitId)->firstOrFail();
            $this->assertNotNull($assignment->opened_at);
            $this->assertNull($assignment->returned_at);
        }
    }

    public function test_unit_dari_katalog_lain_tidak_boleh_ditugaskan(): void
    {
        $catalogKursi = $this->makeCatalog('Kursi Asli', 2);
        $catalogMeja = $this->makeCatalog('Meja Asing', 2);
        $loan = $this->approvedLoan($catalogKursi, 1);
        $loanItem = $loan->loanItems()->firstOrFail();

        $foreignUnit = $catalogMeja->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [
                    $loanItem->id => ['unit_ids' => [$foreignUnit->id]],
                ],
            ])
            ->assertSessionHasErrors('handover');

        $this->assertSame(0, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
    }

    public function test_unit_yang_sedang_dipinjam_tidak_boleh_ditugaskan_ulang(): void
    {
        $catalog = $this->makeCatalog('Kursi Bentrok', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Peminjaman kedua untuk unit yang sama harus ditolak.
        $loanKedua = $this->approvedLoan($catalog, 1, 'Uji bentrok unit');
        $loanItemKedua = $loanKedua->loanItems()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loanKedua), [
                'unit_assignments' => [$loanItemKedua->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertSessionHasErrors('handover');
    }

    public function test_status_unit_berubah_menjadi_dipinjam_setelah_serah_terima(): void
    {
        $catalog = $this->makeCatalog('Kursi Dipinjam', 3);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UnitStatus::DIPINJAM, $unit->refresh()->status);
    }

    public function test_pengembalian_menutup_unit_yang_dipilih_bukan_unit_lain(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Parsial', 4);
        $loan = $this->approvedLoan($catalog, 4);
        $loanItem = $loan->loanItems()->firstOrFail();

        $units = $catalog->units()->orderBy('inventory_code')->get();
        $chosen = [$units[0], $units[2]];

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [
                    $loanItem->id => ['unit_ids' => $units->pluck('id')->all()],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $service = app(LoanService::class);

        foreach ($chosen as $unit) {
            $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();

            $service->processReturn($loan->refresh(), $this->laboranTkj, [[
                'loan_item_id' => $loanItem->id,
                'assignment_id' => $assignment->id,
                'unit_id' => $unit->id,
                'qty_returned' => 1,
                'condition_in' => Condition::BAIK->value,
                'returned_room_id' => $this->roomTkj->id,
            ]]);
        }

        foreach ($chosen as $unit) {
            $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();
            $this->assertNotNull($assignment->refresh()->returned_at, "Unit {$unit->inventory_code} seharusnya tercatat sudah kembali.");
        }

        // Dua unit yang tidak dipilih masih harus_open.
        foreach ([$units[1], $units[3]] as $unit) {
            $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();
            $this->assertNull($assignment->refresh()->returned_at, "Unit {$unit->inventory_code} seharusnya belum kembali.");
        }

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_unit_yang_kembali_dalam_kondisi_rusak_tidak_lagi_dapat_dipinjam(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Rusak', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();

        app(LoanService::class)->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'assignment_id' => $assignment->id,
            'unit_id' => $unit->id,
            'qty_returned' => 1,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $this->roomTkj->id,
            'damage_photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]]);

        $unit->refresh();
        $this->assertSame(Condition::RUSAK_RINGAN, $unit->condition);
        $this->assertNotSame(UnitStatus::DIPINJAM, $unit->status);
        $this->assertFalse($unit->isAvailableForLoan());
    }

    public function test_unit_yang_dikembalikan_ke_ruangan_lain_mencatat_lokasi_baru(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Pindah', 1);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();
        $ruangTujuan = Room::where('code', 'G1-03')->firstOrFail();

        app(LoanService::class)->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'assignment_id' => $assignment->id,
            'unit_id' => $unit->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $ruangTujuan->id,
        ]]);

        $this->assertSame($ruangTujuan->id, $unit->refresh()->room_id);
    }

    public function test_unit_yang_sudah_dikembalikan_tidak_dapat_dikembalikan_lagi(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Ganda', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = LoanItemUnit::where('asset_unit_id', $unit->id)->firstOrFail();
        $service = app(LoanService::class);

        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'assignment_id' => $assignment->id,
            'unit_id' => $unit->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $this->roomTkj->id,
        ]]);

        $this->expectException(ValidationException::class);

        $service->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'assignment_id' => $assignment->id,
            'unit_id' => $unit->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $this->roomTkj->id,
        ]]);
    }

    public function test_asset_unit_tidak_boleh_ditugaskan_dua_kali_pada_satu_pinjaman(): void
    {
        $catalog = $this->makeCatalog('Kursi Ganda Serial', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [
                    $loanItem->id => ['unit_ids' => [$unit->id, $unit->id]],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
        $this->assertSame(1, LoanItemUnit::where('asset_unit_id', $unit->id)->count());
    }

    public function test_serah_terima_menolak_unit_melebihi_jumlah_yang_dipinjam(): void
    {
        $catalog = $this->makeCatalog('Kursi Kelebihan Unit', 4);
        $loan = $this->approvedLoan($catalog, 2);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unitIds = $catalog->units()->orderBy('inventory_code')->take(3)->pluck('id')->all();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $unitIds]],
            ])
            ->assertSessionHasErrors('handover');

        $this->assertSame(0, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertSame(0, $catalog->units()->where('status', UnitStatus::DIPINJAM->value)->count());
    }

    public function test_request_serah_terima_berulang_tidak_membuat_penugasan_ganda(): void
    {
        $catalog = $this->makeCatalog('Kursi Serah Berulang', 2);
        $loan = $this->approvedLoan($catalog, 1);
        $loanItem = $loan->loanItems()->firstOrFail();
        $unitId = $catalog->units()->firstOrFail()->id;

        $payload = ['unit_assignments' => [$loanItem->id => ['unit_ids' => [$unitId]]]];

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), $payload)
            ->assertSessionHasErrors('handover');

        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
        $this->assertSame(1, LoanItemUnit::where('asset_unit_id', $unitId)->count());
    }

    public function test_serah_terima_menolak_penugasan_unit_parsial(): void
    {
        $catalog = $this->makeCatalog('Kursi Parsial Serah Terima', 4);
        $loan = $this->approvedLoan($catalog, 2);
        $loanItem = $loan->loanItems()->firstOrFail();

        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => [$unit->id]]],
            ])
            ->assertSessionHasErrors('handover');

        // Penugasan parsial ditolak seluruhnya: tidak ada unit yang ditandai dipinjam.
        $this->assertSame(0, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertSame(UnitStatus::AKTIF, $unit->refresh()->status);
    }

    public function test_pengembalian_tanpa_identitas_unit_hanya_menutup_unit_yang_dikembalikan(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Kuantitas Lama', 4);
        $loan = $this->approvedLoan($catalog, 4);
        $loanItem = $loan->loanItems()->firstOrFail();

        $units = $catalog->units()->orderBy('inventory_code')->get();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $units->pluck('id')->all()]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Alur kuantitas lama: tanpa assignment_id/unit_id, hanya satu unit
        // yang dicatat kembali — bukan seluruh penugasan terbuka.
        app(LoanService::class)->processReturn($loan->refresh(), $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $this->roomTkj->id,
        ]]);

        $open = LoanItemUnit::where('loan_item_id', $loanItem->id)
            ->whereNull('returned_at')
            ->count();

        $this->assertSame(3, $open, 'Tiga unit yang belum kembali harus tetap berstatus dipinjam.');
        $this->assertSame(1, $loanItem->refresh()->qty_returned);
    }

    public function test_daftar_unit_serah_terima_tidak_menampilkan_unit_di_luar_cakupan(): void
    {
        $catalog = $this->makeCatalog('Kursi Cakupan', 2);
        $loan = $this->approvedLoan($catalog, 1);

        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->get(route('loans.handover-form', $loan))
            ->assertOk()
            ->assertSee($unit->inventory_code);
    }
}

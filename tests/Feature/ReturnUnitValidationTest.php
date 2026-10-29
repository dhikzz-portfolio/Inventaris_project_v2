<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\ReturnStatus;
use App\Enums\UnitStatus;
use App\Livewire\Loan\ReturnForm;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnUnitValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $guru;

    private Category $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
        $this->room = Room::where('code', 'G1-01')->firstOrFail();
    }

    public function test_memilih_unit_tidak_otomatis_mengonfirmasi_pengembalian(): void
    {
        [$loan, $loanItem, $units] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('selected', [0, 1])
            ->assertSee('2 unit dipilih')
            ->assertSee('0 dari 2 unit sudah dikonfirmasi')
            ->assertSee($units->first()->inventory_code);

        $this->assertSame(0, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());
        $this->assertSame(0, (int) $loanItem->refresh()->qty_returned);
    }

    public function test_konfirmasi_satu_unit_tidak_mengubah_unit_lain(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$loan, $loanItem, $units] = $this->makeUnitLoan(3);

        $this->actingAs($this->laboran);

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('rows.0.condition_in', Condition::RUSAK_RINGAN->value)
            ->set('rows.0.damage_photo', UploadedFile::fake()->image('baret.jpg'))
            ->set('rows.0.return_notes', 'Baret pada rangka')
            ->call('confirmOne', 0)
            ->assertHasNoErrors()
            ->assertSee('1 dari 3 unit sudah dikonfirmasi');

        $confirmed = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();

        $this->assertNotNull($confirmed->returned_at);
        $this->assertSame(ReturnStatus::SUDAH_DIKONFIRMASI, $confirmed->return_status);
        $this->assertSame(Condition::RUSAK_RINGAN, $confirmed->condition_in);
        $this->assertSame('Baret pada rangka', $confirmed->return_notes);
        $this->assertSame($this->laboran->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->damage_photo_path, 'Foto bukti harus tersimpan pada unit yang diperiksa.');
        $this->assertStringStartsWith('damage/loan-'.$loan->id, $confirmed->damage_photo_path);
        $this->assertSame(0, LoanItemUnit::whereIn('asset_unit_id', $units->skip(1)->pluck('id'))
            ->whereNotNull('damage_photo_path')->count(), 'Foto tidak boleh menempel pada unit lain.');

        $this->assertSame(0, LoanItemUnit::whereIn('asset_unit_id', $units->skip(1)->pluck('id'))->whereNotNull('returned_at')->count());
        $this->assertSame(1, (int) $loanItem->refresh()->qty_returned);
        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);

        $component->assertSee('2 unit belum dikonfirmasi');
    }

    public function test_validasi_massal_tidak_tampil_untuk_kelompok_kurang_dari_sebelas_unit(): void
    {
        [$catalog, $units] = $this->createCatalog(10, 'Kursi Sepuluh Unit');
        $loan = $this->createLoan([[$catalog, $units]]);

        $this->actingAs($this->laboran);

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->assertDontSee('Validasi Semua')
            ->call('requestMassValidation', $catalog->id)
            ->assertHasErrors(['mass']);

        $this->assertNull($component->get('massGroupId'));
        $this->assertSame(0, LoanItemUnit::whereIn('asset_unit_id', $units->pluck('id'))->whereNotNull('returned_at')->count());
    }

    public function test_validasi_massal_sebelas_unit_hanya_memproses_unit_yang_belum_dikonfirmasi(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$catalog, $units] = $this->createCatalog(11, 'Kursi Sebelas Unit');
        $loan = $this->createLoan([[$catalog, $units]]);
        $loanItem = $loan->loanItems()->where('item_id', $catalog->id)->firstOrFail();

        $this->actingAs($this->laboran);

        $first = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('rows.0.return_notes', 'Sudah diperiksa lebih dulu')
            ->call('confirmOne', 0)
            ->assertHasNoErrors()
            ->call('requestMassValidation', $catalog->id)
            ->assertSet('massPendingCount', 10)
            ->assertSet('massGroupId', $catalog->id)
            ->assertSee('Konfirmasi validasi massal')
            ->call('confirmMassValidation')
            ->assertHasNoErrors();

        $this->assertSame('Sudah diperiksa lebih dulu', $first->refresh()->return_notes);
        $this->assertSame(11, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());
        $this->assertSame(11, LoanItemUnit::where('loan_item_id', $loanItem->id)->where('return_status', ReturnStatus::SUDAH_DIKONFIRMASI->value)->count());
        $this->assertSame(11, (int) $loanItem->refresh()->qty_returned);
        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);

        $component->assertSee('Seluruh unit terkonfirmasi');
    }

    public function test_validasi_massal_tidak_mencampur_katalog_lain(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$catalogA, $unitsA] = $this->createCatalog(11, 'Kursi Massal');
        [$catalogB, $unitsB] = $this->createCatalog(3, 'Meja Lain');
        $loan = $this->createLoan([[$catalogA, $unitsA], [$catalogB, $unitsB]]);
        $loanItemB = $loan->loanItems()->where('item_id', $catalogB->id)->firstOrFail();

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->call('requestMassValidation', $catalogA->id)
            ->assertSet('massPendingCount', 11)
            ->call('confirmMassValidation')
            ->assertHasNoErrors()
            ->assertSee('11 dari 14 unit sudah dikonfirmasi');

        $this->assertSame(11, LoanItemUnit::whereIn('asset_unit_id', $unitsA->pluck('id'))->whereNotNull('returned_at')->count());
        $this->assertSame(0, LoanItemUnit::whereIn('asset_unit_id', $unitsB->pluck('id'))->whereNotNull('returned_at')->count());
        $this->assertSame(0, (int) $loanItemB->refresh()->qty_returned);
    }

    public function test_tombol_selesai_tetap_tertahan_saat_masih_ada_unit_belum_dikonfirmasi(): void
    {
        [$loan] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->call('confirmOne', 0)
            ->assertHasNoErrors()
            ->assertSee('1 dari 2 unit sudah dikonfirmasi')
            ->call('finishReturn')
            ->assertHasErrors(['finish']);

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_backend_menolak_penyelesaian_jika_masih_ada_unit_belum_dikonfirmasi(): void
    {
        [$loan] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->call('confirmOne', 0)
            ->assertHasNoErrors();

        try {
            app(LoanService::class)->completeReturn($loan->refresh(), $this->laboran);
            $this->fail('Penyelesaian seharusnya ditolak karena masih ada unit belum dikonfirmasi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('finish', $exception->errors());
            $this->assertStringContainsString('1 dari 2 unit', $exception->errors()['finish'][0]);
        }

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_pinjaman_selesai_setelah_semua_unit_dikonfirmasi_dan_aksi_selesai_ditekan(): void
    {
        [$loan, $loanItem] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        $component = Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('selected', [0, 1])
            ->call('confirmSelected')
            ->assertHasNoErrors();

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
        $this->assertSame(2, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());

        $component->call('finishReturn')
            ->assertHasNoErrors()
            ->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame(LoanStatus::SELESAI, $loan->status);
        $this->assertSame($this->laboran->id, $loan->returned_by);
        $this->assertNotNull($loan->returned_at);
    }

    public function test_setiap_unit_dapat_memiliki_kondisi_dan_catatan_berbeda(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$loan, $loanItem, $units] = $this->makeUnitLoan(3);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->set('rows.0.condition_in', Condition::BAIK->value)
            ->set('rows.0.return_notes', 'Kondisi baik')
            ->set('rows.1.condition_in', Condition::RUSAK_RINGAN->value)
            ->set('rows.1.damage_photo', UploadedFile::fake()->image('ringan.jpg'))
            ->set('rows.1.return_notes', 'Baret halus')
            ->set('rows.2.condition_in', Condition::RUSAK_BERAT->value)
            ->set('rows.2.damage_photo', UploadedFile::fake()->image('berat.jpg'))
            ->set('rows.2.return_notes', 'Rangka patah')
            ->set('selected', [0, 1, 2])
            ->call('confirmSelected')
            ->assertHasNoErrors();

        $assignmentOne = LoanItemUnit::where('asset_unit_id', $units[1]->id)->firstOrFail();
        $assignmentTwo = LoanItemUnit::where('asset_unit_id', $units[2]->id)->firstOrFail();

        $this->assertSame(Condition::RUSAK_RINGAN, $assignmentOne->condition_in);
        $this->assertSame('Baret halus', $assignmentOne->return_notes);
        $this->assertSame(Condition::RUSAK_BERAT, $assignmentTwo->condition_in);
        $this->assertSame('Rangka patah', $assignmentTwo->return_notes);
        $this->assertSame(Condition::RUSAK_RINGAN, $units[1]->refresh()->condition);
        $this->assertSame(Condition::RUSAK_BERAT, $units[2]->refresh()->condition);
        $this->assertSame(2, (int) $loanItem->refresh()->qty_damaged);
        $this->assertSame(ItemStatus::RUSAK_BERAT, $loanItem->item->refresh()->status);
    }

    public function test_petugas_di_luar_cakupan_tidak_dapat_membuka_form_pengembalian(): void
    {
        [$loan] = $this->makeUnitLoan(1);

        $otherLaboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();

        $this->actingAs($otherLaboran)
            ->get(route('loans.return-form', $loan))
            ->assertForbidden();

        $this->actingAs($this->guru)
            ->get(route('loans.return-form', $loan))
            ->assertForbidden();
    }

    public function test_foto_kerusakan_unit_sebelumnya_tidak_memenuhi_unit_berikutnya(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$loan, $loanItem, $units] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        $service = app(LoanService::class);
        $first = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();

        $service->processReturn($loan, $this->laboran, [[
            'loan_item_id' => $loanItem->id,
            'assignment_id' => $first->id,
            'unit_id' => $units[0]->id,
            'qty_returned' => 1,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $this->room->id,
            'return_notes' => 'Baret pada unit pertama',
            'damage_photo' => UploadedFile::fake()->image('unit-pertama.jpg'),
        ]]);

        $second = LoanItemUnit::where('asset_unit_id', $units[1]->id)->firstOrFail();

        try {
            $service->processReturn($loan->refresh(), $this->laboran, [[
                'loan_item_id' => $loanItem->id,
                'assignment_id' => $second->id,
                'unit_id' => $units[1]->id,
                'qty_returned' => 1,
                'condition_in' => Condition::RUSAK_RINGAN->value,
                'returned_room_id' => $this->room->id,
                'return_notes' => 'Baret pada unit kedua',
            ]]);
            $this->fail('Unit rusak tanpa foto sendiri seharusnya ditolak meskipun unit lain sudah punya foto.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows', $exception->errors());
            $this->assertStringContainsString('setiap unit rusak', $exception->errors()['rows'][0]);
        }

        $this->assertNull($second->refresh()->returned_at);
        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());
    }

    public function test_unit_yang_sudah_dikonfirmasi_tidak_dapat_dikonfirmasi_dua_kali(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$loan, $loanItem, $units] = $this->makeUnitLoan(2);

        $this->actingAs($this->laboran);

        Livewire::test(ReturnForm::class, ['loan' => $loan])
            ->call('confirmOne', 0)
            ->assertHasNoErrors()
            ->call('confirmOne', 0)
            ->assertHasErrors(['selection']);

        $assignment = LoanItemUnit::where('asset_unit_id', $units[0]->id)->firstOrFail();

        $this->assertNotNull($assignment->returned_at);
        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());

        try {
            app(LoanService::class)->processReturn($loan->refresh(), $this->laboran, [[
                'loan_item_id' => $loanItem->id,
                'assignment_id' => $assignment->id,
                'unit_id' => $units[0]->id,
                'qty_returned' => 1,
                'condition_in' => Condition::BAIK->value,
                'returned_room_id' => $this->room->id,
            ]]);
            $this->fail('Unit yang sudah kembali seharusnya tidak dapat diproses ulang.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows', $exception->errors());
        }

        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->whereNotNull('returned_at')->count());
    }

    /** @return array{0: Item, 1: Collection<int, AssetUnit>} */
    private function createCatalog(int $count, string $name): array
    {
        $catalog = Item::create([
            'internal_id' => 'RET-UNIT-'.Str::upper(Str::random(8)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(8000, 9999),
            'name' => $name,
            'specification' => 'Unit uji pengembalian',
            'category_id' => $this->category->id,
            'room_id' => $this->room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $unitService = app(AssetUnitService::class);
        $units = collect();

        for ($index = 0; $index < $count; $index++) {
            $units->push($unitService->create($catalog, $this->room));
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
            'Uji validasi pengembalian per unit',
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
        [$catalog, $units] = $this->createCatalog($count, 'Kursi Uji '.Str::upper(Str::random(4)));
        $loan = $this->createLoan([[$catalog, $units]]);
        $loanItem = $loan->loanItems()->where('item_id', $catalog->id)->firstOrFail();

        return [$loan, $loanItem, $units];
    }
}

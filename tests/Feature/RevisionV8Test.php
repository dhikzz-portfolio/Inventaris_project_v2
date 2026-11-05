<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\OperatorCategory;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revisi V8: kelompok spesifikasi majemuk, kondisi per alokasi, laporan
 * kerusakan otomatis, filter kategori pengguna, dan cakupan laboratorium
 * Operator berdasarkan jurusan.
 */
class RevisionV8Test extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $editor;

    private User $operator;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@gmail.com')->firstOrFail();
        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->editor = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->operator = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    private function room(string $code): Room
    {
        return Room::where('code', $code)->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<string, mixed>
     */
    private function procurementPayload(array $groups, int $total, string $name): array
    {
        return [
            'name' => $name,
            'category_id' => $this->category->id,
            'unit' => 'unit',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => $total,
            'spec_groups' => $groups,
        ];
    }

    public function test_katalog_mendukung_banyak_kelompok_spesifikasi_dan_kondisi_per_alokasi(): void
    {
        $lab1 = $this->room('H1-01');
        $lab2 = $this->room('H1-02');
        $name = 'Keyboard Uji V8 '.uniqid();

        $this->actingAs($this->admin)->post(route('items.store'), $this->procurementPayload([
            [
                'brand' => 'Logitech',
                'model_type' => 'K120',
                'acquisition_year' => 2026,
                'funding_source' => 'BOS',
                'quantity' => 3,
                'allocations' => [
                    ['room_id' => $lab1->id, 'quantity' => 2, 'condition' => 'BAIK', 'note' => 'Kemasan utuh'],
                    ['room_id' => $lab2->id, 'quantity' => 1, 'condition' => 'RUSAK_RINGAN', 'note' => 'Casing retak'],
                ],
            ],
            [
                'brand' => 'Rexus',
                'model_type' => 'KM9',
                'acquisition_year' => 2026,
                'funding_source' => 'Komite',
                'quantity' => 2,
                'allocations' => [
                    ['room_id' => $lab1->id, 'quantity' => 2, 'condition' => 'BAIK'],
                ],
            ],
        ], 5, $name))->assertRedirect(route('catalogs.index'))->assertSessionHasNoErrors();

        $catalog = Item::where('name', $name)->firstOrFail();

        $this->assertSame(5, $catalog->units()->count());
        $this->assertSame(5, (int) $catalog->refresh()->quantity);
        $this->assertCount(2, $catalog->specifications);
        $this->assertSame(3, $catalog->allocations()->count());
        $this->assertSame('INVENTARIS_TERCATAT', $catalog->acquisition_status);

        $damagedAllocation = $catalog->allocations()
            ->where('room_id', $lab2->id)
            ->firstOrFail();

        $this->assertSame(Condition::RUSAK_RINGAN, $damagedAllocation->condition);
        $this->assertSame('Casing retak', $damagedAllocation->note);
        $this->assertSame(2026, $damagedAllocation->acquisition_year);
        $this->assertSame('BOS', $damagedAllocation->funding_source);

        // Unit mewarisi tahun & sumber dana kelompok spesifikasinya.
        $komiteUnits = AssetUnit::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('funding_source', 'Komite')
            ->get();

        $this->assertCount(2, $komiteUnits);
    }

    public function test_jumlah_alokasi_setiap_kelompok_harus_sama_dengan_jumlah_kelompok(): void
    {
        $lab1 = $this->room('H1-01');
        $name = 'Keyboard Tidak Seimbang '.uniqid();

        $this->actingAs($this->admin)->post(route('items.store'), $this->procurementPayload([
            [
                'brand' => 'Logitech',
                'model_type' => 'K120',
                'quantity' => 3,
                'allocations' => [
                    ['room_id' => $lab1->id, 'quantity' => 2, 'condition' => 'BAIK'],
                ],
            ],
        ], 3, $name))->assertSessionHasErrors('spec_groups.0.allocations');

        $this->assertDatabaseMissing('item_catalogs', ['name' => $name]);
    }

    public function test_penerimaan_rusak_tetap_menjadi_inventaris_dan_membuat_laporan_otomatis(): void
    {
        $lab1 = $this->room('H1-01');
        $lab2 = $this->room('H1-02');
        $name = 'Mouse Uji V8 '.uniqid();

        $this->actingAs($this->operator)->post(route('items.store'), $this->procurementPayload([
            [
                'brand' => 'Logitech',
                'model_type' => 'M90',
                'quantity' => 3,
                'allocations' => [
                    ['room_id' => $lab1->id, 'quantity' => 2, 'condition' => 'BAIK'],
                    ['room_id' => $lab2->id, 'quantity' => 1, 'condition' => 'BAIK'],
                ],
            ],
        ], 3, $name))->assertRedirect(route('items.acquisitions'))->assertSessionHasNoErrors();

        $catalog = Item::where('name', $name)->firstOrFail();
        $goodAllocation = $catalog->allocations()->where('room_id', $lab1->id)->firstOrFail();
        $damagedAllocation = $catalog->allocations()->where('room_id', $lab2->id)->firstOrFail();

        $this->actingAs($this->operator)->post(route('items.acquisitions.submit', $catalog))->assertRedirect();
        $this->actingAs($this->editor)->post(route('items.acquisitions.confirm', $catalog))->assertRedirect();
        $this->actingAs($this->admin)->post(route('items.approve', $catalog))->assertRedirect();
        $this->actingAs($this->operator)->post(route('items.acquisitions.start', $catalog))->assertRedirect();

        $this->actingAs($this->operator)->post(route('items.acquisitions.receive', $catalog), [
            'received' => [
                $goodAllocation->id => ['quantity' => 2, 'condition' => 'BAIK', 'note' => 'Baik semua'],
                $damagedAllocation->id => ['quantity' => 1, 'condition' => 'RUSAK_RINGAN', 'note' => 'Casing retak'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $catalog->refresh();
        $this->assertSame('BARANG_DITERIMA', $catalog->acquisition_status);
        $this->assertSame(Condition::RUSAK_RINGAN, $damagedAllocation->refresh()->condition);

        $this->actingAs($this->operator)->post(route('items.acquisitions.record', $catalog))
            ->assertRedirect()->assertSessionHasNoErrors();

        $catalog->refresh();
        $this->assertSame('INVENTARIS_TERCATAT', $catalog->acquisition_status);
        $this->assertSame(3, $catalog->units()->count());

        $damagedUnit = AssetUnit::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('room_id', $lab2->id)
            ->firstOrFail();

        $this->assertSame(Condition::RUSAK_RINGAN, $damagedUnit->condition);
        $this->assertSame(UnitStatus::AKTIF, $damagedUnit->status);

        $report = DamageReport::query()->where('asset_unit_id', $damagedUnit->id)->firstOrFail();
        $this->assertSame(DamageClassification::RUSAK_RINGAN, $report->classification);
        $this->assertSame($damagedAllocation->id, $report->acquisition_allocation_id);
        $this->assertStringContainsString('Casing retak', $report->description);
        $this->assertSame(Condition::BAIK, $report->previous_condition);

        // Unit baik tidak memiliki laporan kerusakan.
        $goodUnit = AssetUnit::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('room_id', $lab1->id)
            ->firstOrFail();

        $this->assertSame(0, DamageReport::query()->where('asset_unit_id', $goodUnit->id)->count());

        // Percobaan penerbitan ulang tidak membuat unit/laporan duplikat.
        $this->actingAs($this->operator)->post(route('items.acquisitions.record', $catalog))
            ->assertSessionHasErrors('inventory');

        $this->assertSame(3, $catalog->units()->count());
        $this->assertSame(1, DamageReport::query()->count());

        // Kondisi dan keterangan alokasi tampil pada detail katalog.
        $this->actingAs($this->operator)->get(route('items.show', $catalog))
            ->assertOk()
            ->assertSee('Casing retak');
    }

    public function test_filter_kategori_pengguna_bekerja_pada_query_backend(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('users.index', ['category' => BorrowerType::SISWA->value]))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => collect($users->items())
                ->every(fn (User $user): bool => $user->borrower_type === BorrowerType::SISWA));

        $this->actingAs($this->superAdmin)
            ->get(route('users.index', ['category' => OperatorCategory::LABORAN->value]))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => collect($users->items())
                ->every(fn (User $user): bool => $user->operator_category === OperatorCategory::LABORAN));

        $this->actingAs($this->superAdmin)
            ->get(route('users.index', ['category' => 'LAINNYA', 'search' => 'admin']))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => collect($users->items())
                ->every(fn (User $user): bool => $user->borrower_type === null && $user->operator_category === null));
    }

    public function test_operator_hanya_mendapat_laboratorium_dari_jurusan_terpilih(): void
    {
        $rpl = Major::where('code', 'RPL')->firstOrFail();
        $dkv = Major::where('code', 'DKV')->firstOrFail();
        $rplRoom = Room::where('is_lab', true)->where('major_id', $rpl->id)->firstOrFail();
        $dkvRoom = Room::where('is_lab', true)->where('major_id', $dkv->id)->firstOrFail();

        // Laboratorium jurusan lain ditolak walaupun ID dikirim manual.
        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Operator Salah Jurusan',
            'email' => 'operator.salah@gmail.com',
            'password' => 'rahasia-aman-123',
            'password_confirmation' => 'rahasia-aman-123',
            'role' => Role::OPERATOR->value,
            'operator_category' => OperatorCategory::LABORAN->value,
            'major_id' => $rpl->id,
            'room_ids' => [$dkvRoom->id],
        ])->assertSessionHasErrors('room_ids');

        $this->assertDatabaseMissing('users', ['email' => 'operator.salah@gmail.com']);

        // Tanpa memilih laboratorium, seluruh laboratorium jurusan aktif default.
        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Operator RPL Baru',
            'email' => 'operator.rpl.baru@gmail.com',
            'password' => 'rahasia-aman-123',
            'password_confirmation' => 'rahasia-aman-123',
            'role' => Role::OPERATOR->value,
            'operator_category' => OperatorCategory::LABORAN->value,
            'major_id' => $rpl->id,
            'room_ids' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $operator = User::where('email', 'operator.rpl.baru@gmail.com')->firstOrFail();
        $expectedLabs = Room::where('is_lab', true)->where('major_id', $rpl->id)->count();

        $this->assertSame($rpl->id, $operator->major_id);
        $this->assertSame($expectedLabs, $operator->roomScopes()->count());
        $this->assertTrue($operator->roomScopes()->pluck('room_id')->contains($rplRoom->id));
        $this->assertFalse($operator->roomScopes()->pluck('room_id')->contains($dkvRoom->id));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\AssetUnit;
use App\Models\Building;
use App\Models\Category;
use App\Models\Event;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Major;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevisionV3Test extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $guru;

    private User $staff;

    private User $siswa;

    private User $laboran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->staff = User::where('email', 'staf.tata@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
    }

    /** Ruangan baru agar mutasi/pengadaan tidak bergantung sisa kapasitas ruangan demo. */
    private function makeRoom(int $roomNo): Room
    {
        $building = Building::query()->firstOrFail();

        return Room::create([
            'building_id' => $building->id,
            'floor' => 9,
            'room_no' => $roomNo,
            'code' => sprintf('Z9-%02d', $roomNo),
            'name' => 'Ruang Uji '.$roomNo,
            'building_letter' => $building->code,
            'is_lab' => true,
        ]);
    }

    private function unitWithSpecification(): AssetUnit
    {
        $unit = AssetUnit::query()
            ->whereNotNull('specification_id')
            ->whereIn('room_id', $this->laboran->roomScopes()->select('room_id'))
            ->first();

        if ($unit === null) {
            $unit = AssetUnit::query()->whereNotNull('specification_id')->firstOrFail();
        }

        return $unit;
    }

    public function test_label_and_scan_use_the_unit_specification_not_the_catalog_procurement_brand(): void
    {
        $unit = $this->unitWithSpecification();
        $catalog = $unit->catalog;

        $specification = Specification::resolveFor($catalog, 'Chitose Spesifikasi Uji', 'Kursi Susun Uji');
        $unit->forceFill(['specification_id' => $specification->id])->save();

        $response = $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id);

        $response->assertOk();
        $response->assertSee('Chitose Spesifikasi Uji');
        $response->assertSee('Kursi Susun Uji');

        $html = view('pdf.unit_label', [
            'unit' => $unit->fresh(['catalog', 'specification', 'room.building']),
            'setting' => InstitutionSetting::current(),
            'qr' => 'data:image/png;base64,AAA',
        ])->render();

        $this->assertStringContainsString('CHITOSE SPESIFIKASI UJI', strtoupper($html));
        $this->assertStringContainsString('KURSI SUSUN UJI', strtoupper($html));
    }

    public function test_qr_payload_uses_stable_unit_identity_and_scan_reads_latest_location_after_mutation(): void
    {
        $unit = $this->unitWithSpecification();
        $specification = $unit->specification;

        parse_str((string) parse_url(app(LabelService::class)->unitQrPayload($unit), PHP_URL_QUERY), $payload);

        $this->assertSame((string) $unit->id, $payload['u']);
        $this->assertSame((string) $specification->id, $payload['s']);
        $this->assertSame((string) LabelService::QR_RELEASE, $payload['r']);

        $destination = $this->makeRoom(91);
        $moved = app(InventoryCodeService::class)->move($unit, $destination, $this->wakasek);

        $response = $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($moved->inventory_code).'&u='.$moved->id);

        $response->assertOk();
        $response->assertSee($destination->name);
        $response->assertSee($specification->modelLabel());
        $this->assertSame($specification->id, $moved->refresh()->specification_id);
    }

    public function test_procurement_from_an_existing_catalog_creates_a_specification_and_units_without_duplicating_the_catalog(): void
    {
        $catalog = Item::query()->approved()->whereNull('archived_at')->firstOrFail();
        $catalogCount = Item::count();
        $unitCount = AssetUnit::where('item_catalog_id', $catalog->id)->count();
        $room = $this->makeRoom(92);

        $this->actingAs($this->wakasek)->post(route('items.store'), [
            'catalog_id' => $catalog->id,
            'brand' => 'Futura Spesifikasi Uji',
            'model_type' => 'Tipe Pengadaan Uji',
            'acquisition_year' => (int) now()->year,
            'funding_source' => 'BOS',
            'quantity' => 2,
            'unit' => 'unit',
            'allocations' => [
                ['room_id' => $room->id, 'quantity' => 2],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($catalogCount, Item::count());

        $specification = Specification::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('brand', 'Futura Spesifikasi Uji')
            ->where('model_type', 'Tipe Pengadaan Uji')
            ->firstOrFail();

        $this->assertSame($unitCount + 2, AssetUnit::where('item_catalog_id', $catalog->id)->count());
        $this->assertSame(2, AssetUnit::where('specification_id', $specification->id)->count());
    }

    public function test_procurement_rejects_a_category_that_differs_from_the_selected_catalog(): void
    {
        $catalog = Item::query()->approved()->whereNull('archived_at')->firstOrFail();
        $otherCategory = Category::query()->whereKeyNot($catalog->category_id)->firstOrFail();
        $catalogCount = Item::count();
        $room = $this->makeRoom(93);

        $this->actingAs($this->wakasek)->post(route('items.store'), [
            'catalog_id' => $catalog->id,
            'category_id' => $otherCategory->id,
            'name' => 'Nama Tidak Sah',
            'acquisition_year' => (int) now()->year,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'allocations' => [
                ['room_id' => $room->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('category_id');

        $this->assertSame($catalogCount, Item::count());
    }

    public function test_student_degree_is_rejected_by_the_backend(): void
    {
        $before = $this->siswa->degree;

        $this->actingAs($this->wakasek)->put(route('users.update', $this->siswa), [
            'name' => $this->siswa->name,
            'degree' => 'S.Pd Palsu',
            'email' => $this->siswa->email,
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::SISWA->value,
            'class_name' => $this->siswa->class_name,
            'major_id' => $this->siswa->major_id,
        ])->assertSessionHasErrors('degree');

        $this->assertSame($before, $this->siswa->refresh()->degree);
    }

    public function test_teacher_and_staff_major_is_rejected_by_the_backend(): void
    {
        $before = $this->guru->major_id;
        $otherMajor = Major::query()->whereKeyNot($this->guru->major_id)->firstOrFail();

        $this->actingAs($this->wakasek)->put(route('users.update', $this->guru), [
            'name' => $this->guru->name,
            'degree' => $this->guru->degree,
            'email' => $this->guru->email,
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::GURU->value,
            'major_id' => $otherMajor->id,
        ])->assertSessionHasErrors('major_id');

        $this->assertSame($before, $this->guru->refresh()->major_id);
    }

    public function test_password_change_requires_the_correct_old_password(): void
    {
        $this->actingAs($this->siswa)->put(route('password.update'), [
            'current_password' => 'kata-sandi-salah',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertTrue(Hash::check('siswa123', $this->siswa->refresh()->password));
    }

    public function test_password_change_succeeds_with_old_password_and_matching_confirmation(): void
    {
        $this->actingAs($this->siswa)->put(route('password.update'), [
            'current_password' => 'siswa123',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertRedirect()->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('KataSandiBaru123', $this->siswa->refresh()->password));
    }

    public function test_event_pic_can_be_a_registered_account_or_a_custom_name(): void
    {
        $this->actingAs($this->guru)->post(route('events.store'), [
            'name' => 'Kegiatan dengan PIC Akun',
            'pic_mode' => 'user',
            'pic_user_id' => $this->staff->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $accountEvent = Event::where('name', 'Kegiatan dengan PIC Akun')->firstOrFail();
        $this->assertSame($this->staff->id, $accountEvent->pic_user_id);
        $this->assertSame($this->staff->displayName(), $accountEvent->pic_name);

        $this->actingAs($this->guru)->post(route('events.store'), [
            'name' => 'Kegiatan dengan PIC Khusus',
            'pic_mode' => 'custom',
            'pic_name' => 'Panitia MPLS 2026',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customEvent = Event::where('name', 'Kegiatan dengan PIC Khusus')->firstOrFail();
        $this->assertNull($customEvent->pic_user_id);
        $this->assertSame('Panitia MPLS 2026', $customEvent->pic_name);
    }

    public function test_only_the_event_creator_can_delete_an_event_without_loans(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan Tanpa Peminjaman',
            'pic_name' => $this->guru->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_by' => $this->guru->id,
        ]);

        $this->actingAs($this->staff)
            ->delete(route('events.destroy', $event))
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $this->actingAs($this->guru)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_event_with_a_loan_cannot_be_deleted_even_by_its_creator(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan dengan Peminjaman',
            'pic_name' => $this->guru->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_by' => $this->guru->id,
        ]);

        Loan::create([
            'borrower_id' => $this->siswa->id,
            'event_id' => $event->id,
            'purpose' => 'Kegiatan sekolah',
            'start_date' => now()->addDay(),
            'due_date' => now()->addDays(2),
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->guru)
            ->delete(route('events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_loan_summary_hides_optional_rows_without_values(): void
    {
        $borrower = User::create([
            'name' => 'Peminjam Tanpa Jurusan',
            'email' => 'tanpa.jurusan@gmail.com',
            'password' => 'password123',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::SISWA->value,
            'class_name' => 'X RPL 1',
            'major_id' => null,
        ]);

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'event_id' => null,
            'purpose' => 'Keperluan kelas',
            'start_date' => now()->addDay(),
            'due_date' => now()->addDays(2),
            'status' => 'PENDING',
        ]);

        $response = $this->actingAs($this->wakasek)->get(route('loans.show', $loan));

        $response->assertOk();
        $response->assertSee('Ringkasan');
        $response->assertDontSee('Jurusan:');
        $response->assertDontSee('Kegiatan:');
        $response->assertDontSee('undefined');
    }
}

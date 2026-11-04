<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\DamageReportStatus;
use App\Enums\ItemStatus;
use App\Enums\Role;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\Building;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
use App\Services\AssetUnitService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevisionV5Test extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketua;

    private User $laboran;

    private User $siswa;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->laboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
    }

    private function makeRoom(): Room
    {
        $building = Building::query()->firstOrFail();
        $roomNo = (int) Room::query()->max('room_no') + 1;

        return Room::create([
            'building_id' => $building->id,
            'floor' => 9,
            'room_no' => $roomNo,
            'code' => sprintf('V9-%02d', $roomNo),
            'name' => 'Lab Uji V5 '.$roomNo,
            'building_letter' => $building->code,
            'is_lab' => true,
        ]);
    }

    /**
     * @return array{0: Item, 1: array<int, AssetUnit>}
     */
    private function makeCatalog(Room $room, int $quantity = 2): array
    {
        $catalog = Item::create([
            'internal_id' => 'V5'.Str::upper(Str::random(6)),
            'location_code' => '12.24.13.09.01.07.01.01',
            'register_no' => (string) random_int(100000, 999999),
            'name' => 'Komputer Uji V5',
            'brand' => 'Lenovo',
            'model_type' => 'ThinkCentre',
            'category_id' => Category::query()->firstOrFail()->id,
            'room_id' => $room->id,
            'acquisition_year' => 2025,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $units = [];
        for ($i = 0; $i < $quantity; $i++) {
            $units[] = app(AssetUnitService::class)->create($catalog, $room);
        }

        return [$catalog, $units];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function loanPayload(Item $item, array $overrides = []): array
    {
        return array_merge([
            'purpose' => 'Uji peminjaman revisi V5',
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'due_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
            'borrower_mode' => 'SELF',
        ], $overrides);
    }

    private function accountAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin Akun Uji',
            'email' => 'admin.akun.uji@gmail.com',
            'role' => Role::SUPER_ADMIN->value,
        ]);
        $admin->syncRoles([Role::SUPER_ADMIN->value]);

        return $admin;
    }

    public function test_operational_roles_can_borrow_for_themselves_or_someone_else(): void
    {
        $room = $this->makeRoom();
        [$item] = $this->makeCatalog($room, 6);

        UserRoomScope::updateOrCreate(
            ['user_id' => $this->laboran->id, 'room_id' => $room->id],
            ['assigned_by' => $this->wakasek->id]
        );

        // Laboran untuk dirinya sendiri.
        $this->actingAs($this->laboran)
            ->post(route('loans.store'), $this->loanPayload($item))
            ->assertRedirect();

        $selfLoan = Loan::query()->latest('id')->firstOrFail();
        $this->assertSame($this->laboran->id, $selfLoan->borrower_id);
        $this->assertSame($this->laboran->id, $selfLoan->requested_by);
        $this->assertSame('SELF', $selfLoan->borrower_mode->value);

        // Laboran membuat transaksi untuk siswa (tanpa akun baru).
        $this->actingAs($this->laboran)
            ->post(route('loans.store'), $this->loanPayload($item, [
                'borrower_mode' => 'OTHER',
                'borrower_name' => 'Siswa Pinjam Manual',
                'borrower_category' => 'SISWA',
                'borrower_class' => 'X RPL 1',
            ]))
            ->assertRedirect();

        $otherLoan = Loan::query()->latest('id')->firstOrFail();
        $this->assertSame($this->laboran->id, $otherLoan->requested_by);
        $this->assertNull($otherLoan->borrower_id);
        $this->assertSame('Siswa Pinjam Manual', $otherLoan->borrower_name);
        $this->assertSame('OTHER', $otherLoan->borrower_mode->value);

        // Wakasek dan Ketua Laboran juga dapat meminjamkan untuk pihak lain.
        foreach ([$this->wakasek, $this->ketua] as $actor) {
            $this->actingAs($actor)
                ->post(route('loans.store'), $this->loanPayload($item, [
                    'borrower_mode' => 'OTHER',
                    'borrower_name' => 'Pihak Lain '.$actor->id,
                ]))
                ->assertRedirect();

            $loan = Loan::query()->latest('id')->firstOrFail();
            $this->assertSame($actor->id, $loan->requested_by);
            $this->assertSame('Pihak Lain '.$actor->id, $loan->borrower_name);
        }
    }

    public function test_borrower_role_cannot_borrow_for_someone_else_even_by_manipulating_request(): void
    {
        $room = $this->makeRoom();
        [$item] = $this->makeCatalog($room);

        $this->actingAs($this->siswa)
            ->post(route('loans.store'), $this->loanPayload($item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $before = Loan::query()->count();

        $this->actingAs($this->siswa)
            ->post(route('loans.store'), $this->loanPayload($item, [
                'borrower_mode' => 'OTHER',
                'borrower_name' => 'Orang Lain Palsu',
                'borrower_user_id' => $this->guru->id,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('borrower_mode');

        $this->assertSame($before, Loan::query()->count());
        $this->assertDatabaseMissing('loans', ['borrower_name' => 'Orang Lain Palsu']);
    }

    public function test_loan_detail_shows_creator_and_actual_borrower_separately(): void
    {
        $room = $this->makeRoom();
        [$item] = $this->makeCatalog($room);

        UserRoomScope::updateOrCreate(
            ['user_id' => $this->laboran->id, 'room_id' => $room->id],
            ['assigned_by' => $this->wakasek->id]
        );

        $this->actingAs($this->laboran)->post(route('loans.store'), $this->loanPayload($item, [
            'borrower_mode' => 'OTHER',
            'borrower_name' => $this->guru->displayName(),
            'borrower_user_id' => $this->guru->id,
        ]))->assertRedirect();

        $loan = Loan::query()->latest('id')->firstOrFail();

        $this->actingAs($this->laboran)
            ->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Dibuat oleh')
            ->assertSee($this->laboran->displayName())
            ->assertSee('Dipinjam oleh')
            ->assertSee($this->guru->displayName());
    }

    public function test_pdf_and_excel_use_the_same_per_unit_detail_and_excel_preview_matches(): void
    {
        $data = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $this->wakasek);
        $tables = MonthlyReportExport::tables($data);

        $unitTable = $tables[0];
        $this->assertSame('Unit', $unitTable['title']);
        $this->assertCount($data['units']->count(), $unitTable['rows']);
        $this->assertContains('Kode Inventaris', $unitTable['headings']);
        $this->assertContains('Spesifikasi', $unitTable['headings']);

        $codes = array_column($unitTable['rows'], 0);
        foreach ($data['units']->take(3) as $unit) {
            $this->assertContains($unit->inventory_code, $codes);
        }

        $html = view('pdf.report', $data)->render();
        $this->assertStringContainsString('Kode Inventaris', $html);
        $this->assertStringContainsString('Dibuat Oleh', $html);
        $this->assertStringContainsString($data['units']->first()->inventory_code, $html);

        $this->actingAs($this->wakasek)
            ->get(route('reports.preview-excel', ['period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pratinjau Excel')
            ->assertSee($data['units']->first()->inventory_code)
            ->assertSee('Kode Inventaris');
    }

    public function test_import_template_is_downloadable_and_wrong_headers_are_rejected(): void
    {
        $response = $this->actingAs($this->wakasek)->get(route('reports.import.template'));
        $response->assertOk();
        $this->assertStringContainsString('kode_inventaris', $response->streamedContent());

        $bad = UploadedFile::fake()->createWithContent('salah-header.csv', "foo,bar\n1,2\n");

        $this->actingAs($this->wakasek)->post(route('reports.import.preview'), [
            'file' => $bad,
            'mode' => 'update',
        ])->assertSessionHasErrors('file');
    }

    public function test_damage_photos_and_history_are_visible_on_unit_detail_with_scoped_file_access(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom();
        [, $units] = $this->makeCatalog($room, 1);
        $unit = $units[0];
        $photoPath = 'damage-reports/unit-'.$unit->id.'.jpg';
        Storage::disk('local')->put($photoPath, 'fake-image-bytes');

        DamageReport::create([
            'asset_unit_id' => $unit->id,
            'reported_by' => $this->wakasek->id,
            'classification' => DamageClassification::RUSAK_RINGAN->value,
            'previous_condition' => Condition::BAIK->value,
            'reported_condition' => Condition::RUSAK_RINGAN->value,
            'description' => 'Kipas pendingin berbunyi kasar.',
            'photo_path' => $photoPath,
            'status' => DamageReportStatus::DILAPORKAN->value,
        ]);

        $this->actingAs($this->wakasek)
            ->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee('Riwayat Kerusakan dan Dokumentasi')
            ->assertSee('Kipas pendingin berbunyi kasar.');

        $this->actingAs($this->wakasek)
            ->get(route('files.show', ['path' => $photoPath]))
            ->assertOk();

        $this->actingAs($this->siswa)
            ->get(route('files.show', ['path' => $photoPath]))
            ->assertForbidden();
    }

    public function test_damage_report_accepts_multiple_photos_and_keeps_them_on_unit_detail(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom();
        [$item, $units] = $this->makeCatalog($room, 1);
        $unit = $units[0];

        UserRoomScope::updateOrCreate(
            ['user_id' => $this->laboran->id, 'room_id' => $room->id],
            ['assigned_by' => $this->wakasek->id]
        );

        $this->actingAs($this->laboran)->post(route('damage-reports.store'), [
            'asset_unit_id' => $unit->id,
            'classification' => DamageClassification::RUSAK_RINGAN->value,
            'description' => 'Dua foto bukti: kipas dan kabel power.',
            'photos' => [
                UploadedFile::fake()->image('bukti-kipas.jpg', 640, 480),
                UploadedFile::fake()->image('bukti-kabel.jpg', 640, 480),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report = DamageReport::query()->latest('id')->firstOrFail();

        $this->assertCount(2, $report->photos);
        $this->assertNotNull($report->photo_path);

        foreach ($report->photos as $photo) {
            Storage::disk('local')->assertExists($photo->path);
        }

        $this->actingAs($this->wakasek)
            ->get(route('units.show', $unit))
            ->assertOk()
            ->assertSee('Riwayat Kerusakan dan Dokumentasi')
            ->assertSee('Dua foto bukti: kipas dan kabel power.')
            ->assertSee(route('files.show', ['path' => $report->photos->first()->path]), false)
            ->assertSee(route('files.show', ['path' => $report->photos->last()->path]), false);

        // Laporan lama (beserta fotonya) tidak tertimpa oleh laporan baru pada unit yang sama.
        $unit->forceFill(['condition' => Condition::BAIK->value])->save();
        $report->forceFill(['status' => DamageReportStatus::SELESAI->value])->save();

        $this->actingAs($this->laboran)->post(route('damage-reports.store'), [
            'asset_unit_id' => $unit->id,
            'classification' => DamageClassification::RUSAK_BERAT->value,
            'description' => 'Kerusakan baru setelah laporan pertama selesai.',
            'photos' => [UploadedFile::fake()->image('bukti-baru.jpg', 640, 480)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $report->fresh()->photos()->count());
        $this->assertSame(2, DamageReport::query()->count());
    }

    public function test_only_super_admin_pengguna_can_access_account_administration(): void
    {
        $admin = $this->accountAdmin();

        $this->actingAs($admin)->get(route('users.index'))->assertOk();

        foreach ([$this->wakasek, $this->ketua, $this->laboran, $this->siswa] as $roleUser) {
            $this->actingAs($roleUser)->get(route('users.index'))->assertForbidden();
        }

        // Role ini tidak memperoleh transaksi inventaris.
        $this->actingAs($admin)->get(route('loans.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_account_admin_cannot_escalate_role_or_touch_super_admin(): void
    {
        $admin = $this->accountAdmin();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'PasswordKuat123',
            'password_confirmation' => 'PasswordKuat123',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.test']);

        $this->actingAs($admin)->get(route('users.show', $this->wakasek))->assertForbidden();
        $this->actingAs($admin)->post(route('users.status', $this->wakasek))->assertForbidden();

        $this->assertTrue($this->wakasek->refresh()->isActive());
    }

    public function test_bootstrap_account_is_hashed_forced_to_change_password_and_idempotent(): void
    {
        $this->artisan('user:bootstrap-admin', [
            '--email' => 'bootstrap.admin@example.test',
            '--password' => 'AwalKuat12345!',
        ])->assertSuccessful();

        $admin = User::where('email', 'bootstrap.admin@example.test')->firstOrFail();

        $this->assertSame(Role::SUPER_ADMIN->value, $admin->role);
        $this->assertTrue(Hash::check('AwalKuat12345!', $admin->password));
        $this->assertTrue($admin->mustChangePassword());
        $this->assertTrue($admin->isActive());

        $this->artisan('user:bootstrap-admin', [
            '--email' => 'bootstrap.admin@example.test',
            '--password' => 'PasswordLain123!',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'bootstrap.admin@example.test')->count());

        $this->actingAs($admin)->get(route('users.index'))->assertRedirect(route('profile.edit'));

        $this->actingAs($admin)->put(route('password.update'), [
            'current_password' => 'AwalKuat12345!',
            'password' => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ])->assertRedirect();

        $admin->refresh();
        $this->assertFalse($admin->mustChangePassword());
        $this->assertNotNull($admin->password_changed_at);

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
    }

    public function test_bootstrap_password_can_come_from_environment_and_is_only_reset_on_request(): void
    {
        config(['auth.bootstrap_user_admin.email' => 'env.admin@example.test']);
        config(['auth.bootstrap_user_admin.password' => 'PasswordDariEnv123!']);

        $this->artisan('user:bootstrap-admin')->assertSuccessful();

        $admin = User::where('email', 'env.admin@example.test')->firstOrFail();
        $originalHash = $admin->password;

        $this->assertTrue(Hash::check('PasswordDariEnv123!', $originalHash));
        $this->assertTrue($admin->mustChangePassword());

        // Tanpa --reset-password, akun yang sudah ada tidak diubah.
        $this->artisan('user:bootstrap-admin', ['--password' => 'PasswordLain123!'])->assertSuccessful();
        $this->assertSame($originalHash, $admin->refresh()->password);

        // Dengan --reset-password, password dari env dipakai dan kewajiban ganti diaktifkan lagi.
        $admin->forceFill(['must_change_password' => false, 'password_changed_at' => now()])->save();

        $this->artisan('user:bootstrap-admin', ['--reset-password' => true])->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(Hash::check('PasswordDariEnv123!', $admin->password));
        $this->assertTrue($admin->mustChangePassword());
        $this->assertNull($admin->password_changed_at);
        $this->assertSame(1, User::where('email', 'env.admin@example.test')->count());

        // Password terlalu pendek ditolak sebelum akun dibuat.
        config(['auth.bootstrap_user_admin.email' => 'pendek@example.test']);
        config(['auth.bootstrap_user_admin.password' => 'pendek']);

        $this->artisan('user:bootstrap-admin')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'pendek@example.test']);
    }

    public function test_every_photo_of_a_damage_report_is_served_only_to_authorized_users(): void
    {
        Storage::fake('local');

        $room = $this->makeRoom();
        [$item, $units] = $this->makeCatalog($room, 1);
        $unit = $units[0];
        $report = DamageReport::create([
            'asset_unit_id' => $unit->id,
            'reported_by' => $this->wakasek->id,
            'classification' => DamageClassification::RUSAK_RINGAN->value,
            'previous_condition' => Condition::BAIK->value,
            'reported_condition' => Condition::RUSAK_RINGAN->value,
            'description' => 'Beberapa foto bukti untuk pengujian akses berkas.',
            'photo_path' => 'damage-reports/utama.jpg',
            'status' => DamageReportStatus::DILAPORKAN->value,
        ]);

        foreach (['damage-reports/utama.jpg', 'damage-reports/kedua.jpg', 'damage-reports/ketiga.jpg'] as $index => $path) {
            Storage::disk('local')->put($path, 'fake-image-bytes-'.$index);
        }

        $report->photos()->create(['path' => 'damage-reports/kedua.jpg']);
        $report->photos()->create(['path' => 'damage-reports/ketiga.jpg']);

        foreach (['damage-reports/utama.jpg', 'damage-reports/kedua.jpg', 'damage-reports/ketiga.jpg'] as $path) {
            $this->actingAs($this->wakasek)
                ->get(route('files.show', ['path' => $path]))
                ->assertOk();

            $this->actingAs($this->siswa)
                ->get(route('files.show', ['path' => $path]))
                ->assertForbidden();
        }
    }

    public function test_excel_preview_is_available_wherever_reports_are_available(): void
    {
        UserRoomScope::updateOrCreate(
            ['user_id' => $this->laboran->id, 'room_id' => $this->makeRoom()->id],
            ['assigned_by' => $this->wakasek->id]
        );

        $this->actingAs($this->laboran)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Pratinjau Excel');

        $this->actingAs($this->laboran)
            ->get(route('reports.preview-excel', ['period' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pratinjau Excel');
    }

    public function test_account_menu_is_hidden_from_super_admin_sarpras_and_import_guide_has_template_link(): void
    {
        $this->actingAs($this->wakasek)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'), false)
            ->assertDontSee('Akun Pengguna');

        $this->actingAs($this->wakasek)
            ->get(route('reports.import.form'))
            ->assertOk()
            ->assertSee('Unduh Contoh File')
            ->assertSee(route('reports.import.template'), false);
    }
}

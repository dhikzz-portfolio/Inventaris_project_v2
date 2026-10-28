<?php

namespace Tests\Feature;

use App\Enums\Decision;
use App\Enums\DocumentStatus;
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
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Revisi alur peminjaman:
 *
 * - Error HTTP 422 pada halaman serah terima karena status peminjaman tidak
 *   sama dengan `APPROVED` (akar masalah: rantai persetujuan barang bernilai
 *   tinggi menuntut Wakasek menyetujui dua kali).
 * - Persetujuan Wakasek yang sah sudah cukup — tidak ada persetujuan Admin
 *   tambahan yang berulang.
 * - Tanda tangan digital opsional; status dokumen terpisah dari status
 *   persetujuan transaksi.
 * - Wakasek dapat meminjam tanpa menunggu persetujuan Admin lain.
 */
class LoanApprovalHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $laboranTkj;

    private User $guruBudi;

    private Room $roomTkj;

    private Category $category;

    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->laboranTkj = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->guruBudi = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->roomTkj = Room::where('major_id', Major::where('code', 'TP')->value('id'))->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    private function makeCatalog(string $name, int $qty = 1, bool $highValue = false): Item
    {
        self::$sequence++;

        $catalog = Item::create([
            'internal_id' => 'REVISI-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6)).'-'.self::$sequence,
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) (8000 + self::$sequence),
            'name' => $name,
            'category_id' => $this->category->id,
            'room_id' => $this->roomTkj->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'is_high_value' => $highValue,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $service = app(AssetUnitService::class);
        for ($i = 0; $i < $qty; $i++) {
            $service->create($catalog, $this->roomTkj, [], $this->laboranTkj);
        }

        return $catalog->refresh();
    }

    private function requestLoan(User $borrower, Item $catalog, int $qty = 1, string $purpose = 'Uji alur'): Loan
    {
        return app(LoanService::class)->requestLoan(
            $borrower,
            [['item_id' => $catalog->id, 'qty' => $qty]],
            $purpose,
            '2027-03-01 08:00',
            '2027-03-05 16:00'
        );
    }

    // ================= SERAH TERIMA =================

    public function test_peminjaman_disetujui_dapat_masuk_ke_proses_serah_terima(): void
    {
        $catalog = $this->makeCatalog('Kursi Disetujui', 2);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->assertSame(LoanStatus::PENDING, $loan->status);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan), ['note' => 'Disetujui laboran'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertNull($loan->handoverBlocker());

        // Halaman serah terima harus dapat dibuka tanpa error 422.
        $this->actingAs($this->laboranTkj)
            ->get(route('loans.handover-form', $loan))
            ->assertOk();

        $loanItem = $loan->loanItems()->firstOrFail();
        $units = $catalog->units()->orderBy('inventory_code')->take(1)->pluck('id')->all();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $units]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(LoanStatus::DIPINJAM, $loan->refresh()->status);
    }

    public function test_peminjaman_belum_disetujui_ditolak_dengan_pesan_yang_jelas_bukan_exception(): void
    {
        $catalog = $this->makeCatalog('Kursi Menunggu', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        // Halaman serah terima: redirect dengan pesan, bukan halaman 422 mentah.
        $this->actingAs($this->laboranTkj)
            ->get(route('loans.handover-form', $loan))
            ->assertRedirect(route('loans.show', $loan))
            ->assertSessionHasErrors('handover');

        // Aksi serah terima langsung tetap ditolak.
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan))
            ->assertRedirect()
            ->assertSessionHasErrors('handover');

        $loan->refresh();
        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertNull($loan->handed_over_at);
    }

    public function test_persetujuan_sah_tidak_gagal_akibat_perbedaan_format_status(): void
    {
        $catalog = $this->makeCatalog('Kursi Format Status', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();

        // Nilai yang tersimpan di database harus identik dengan enum yang
        // dibandingkan controller/service.
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertSame('APPROVED', $loan->getRawOriginal('status'));
        $this->assertSame(LoanStatus::APPROVED->value, $loan->status->value);
        $this->assertTrue($loan->isFullyApproved());
    }

    public function test_serah_terima_tidak_dapat_dilakukan_dua_kali(): void
    {
        $catalog = $this->makeCatalog('Kursi Ganda', 2);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan))
            ->assertRedirect();

        $loanItem = $loan->refresh()->loanItems()->firstOrFail();
        $units = $catalog->units()->orderBy('inventory_code')->take(1)->pluck('id')->all();

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $units]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $handedOverAt = $loan->refresh()->handed_over_at;

        // Permintaan kedua harus ditolak tanpa merusak datayer handover pertama.
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $units]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('handover');

        $loan->refresh();
        $this->assertSame(LoanStatus::DIPINJAM, $loan->status);
        $this->assertTrue($handedOverAt->equalTo($loan->handed_over_at));
        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
    }

    public function test_transisi_status_yang_tidak_diperbolehkan_ditolak(): void
    {
        $catalog = $this->makeCatalog('Kursi Ditolak', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.reject', $loan), ['note' => 'Barang dipakai kegiatan lain'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::DITOLAK, $loan->status);

        $this->actingAs($this->laboranTkj)
            ->get(route('loans.handover-form', $loan))
            ->assertRedirect(route('loans.show', $loan))
            ->assertSessionHasErrors('handover');

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.handover', $loan))
            ->assertRedirect()
            ->assertSessionHasErrors('handover');

        $this->assertSame(LoanStatus::DITOLAK, $loan->refresh()->status);
    }

    // ================= PERSETUJUAN BARANG NILAI TINGGI =================

    public function test_persetujuan_wakasek_memenuhi_aturan_tanpa_admin_tambahan(): void
    {
        $catalog = $this->makeCatalog('Laptop Nilai Tinggi', 1, true);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->assertTrue($loan->needs_high_value_approval);
        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertSame(2, $loan->finalApprovalLevel());

        // Satu persetujuan Wakasek harus langsung menutup rantai persetujuan.
        $this->actingAs($this->wakasek)
            ->post(route('loans.approve', $loan), ['note' => 'Persetujuan Wakasek'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();

        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertTrue($loan->isFullyApproved());
        $this->assertNull($loan->nextApprovalLevel());
        $this->assertNull($loan->handoverBlocker());
        $this->assertCount(1, $loan->approvals);

        $approval = $loan->approvals->firstOrFail();
        $this->assertSame($this->wakasek->id, $approval->approver_id);
        $this->assertSame(2, $approval->level);
        $this->assertSame(Decision::APPROVED, $approval->decision);
    }

    public function test_persetujuan_dari_role_lain_tetap_mengikuti_alur_berjenjang(): void
    {
        $catalog = $this->makeCatalog('Laptop Berjenjang', 1, true);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        // Persetujuan level 1 oleh Laboran belum cukup untuk barang bernilai tinggi.
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan), ['note' => 'Disetujui laboran'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertSame(2, $loan->nextApprovalLevel());

        // Laboran tidak berwenang memutuskan level 2.
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan), ['note' => 'Mencoba level 2'])
            ->assertRedirect()
            ->assertSessionHasErrors('decision');

        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);
    }

    public function test_audit_log_mencatat_aktor_dan_keputusan_yang_benar(): void
    {
        $catalog = $this->makeCatalog('Laptop Audit', 1, true);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->wakasek)
            ->post(route('loans.approve', $loan), ['note' => 'Persetujuan Wakasek'])
            ->assertRedirect();

        $activity = Activity::query()
            ->where('subject_type', Loan::class)
            ->where('subject_id', $loan->id)
            ->where('description', 'like', 'Menyetujui%')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->wakasek->id, (int) $activity->causer_id);
        $this->assertStringContainsString('level 2', $activity->description);

        $approval = $loan->refresh()->approvals()->firstOrFail();
        $this->assertSame($this->wakasek->id, $approval->approver_id);
        $this->assertNotNull($approval->decided_at);
    }

    public function test_tanda_tangan_digital_kosong_tidak_menggagalkan_alur(): void
    {
        $catalog = $this->makeCatalog('Laptop Tanpa TTD', 1, true);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->wakasek)
            ->post(route('loans.approve', $loan), ['note' => 'Tanpa tanda tangan digital'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::APPROVED, $loan->status);

        $approval = $loan->approvals()->firstOrFail();
        $this->assertNull($approval->signature_path);
    }

    // ================= STATUS DOKUMEN =================

    public function test_dokumen_dapat_dicetak_dan_ditandatangani_secara_fisik(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Dokumen', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan))
            ->assertRedirect();

        $loan->refresh();

        // Dokumen resmi dibuat & dipratinjau.
        $this->actingAs($this->laboranTkj)
            ->get(route('loans.bast', $loan))
            ->assertOk();

        $loan->refresh();
        $this->assertNotNull($loan->bast_path);
        $this->assertSame(DocumentStatus::SIAP_DICETAK, $loan->document_status);

        // Dicetak, lalu ditandatangani secara fisik oleh petugas.
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.document.printed', $loan))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::DICETAK, $loan->refresh()->document_status);

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.document.signed-physical', $loan))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(DocumentStatus::DITANDATANGANI_FISIK, $loan->document_status);

        // Status dokumen tidak boleh mengubah status persetujuan transaksi.
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
    }

    public function test_status_dokumen_tidak_tertukar_dengan_status_persetujuan(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi Pemisahan Status', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)->post(route('loans.approve', $loan));
        $this->actingAs($this->laboranTkj)->get(route('loans.bast', $loan));

        $loan->refresh();
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertNotSame($loan->status->value, $loan->document_status->value);
    }

    public function test_status_ditandatangani_digital_hanya_boleh_bila_ada_ttd_digital(): void
    {
        Storage::fake('local');

        $catalog = $this->makeCatalog('Kursi TTD Digital', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->laboranTkj)->post(route('loans.approve', $loan));
        $this->actingAs($this->laboranTkj)->get(route('loans.bast', $loan));

        $this->actingAs($this->laboranTkj)
            ->post(route('loans.document.signed-digital', $loan))
            ->assertRedirect()
            ->assertSessionHasErrors('document');

        $this->assertNotSame(
            DocumentStatus::DITANDATANGANI_DIGITAL,
            $loan->refresh()->document_status
        );
    }

    // ================= WAKASEK =================

    public function test_wakasek_dapat_meminjam_tanpa_persetujuan_admin_tambahan(): void
    {
        $catalog = $this->makeCatalog('Laptop Wakasek', 2, true);
        $loan = $this->requestLoan($this->wakasek, $catalog, 1, 'Penggunaan internal wakasek');

        // Transaksi langsung disetujui atas kewenangan sendiri.
        $this->assertSame(LoanStatus::APPROVED, $loan->status);
        $this->assertTrue($loan->isFullyApproved());
        $this->assertNull($loan->handoverBlocker());

        // Persetujuan tercatat atas nama Wakasek sendiri — bukan dipalsukan atas nama Admin lain.
        $approval = $loan->approvals()->firstOrFail();
        $this->assertSame($this->wakasek->id, $approval->approver_id);
        $this->assertSame($loan->finalApprovalLevel(), $approval->level);

        // Penyerahan tetap mencatat unit fisik dan memperbarui status unit.
        $loanItem = $loan->loanItems()->firstOrFail();
        $units = $catalog->units()->orderBy('inventory_code')->take(1)->pluck('id')->all();

        $this->actingAs($this->wakasek)
            ->post(route('loans.handover', $loan), [
                'unit_assignments' => [$loanItem->id => ['unit_ids' => $units]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame(LoanStatus::DIPINJAM, $loan->status);
        $this->assertSame($this->wakasek->id, $loan->handed_over_by);

        $unit = $catalog->units()->whereIn('id', $units)->firstOrFail();
        $this->assertSame(UnitStatus::DIPINJAM, $unit->refresh()->status);
        $this->assertSame(1, LoanItemUnit::where('loan_item_id', $loanItem->id)->count());
    }

    public function test_pengguna_biasa_tidak_dapat_memanfaatkan_jalur_khusus_wakasek(): void
    {
        $catalog = $this->makeCatalog('Laptop Guru', 1, true);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->assertSame(LoanStatus::PENDING, $loan->status);
        $this->assertFalse($loan->isFullyApproved());
        $this->assertNotNull($loan->handoverBlocker());

        // Mengirim role lewat parameter tidak memberi kewenangan apa pun.
        $this->actingAs($this->guruBudi)
            ->post(route('loans.approve', $loan), [
                'note' => 'Mengaku wakasek',
                'role' => 'SUPER_ADMIN',
            ])
            ->assertForbidden();
    }

    public function test_pengguna_biasa_tidak_dapat_menandai_dokumen_tandatangan_fisik(): void
    {
        $catalog = $this->makeCatalog('Kursi Dokumen Guru', 1);
        $loan = $this->requestLoan($this->guruBudi, $catalog);

        $this->actingAs($this->guruBudi)
            ->post(route('loans.document.signed-physical', $loan))
            ->assertForbidden();
    }

    public function test_wakasek_mengakses_halaman_inventaris_penuh(): void
    {
        $routes = [
            'catalogs.index',
            'masters.index',
            'labels.index',
            'reports.index',
            'movements.index',
            'write-offs.index',
            'write-offs.pending',
            'items.acquisitions',
            'loans.pending',
            'loans.returns',
            'users.index',
            'settings.edit',
        ];

        foreach ($routes as $routeName) {
            $this->actingAs($this->wakasek)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_role_bukan_wakasek_tidak_dapat_membuka_halaman_khusus_wakasek(): void
    {
        $adminSarpras = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();

        foreach (['users.index', 'settings.edit', 'write-offs.pending', 'items.acquisitions'] as $routeName) {
            $this->actingAs($adminSarpras)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }
}

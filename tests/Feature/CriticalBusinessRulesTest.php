<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Category;
use App\Models\Item;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use App\Services\IdGenerator;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CriticalBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private User $laboranTkj;

    private User $laboranRpl;

    private User $guruBudi;

    private Item $highValueItem;

    private Item $normalItem;

    private Room $roomTkj;

    private Room $roomRpl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->admin = User::where('username', 'admin.sarpras')->firstOrFail();
        $this->laboranTkj = User::where('username', 'laboran.tkj')->firstOrFail();
        $this->laboranRpl = User::where('username', 'laboran.rpl')->firstOrFail();
        $this->guruBudi = User::where('username', 'guru.budi')->firstOrFail();

        $majorTkj = Major::where('code', 'TKJ')->firstOrFail();
        $majorRpl = Major::where('code', 'RPL')->firstOrFail();

        $this->roomTkj = Room::where('major_id', $majorTkj->id)->firstOrFail();
        $this->roomRpl = Room::where('major_id', $majorRpl->id)->firstOrFail();

        $category = Category::firstOrFail();

        $this->highValueItem = Item::create([
            'internal_id' => 'CAM-G1-01-99',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.16.03.01',
            'register_no' => '9901',
            'name' => 'Kamera DSLR Sony Alpha',
            'category_id' => $category->id,
            'room_id' => $this->roomTkj->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'is_high_value' => true,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $this->normalItem = Item::create([
            'internal_id' => 'MIC-G1-01-98',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
            'register_no' => '9902',
            'name' => 'Microphone Wireless',
            'category_id' => $category->id,
            'room_id' => $this->roomTkj->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'is_high_value' => false,
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    /**
     * R1: Kuantitas kembali < kuantitas pinjam
     * Peminjaman tidak bisa berstatus SELESAI; selisih otomatis diberi flag HILANG.
     */
    public function test_r1_kuantitas_kembali_kurang_dari_pinjam_flag_hilang_dan_tidak_selesai(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Uji R1 Kuantitas',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);
        $loanItem = $loan->loanItems()->firstOrFail();

        $service->processReturn($loan, $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 0,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $this->normalItem->room_id,
        ]]);

        $this->assertSame(ItemStatus::HILANG, $this->normalItem->refresh()->status);
        $this->assertNotSame(LoanStatus::SELESAI, $loan->refresh()->status);
    }

    /**
     * R2: Barang dikembalikan ke ruangan yang salah
     * Validasi ID barang saat check-in; mutasi tercatat dan ID internal berubah.
     */
    public function test_r2_barang_dikembalikan_ke_ruangan_berbeda_memicu_mutasi_lokasi_dan_update_id(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Uji R2 Lokasi',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);
        $loanItem = $loan->loanItems()->firstOrFail();

        $oldInternalId = $this->normalItem->internal_id;
        $targetRoom = $this->roomRpl;

        $service->processReturn($loan, $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::BAIK->value,
            'returned_room_id' => $targetRoom->id,
        ]]);

        $this->normalItem->refresh();
        $this->assertSame($targetRoom->id, $this->normalItem->room_id);
        $this->assertNotSame($oldInternalId, $this->normalItem->internal_id);
        $this->assertStringStartsWith(IdGenerator::roomPrefix($targetRoom), $this->normalItem->internal_id);
    }

    /**
     * R3: Kondisi akhir wajib diisi
     * Jika "Rusak": wajib foto bukti, dan sistem mencatat riwayat peminjam terakhir.
     */
    public function test_r3_kondisi_rusak_wajib_foto_dan_catat_riwayat_peminjam_terakhir(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $service = app(LoanService::class);

        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Uji R3 Rusak',
            '2026-11-01',
            '2026-11-03'
        );
        $loan->update(['status' => LoanStatus::DIPINJAM]);
        $loanItem = $loan->loanItems()->firstOrFail();

        // 1) Rusak tanpa foto harus gagal (HttpException 422)
        try {
            $service->processReturn($loan, $this->laboranTkj, [[
                'loan_item_id' => $loanItem->id,
                'qty_returned' => 1,
                'condition_in' => Condition::RUSAK_RINGAN->value,
                'returned_room_id' => $this->normalItem->room_id,
            ]]);
            $this->fail('Harus gagal jika kondisi rusak tanpa foto');
        } catch (ValidationException $e) {
            $this->assertTrue($e->validator->errors()->has('rows'));
        }

        // 2) Rusak dengan foto harus sukses dan mencatat riwayat peminjam terakhir
        $photo = UploadedFile::fake()->image('bukti_rusak.jpg');
        $service->processReturn($loan, $this->laboranTkj, [[
            'loan_item_id' => $loanItem->id,
            'qty_returned' => 1,
            'condition_in' => Condition::RUSAK_RINGAN->value,
            'returned_room_id' => $this->normalItem->room_id,
            'damage_photo' => $photo,
        ]]);

        $this->assertSame(ItemStatus::RUSAK_RINGAN, $this->normalItem->refresh()->status);
        $this->assertSame($this->guruBudi->id, $this->normalItem->lastBorrower()?->id);
    }

    /**
     * R4: Jadwal bentrok
     * Request ditolak validasi bila rentang tanggal beririsan dengan peminjaman aktif/ter-booking pada barang yang sama.
     */
    public function test_r4_jadwal_bentrok_ditolak_validasi(): void
    {
        $service = app(LoanService::class);

        // Booking 1: 10 Nov - 15 Nov
        $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Booking Pertama',
            '2026-11-10',
            '2026-11-15'
        );

        // Booking 2: 12 Nov - 18 Nov (beririsan) -> Harus AvailabilityException
        $this->expectException(AvailabilityException::class);
        $service->requestLoan(
            $this->wakasek,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Booking Bentrok',
            '2026-11-12',
            '2026-11-18'
        );
    }

    /**
     * R5: Label pudar/rusak
     * Re-print label tersedia di halaman detail barang dan menghasilkan PDF.
     */
    public function test_r5_reprint_label_tersedia_di_detail_dan_menghasilkan_pdf(): void
    {
        // Akses detail barang melihat tombol re-print label
        $this->actingAs($this->laboranTkj)
            ->get(route('items.show', $this->normalItem))
            ->assertOk()
            ->assertSee(route('items.label', $this->normalItem));

        // Unduh label PDF langsung
        $this->actingAs($this->laboranTkj)
            ->get(route('items.label', $this->normalItem))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * R6: Barang is_high_value
     * Wajib approval level 1 (Laboran) lalu level 2 (Wakasek); status tidak boleh DIPINJAM sebelum semua APPROVED.
     */
    public function test_r6_barang_high_value_wajib_approval_berjenjang_level_1_dan_level_2(): void
    {
        $service = app(LoanService::class);
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->highValueItem->id, 'qty' => 1]],
            'Pinjam Kamera High Value',
            '2026-11-01',
            '2026-11-03'
        );

        $this->assertTrue($loan->needs_high_value_approval);
        $this->assertSame(LoanStatus::PENDING, $loan->status);

        // Approval level 1 oleh laboran
        $service->decide($loan, $this->laboranTkj, Decision::APPROVED, 'Disetujui laboran');
        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);
        $this->assertSame(2, $loan->nextApprovalLevel());

        // Approval level 2 oleh Wakasek
        $service->decide($loan, $this->wakasek, Decision::APPROVED, 'Disetujui wakasek');
        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);
        $this->assertNull($loan->nextApprovalLevel());
    }

    /**
     * R7: Write-off (Penghapusan inventaris)
     * Wajib Berita Acara; hanya efektif setelah APPROVED oleh Wakasek.
     */
    public function test_r7_write_off_wajib_berita_acara_dan_approval_wakasek(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Permohonan write-off oleh Admin Sarpras
        $file = UploadedFile::fake()->create('berita_acara.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('write-offs.store'), [
                'item_id' => $this->normalItem->id,
                'quantity' => 1,
                'reason' => 'Barang rusak total tidak bisa diperbaiki',
                'berita_acara' => $file,
            ])
            ->assertRedirect(route('write-offs.index'));

        $writeOff = WriteOff::where('item_id', $this->normalItem->id)->firstOrFail();
        $this->assertSame('PENDING', $writeOff->status);

        // Sebelum di-approve Wakasek, status barang masih tetap
        $this->assertNotSame(ItemStatus::DIHAPUS, $this->normalItem->refresh()->status);

        // Approval oleh Wakasek
        $this->actingAs($this->wakasek)
            ->post(route('write-offs.approve', $writeOff))
            ->assertRedirect();

        $this->assertSame('APPROVED', $writeOff->refresh()->status);
        $this->assertSame(ItemStatus::DIHAPUS, $this->normalItem->refresh()->status);
        $this->assertSame(0, $this->normalItem->quantity);
    }

    /**
     * R8: Laboran lintas jurusan
     * Ditolak (403) saat mencoba menyetujui peminjaman di luar jurusannya.
     */
    public function test_r8_laboran_lintas_jurusan_ditolak_403(): void
    {
        $service = app(LoanService::class);

        // Barang ada di ruangan TKJ ($this->roomTkj)
        $loan = $service->requestLoan(
            $this->guruBudi,
            [['item_id' => $this->normalItem->id, 'qty' => 1]],
            'Pinjam alat TKJ',
            '2026-11-20',
            '2026-11-22'
        );

        // Laboran RPL mencoba approve peminjaman alat TKJ -> Harus 403 Forbidden
        $this->actingAs($this->laboranRpl)
            ->post(route('loans.approve', $loan))
            ->assertForbidden();

        // Sebaliknya, Laboran TKJ berhak mengakses dan menyetujui
        $this->actingAs($this->laboranTkj)
            ->post(route('loans.approve', $loan))
            ->assertRedirect();
    }

    /**
     * R9: Audit log
     * Tidak bisa dihapus atau diubah dari UI maupun kode/API.
     */
    public function test_r9_audit_log_tidak_bisa_dihapus_atau_diubah(): void
    {
        activity('test')
            ->performedOn($this->normalItem)
            ->causedBy($this->admin)
            ->log('Pencatatan aktivitas uji coba');

        $activity = Activity::latest('id')->firstOrFail();

        // 1) Percobaan update harus melempar DomainException
        try {
            $activity->update(['description' => 'Aktivitas diubah']);
            $this->fail('Audit log seharusnya tidak bisa diubah');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 2) Percobaan delete harus melempar DomainException
        try {
            $activity->delete();
            $this->fail('Audit log seharusnya tidak bisa dihapus');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }
}

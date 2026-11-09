<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AcquisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $laboran;

    private User $ketua;

    private User $wakasek;

    private Category $category;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboran = User::where('email', 'laboran.rpl@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
        $this->room = Room::where('code', 'H1-01')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Proyektor Uji Alur '.uniqid(),
            'category_id' => $this->category->id,
            'specification' => 'Spesifikasi pengadaan tiga tahap',
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 2,
            'unit' => 'unit',
            'allocations' => [[
                'room_id' => $this->room->id,
                'quantity' => 2,
            ]],
        ], $overrides);
    }

    private function createDraft(): Item
    {
        $payload = $this->payload();

        $this->actingAs($this->laboran)
            ->post(route('items.store'), $payload)
            ->assertRedirect(route('items.acquisitions'))
            ->assertSessionHasNoErrors();

        return Item::where('name', $payload['name'])->firstOrFail();
    }

    private function toWaitingApproval(Item $item): void
    {
        $this->actingAs($this->ketua)->post(route('items.acquisitions.confirm', $item))->assertRedirect();
    }

    public function test_search_only_shows_matching_acquisitions(): void
    {
        $matchingItem = $this->createDraft();
        $this->createDraft();

        $response = $this->actingAs($this->laboran)
            ->get(route('items.acquisitions', ['search' => $matchingItem->name]));

        $response->assertOk()
            ->assertViewHas('search', $matchingItem->name)
            ->assertViewHas('groups', fn ($groups): bool => $groups->flatten()->pluck('id')->all() === [$matchingItem->id]);
    }

    public function test_search_shows_empty_state_when_no_acquisitions_match(): void
    {
        $this->createDraft();

        $this->actingAs($this->laboran)
            ->get(route('items.acquisitions', ['search' => 'Tidak Ada Barang Ini']))
            ->assertOk()
            ->assertViewHas('groups', fn ($groups): bool => $groups->isEmpty())
            ->assertSeeText('Pengadaan tidak ditemukan');
    }

    public function test_search_does_not_show_acquisitions_outside_laboran_scope(): void
    {
        $item = $this->createDraft();
        $laboranDkv = User::where('email', 'laboran.dkv@gmail.com')->firstOrFail();

        $this->actingAs($laboranDkv)
            ->get(route('items.acquisitions', ['search' => $item->name]))
            ->assertOk()
            ->assertViewHas('groups', fn ($groups): bool => $groups->isEmpty());
    }

    public function test_search_rejects_keywords_longer_than_100_characters(): void
    {
        $this->actingAs($this->laboran)
            ->get(route('items.acquisitions', ['search' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('search');
    }

    public function test_alur_tiga_tahap_barang_tercatat_setelah_diterima(): void
    {
        $item = $this->createDraft();

        // Tahap 1 — pengajuan Operator langsung masuk antrean validasi.
        $this->assertSame('MENUNGGU_KONFIRMASI', $item->acquisition_status);
        $this->assertNotNull($item->acquisition_submitted_at);
        $this->assertSame(0, $item->units()->count());
        $this->assertSame(Role::OPERATOR->value, $item->requester->role);
        $this->assertTrue(AppNotification::where('user_id', $this->ketua->id)
            ->where('type', 'PENGADAAN')->exists());

        // Laboran tidak boleh mengonfirmasi miliknya sendiri.
        $this->actingAs($this->laboran)
            ->post(route('items.acquisitions.confirm', $item))
            ->assertForbidden();

        // Tahap 2 — konfirmasi Ketua Laboran (bukan persetujuan akhir).
        $this->actingAs($this->ketua)
            ->post(route('items.acquisitions.confirm', $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('MENUNGGU_PERSETUJUAN', $item->acquisition_status);
        $this->assertSame($this->ketua->id, $item->acquisition_confirmed_by);
        $this->assertNotNull($item->acquisition_confirmed_at);

        // Ketua Laboran tidak boleh menggantikan keputusan Wakasek.
        $this->actingAs($this->ketua)->post(route('items.approve', $item))->assertForbidden();

        // Tahap 3 — persetujuan Wakasek; unit belum terbit.
        $this->actingAs($this->wakasek)
            ->post(route('items.approve', $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('DISETUJUI', $item->acquisition_status);
        $this->assertSame($this->wakasek->id, $item->acquisition_approved_by);
        $this->assertSame(0, $item->units()->count());

        // Proses pengadaan & penerimaan aktual (1 dari 2 unit).
        $this->actingAs($this->laboran)->post(route('items.acquisitions.start', $item))->assertRedirect();
        $this->assertSame('DALAM_PROSES', $item->refresh()->acquisition_status);

        $this->actingAs($this->laboran)->post(route('items.acquisitions.receive', $item), [
            'received' => [$this->room->id => 1],
            'condition' => 'BAIK',
            'note' => 'Satu unit belum dikirim',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('BARANG_DITERIMA', $item->acquisition_status);
        $this->assertSame(1, $item->acquisition_received_quantity);
        $this->assertSame(1, (int) $item->allocations()->first()->received_quantity);
        $this->assertSame(0, $item->units()->count());

        // Pencatatan inventaris resmi.
        $this->actingAs($this->laboran)
            ->post(route('items.acquisitions.record', $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('INVENTARIS_TERCATAT', $item->acquisition_status);
        $this->assertSame(1, $item->units()->count());
        $this->assertSame(1, (int) $item->quantity);

        // Audit trail mencatat setiap transisi beserta status sebelum/sesudah.
        $logs = Activity::where('log_name', 'pengadaan')->get();
        $this->assertGreaterThanOrEqual(6, $logs->count());

        $approveLog = $logs->first(fn (Activity $log) => ($log->properties['aksi'] ?? null) === 'Menyetujui pengajuan pengadaan (belum menjadi inventaris)');
        $this->assertNotNull($approveLog);
        $this->assertSame('MENUNGGU_PERSETUJUAN', $approveLog->properties['status_sebelum']);
        $this->assertSame('DISETUJUI', $approveLog->properties['status_sesudah']);
        $this->assertSame(Role::ADMIN->value, $approveLog->properties['role']);
    }

    public function test_penolakan_wajib_alasan_dan_ditinjau_kembali(): void
    {
        $item = $this->createDraft();
        $this->toWaitingApproval($item);

        $this->actingAs($this->wakasek)
            ->post(route('items.reject', $item), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame('MENUNGGU_PERSETUJUAN', $item->refresh()->acquisition_status);

        $this->actingAs($this->wakasek)
            ->post(route('items.reject', $item), ['note' => 'Anggaran belum tersedia'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('DITOLAK', $item->acquisition_status);
        $this->assertSame('Anggaran belum tersedia', $item->acquisition_note);
        $this->assertSame(0, $item->units()->count());

        $this->actingAs($this->laboran)
            ->get(route('items.acquisitions'))
            ->assertOk()
            ->assertSee('Anggaran belum tersedia');
    }

    public function test_operator_melihat_status_pengajuan_sedang_divalidasi(): void
    {
        $item = $this->createDraft();

        $this->actingAs($this->laboran)
            ->get(route('items.acquisitions'))
            ->assertOk()
            ->assertSeeText($item->name)
            ->assertSeeText('Menunggu validasi Editor/Validator')
            ->assertSeeText('Pengajuan sedang diajukan dan menunggu validasi Editor/Validator.');
    }

    public function test_antrean_validasi_menampilkan_tombol_terima_dan_tolak(): void
    {
        $item = $this->createDraft();

        $this->actingAs($this->ketua)
            ->get(route('items.acquisitions', ['status' => 'MENUNGGU_KONFIRMASI']))
            ->assertOk()
            ->assertSeeText('Validasi Pengadaan')
            ->assertSeeText($item->name)
            ->assertSee('Terima & teruskan ke Admin', false)
            ->assertSee('Tolak', false);
    }

    public function test_editor_validator_dapat_menolak_pengajuan_sebelum_sampai_ke_admin(): void
    {
        $item = $this->createDraft();

        $this->actingAs($this->ketua)
            ->post(route('items.acquisitions.reject', $item), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame('MENUNGGU_KONFIRMASI', $item->refresh()->acquisition_status);

        $this->actingAs($this->ketua)
            ->post(route('items.acquisitions.reject', $item), ['note' => 'Spesifikasi belum jelas'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('DITOLAK', $item->acquisition_status);
        $this->assertSame('Spesifikasi belum jelas', $item->acquisition_note);
        $this->assertSame($this->ketua->id, $item->acquisition_confirmed_by);

        // Pengajuan yang sudah ditolak tidak dapat diteruskan ke Admin.
        $this->actingAs($this->wakasek)
            ->post(route('items.approve', $item))
            ->assertSessionHasErrors('acquisition');
    }

    public function test_transisi_tidak_sah_dan_akses_lintas_ruang_ditolak(): void
    {
        $item = $this->createDraft();

        // Tidak dapat menyetujui sebelum divalidasi Editor/Validator.
        $this->actingAs($this->wakasek)
            ->post(route('items.approve', $item))
            ->assertSessionHasErrors('acquisition');

        $this->assertSame('MENUNGGU_KONFIRMASI', $item->refresh()->acquisition_status);

        // Laboran lain di luar ruang lingkup tidak dapat memproses pengajuan.
        $otherLaboran = User::where('email', 'laboran.dkv@gmail.com')->firstOrFail();
        $this->actingAs($otherLaboran)
            ->post(route('items.acquisitions.submit', $item))
            ->assertNotFound();

        // Pengadaan ke laboratorium di luar cakupan ditolak backend.
        $otherRoom = Room::where('is_lab', true)
            ->whereNotIn('id', $this->laboran->roomScopes()->select('room_id'))
            ->firstOrFail();

        $this->actingAs($this->laboran)->post(route('items.store'), $this->payload([
            'name' => 'Barang Luar Cakupan',
            'allocations' => [['room_id' => $otherRoom->id, 'quantity' => 1]],
            'quantity' => 1,
        ]))->assertSessionHasErrors('allocations.0.room_id');
    }

    public function test_penerimaan_ganda_ditolak(): void
    {
        $item = $this->createDraft();
        $this->toWaitingApproval($item);
        $this->actingAs($this->wakasek)->post(route('items.approve', $item));

        $this->actingAs($this->laboran)->post(route('items.acquisitions.receive', $item), [
            'received' => [$this->room->id => 2],
            'condition' => 'BAIK',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->laboran)->post(route('items.acquisitions.receive', $item), [
            'received' => [$this->room->id => 2],
            'condition' => 'BAIK',
        ])->assertSessionHasErrors('acquisition');

        $this->assertSame('BARANG_DITERIMA', $item->refresh()->acquisition_status);
    }
}

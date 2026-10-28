<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $staff;

    private User $siswa;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->staff = User::where('email', 'staf.tata@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_guru_dapat_mencatat_kegiatan_dengan_status(): void
    {
        $this->actingAs($this->guru)->post(route('events.store'), [
            'name' => 'Rapat Evaluasi Pembelajaran',
            'pic_name' => $this->guru->name,
            'description' => 'Evaluasi tengah semester',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => EventStatus::SEDANG_BERJALAN->value,
        ])->assertRedirect();

        $event = Event::where('name', 'Rapat Evaluasi Pembelajaran')->firstOrFail();

        $this->assertSame($this->guru->id, $event->created_by);
        $this->assertSame(EventStatus::SEDANG_BERJALAN, $event->status);
        $this->assertTrue($event->isManageableBy($this->guru));
        $this->assertFalse($event->isManageableBy($this->siswa));
    }

    public function test_staf_dapat_memperbarui_kegiatan_miliknya(): void
    {
        $event = Event::create([
            'name' => 'Pelatihan Tata Usaha',
            'pic_name' => $this->staff->name,
            'description' => 'Pelatihan arsip',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-27',
            'status' => EventStatus::BELUM_BERJALAN->value,
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)->patch(route('events.update', $event), [
            'name' => 'Pelatihan Tata Usaha',
            'pic_name' => $this->staff->name,
            'description' => 'Pelatihan arsip selesai',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-27',
            'status' => EventStatus::SUDAH_BERJALAN->value,
        ])->assertRedirect();

        $this->assertSame(EventStatus::SUDAH_BERJALAN, $event->refresh()->status);
        $this->assertSame($this->staff->id, $event->updated_by);
    }

    public function test_siswa_tidak_dapat_mencatat_atau_mengubah_kegiatan(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan Guru',
            'pic_name' => 'Guru',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::BELUM_BERJALAN->value,
            'created_by' => $this->guru->id,
        ]);

        $this->actingAs($this->siswa)->post(route('events.store'), [
            'name' => 'Kegiatan Siswa',
            'pic_name' => 'Siswa',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
        ])->assertForbidden();

        $this->actingAs($this->siswa)->patch(route('events.update', $event), [
            'name' => 'Diubah Siswa',
            'pic_name' => 'Siswa',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::SUDAH_BERJALAN->value,
        ])->assertForbidden();

        $this->assertSame('Kegiatan Guru', $event->refresh()->name);
    }

    public function test_guru_tidak_dapat_mengubah_kegiatan_guru_lain(): void
    {
        $guruLain = User::where('email', 'guru.sari@gmail.com')->firstOrFail();

        $event = Event::create([
            'name' => 'Kegiatan Guru Lain',
            'pic_name' => $guruLain->name,
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::BELUM_BERJALAN->value,
            'created_by' => $guruLain->id,
        ]);

        $this->actingAs($this->guru)->patch(route('events.update', $event), [
            'name' => 'Dibajak',
            'pic_name' => 'X',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::SUDAH_BERJALAN->value,
        ])->assertForbidden();
    }

    public function test_wakasek_dapat_memantau_seluruh_kegiatan(): void
    {
        Event::create([
            'name' => 'Kegiatan Pantauan',
            'pic_name' => 'Guru',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::SEDANG_BERJALAN->value,
            'created_by' => $this->guru->id,
        ]);

        $this->actingAs($this->wakasek)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Kegiatan Pantauan')
            ->assertSee('Sedang Berjalan');
    }

    public function test_status_kegiatan_tidak_mengubah_status_persetujuan_peminjaman(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan dengan Pinjaman',
            'pic_name' => 'Guru',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-26',
            'status' => EventStatus::BELUM_BERJALAN->value,
            'created_by' => $this->guru->id,
        ]);

        $loan = Loan::create([
            'borrower_id' => $this->siswa->id,
            'event_id' => $event->id,
            'purpose' => 'Kegiatan sekolah',
            'start_date' => now()->addDay(),
            'due_date' => now()->addDays(2),
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->guru)->patch(route('events.update', $event), [
            'name' => $event->name,
            'pic_name' => $event->pic_name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => EventStatus::SEDANG_BERJALAN->value,
        ])->assertRedirect();

        $this->assertSame(EventStatus::SEDANG_BERJALAN, $event->refresh()->status);
        $this->assertSame('PENDING', $loan->refresh()->status->value);
    }
}

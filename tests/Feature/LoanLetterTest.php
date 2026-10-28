<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Condition;
use App\Enums\Role;
use App\Models\AssetUnit;
use App\Models\Event;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanLetterTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private User $siswa;

    private User $otherBorrower;

    private AssetUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $major = Major::where('code', 'RPL')->firstOrFail();

        $this->guru = User::create([
            'name' => 'Andhika Wicaksana',
            'degree' => 'S.Kom',
            'email' => 'andhika.guru@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::GURU->value,
            'major_id' => $major->id,
            'birth_date' => now()->subYears(30)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->siswa = User::create([
            'name' => 'Raka Pratama',
            'email' => 'raka.siswa@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::SISWA->value,
            'class_name' => 'X RPL 1',
            'major_id' => $major->id,
            'birth_date' => now()->subYears(16)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->otherBorrower = User::create([
            'name' => 'Peminjam Lain',
            'email' => 'lain@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::SISWA->value,
            'class_name' => 'XI DKV 2',
            'major_id' => $major->id,
            'birth_date' => now()->subYears(17)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->unit = AssetUnit::query()->with('catalog')->firstOrFail();
    }

    private function makeLoan(User $borrower, ?Event $event = null): Loan
    {
        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'event_id' => $event?->id,
            'purpose' => 'Keperluan pembelajaran',
            'start_date' => '2026-10-26 10:30:00',
            'due_date' => '2026-10-28 14:00:00',
            'status' => 'APPROVED',
        ]);

        $loanItem = LoanItem::create([
            'loan_id' => $loan->id,
            'item_id' => $this->unit->item_catalog_id,
            'qty_borrowed' => 1,
            'condition_out' => Condition::BAIK->value,
        ]);

        LoanItemUnit::create([
            'loan_item_id' => $loanItem->id,
            'asset_unit_id' => $this->unit->id,
            'assigned_at' => now(),
            'condition_out' => Condition::BAIK->value,
            'opened_at' => now(),
        ]);

        return $loan;
    }

    public function test_surat_non_kegiatan_tidak_menampilkan_field_kegiatan_dan_tanpa_jam(): void
    {
        $loan = $this->makeLoan($this->guru);

        $response = $this->actingAs($this->guru)
            ->get(route('loans.letter.preview', $loan));

        $response->assertOk();
        $response->assertSee('SURAT PEMINJAMAN BARANG');
        $response->assertDontSee('Nama Kegiatan');
        $response->assertDontSee('Penanggung Jawab');
        $response->assertDontSee('Tanggal Kegiatan');
        $response->assertSee('26 Oktober 2026');
        $response->assertSee('28 Oktober 2026');
        $response->assertDontSee('10:30');
        $response->assertDontSee('NIM/NIP');
    }

    public function test_surat_kegiatan_tetap_menampilkan_informasi_kegiatan(): void
    {
        $event = Event::create([
            'name' => 'Lomba Kompetensi Siswa',
            'pic_name' => 'Panitia LKS',
            'start_date' => '2026-10-26',
            'end_date' => '2026-10-27',
            'created_by' => $this->guru->id,
        ]);

        $loan = $this->makeLoan($this->guru, $event);

        $this->actingAs($this->guru)
            ->get(route('loans.letter.preview', $loan))
            ->assertOk()
            ->assertSee('BERITA ACARA SERAH TERIMA (BAST)')
            ->assertSee('Nama Kegiatan')
            ->assertSee('Lomba Kompetensi Siswa')
            ->assertSee('Panitia LKS');
    }

    public function test_identitas_siswa_menampilkan_kelas_dan_jurusan(): void
    {
        $loan = $this->makeLoan($this->siswa);

        $this->actingAs($this->siswa)
            ->get(route('loans.letter.preview', $loan))
            ->assertOk()
            ->assertSee('Kelas X RPL 1')
            ->assertSee('Rekayasa Perangkat Lunak')
            ->assertDontSee('NIM/NIP');
    }

    public function test_identitas_guru_menampilkan_nama_dengan_gelar(): void
    {
        $loan = $this->makeLoan($this->guru);

        $this->actingAs($this->guru)
            ->get(route('loans.letter.preview', $loan))
            ->assertOk()
            ->assertSee('Andhika Wicaksana, S.Kom', false);
    }

    public function test_kode_inventaris_unit_tampil_pada_rincian_barang(): void
    {
        $loan = $this->makeLoan($this->guru);

        $this->actingAs($this->guru)
            ->get(route('loans.letter.preview', $loan))
            ->assertOk()
            ->assertSee('Kode Inventaris Unit')
            ->assertSee($this->unit->inventory_code);
    }

    public function test_pengguna_lain_tidak_dapat_membuka_surat(): void
    {
        $loan = $this->makeLoan($this->guru);

        $this->actingAs($this->otherBorrower)
            ->get(route('loans.letter.preview', $loan))
            ->assertForbidden();
    }

    public function test_pdf_surat_tetap_dapat_diunduh(): void
    {
        $loan = $this->makeLoan($this->guru);

        $this->actingAs($this->guru)
            ->get(route('loans.bast', $loan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}

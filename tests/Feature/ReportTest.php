<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Periode laporan hanya memuat pinjaman yang dibuat oleh test ini.
        Loan::query()->delete();

        $this->guru = User::where('username', 'guru.budi')->firstOrFail();
    }

    private function makeLoan(array $attributes = []): Loan
    {
        return Loan::create(array_merge([
            'borrower_id' => $this->guru->id,
            'purpose' => 'Keperluan laporan bulanan',
            'start_date' => now()->subDays(10),
            'due_date' => now()->subDays(3),
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        return app(ReportService::class)->data(now()->format('Y-m'))['summary'];
    }

    public function test_loan_pending_ditolak_dan_dibatalkan_lewat_jatuh_tempo_tidak_dihitung_terlambat(): void
    {
        $this->makeLoan(['status' => 'PENDING']);
        $this->makeLoan(['status' => 'DITOLAK']);
        $this->makeLoan(['status' => 'DIBATALKAN']);

        $summary = $this->summary();

        $this->assertSame(0, $summary['terlambat']);
        $this->assertSame(3, $summary['pengajuan_pinjam']);
    }

    public function test_loan_selesai_yang_dikembalikan_terlambat_dihitung_terlambat(): void
    {
        $this->makeLoan([
            'status' => 'SELESAI',
            'returned_at' => now()->subDay(),
        ]);

        $this->assertSame(1, $this->summary()['terlambat']);
    }

    public function test_loan_selesai_tepat_waktu_tidak_dihitung_terlambat(): void
    {
        $this->makeLoan([
            'status' => 'SELESAI',
            'due_date' => now()->subDays(3),
            'returned_at' => now()->subDays(4),
        ]);

        $this->assertSame(0, $this->summary()['terlambat']);
    }

    public function test_loan_berjalan_yang_lewat_jatuh_tempo_tetap_dihitung_terlambat(): void
    {
        $this->makeLoan(['status' => 'DIPINJAM']);

        $this->assertSame(1, $this->summary()['terlambat']);
    }
}

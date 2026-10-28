<?php

namespace Tests\Feature;

use App\Enums\Decision;
use App\Enums\LoanStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LaboranApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $laboranTp;

    private User $wakasek;

    private Item $biasaDiLabSendiri;

    private Item $nilaiTinggiDiLabSendiri;

    private Item $barangLabLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->laboranTp = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->biasaDiLabSendiri = Item::where('name', 'Mesin Las Listrik')->firstOrFail();
        $this->nilaiTinggiDiLabSendiri = Item::where('name', 'Oscilloscope')->firstOrFail();
        $this->barangLabLain = Item::where('name', 'Komputer PC')->firstOrFail();
    }

    public function test_laboran_mendapat_persetujuan_otomatis_untuk_barang_biasa_di_lab_sendiri(): void
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->laboranTp,
            [['item_id' => $this->biasaDiLabSendiri->id, 'qty' => 1]],
            'Praktik pengelasan',
            '2027-09-01 08:00',
            '2027-09-02 16:00'
        );

        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);

        $approval = $loan->approvals()->firstOrFail();
        $this->assertSame($this->laboranTp->id, $approval->approver_id);
        $this->assertSame(1, $approval->level);
        $this->assertStringContainsString('otomatis', (string) $approval->note);

        $activity = Activity::query()
            ->where('log_name', 'peminjaman')
            ->where('subject_id', $loan->id)
            ->where('description', 'like', 'Persetujuan otomatis%')
            ->firstOrFail();

        $this->assertSame($this->laboranTp->id, (int) $activity->causer_id);
    }

    public function test_barang_nilai_tinggi_di_lab_sendiri_tetap_menunggu_persetujuan_wakasek(): void
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->laboranTp,
            [['item_id' => $this->nilaiTinggiDiLabSendiri->id, 'qty' => 1]],
            'Kalibrasi perangkat',
            '2027-09-01 08:00',
            '2027-09-02 16:00'
        );

        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);
        $this->assertTrue($loan->needs_high_value_approval);
        $this->assertSame(1, $loan->approvals()->count());
        $this->assertSame(1, (int) $loan->approvals()->value('level'));

        app(LoanService::class)->decide($loan, $this->wakasek, Decision::APPROVED, 'Disetujui Wakasek');

        $this->assertSame(LoanStatus::APPROVED, $loan->refresh()->status);

        $level2 = LoanApproval::query()
            ->where('loan_id', $loan->id)
            ->where('level', 2)
            ->where('decision', Decision::APPROVED->value)
            ->firstOrFail();

        $this->assertSame($this->wakasek->id, $level2->approver_id);
    }

    public function test_pengajuan_lintas_lingkup_tidak_disetujui_otomatis(): void
    {
        $loan = app(LoanService::class)->requestLoan(
            $this->laboranTp,
            [
                ['item_id' => $this->biasaDiLabSendiri->id, 'qty' => 1],
                ['item_id' => $this->barangLabLain->id, 'qty' => 1],
            ],
            'Pinjam lintas laboratorium',
            '2027-09-01 08:00',
            '2027-09-02 16:00'
        );

        $this->assertSame(LoanStatus::PENDING, $loan->refresh()->status);
        $this->assertSame(0, $loan->approvals()->count());
    }

    public function test_katalog_yang_sama_tidak_dapat_diajukan_dua_kali(): void
    {
        try {
            app(LoanService::class)->requestLoan(
                $this->laboranTp,
                [
                    ['item_id' => $this->biasaDiLabSendiri->id, 'qty' => 1],
                    ['item_id' => $this->biasaDiLabSendiri->id, 'qty' => 1],
                ],
                'Pengajuan duplikat',
                '2027-09-01 08:00',
                '2027-09-02 16:00'
            );

            $this->fail('Pengajuan duplikat seharusnya ditolak.');
        } catch (AvailabilityException $exception) {
            $this->assertStringContainsString('tidak boleh dipilih lebih dari sekali', $exception->getMessage());
        }

        $this->assertSame(0, Loan::where('purpose', 'Pengajuan duplikat')->count());
    }
}

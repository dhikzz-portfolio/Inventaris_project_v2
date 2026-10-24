<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_ketua_laboran_hanya_mendapat_data_laporan_laboratorium(): void
    {
        $admin = User::where('username', 'ketua.laboran')->firstOrFail();
        $wakasek = User::where('username', 'wakasek')->firstOrFail();

        $labItem = Item::where('name', 'Komputer PC')->firstOrFail();
        $nonLabItem = Item::where('name', 'Proyektor Epson')->firstOrFail();

        $labLoan = $this->makeLoan(['purpose' => 'Praktikum lab']);
        $labLoan->loanItems()->create([
            'item_id' => $labItem->id, 'qty_borrowed' => 1, 'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);
        // Pinjaman campuran: hanya baris laboratorium yang boleh terlihat Ketua Laboran.
        $labLoan->loanItems()->create([
            'item_id' => $nonLabItem->id, 'qty_borrowed' => 2, 'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);

        $nonLabLoan = $this->makeLoan(['purpose' => 'Pinjaman ruang guru']);
        $nonLabLoan->loanItems()->create([
            'item_id' => $nonLabItem->id, 'qty_borrowed' => 1, 'qty_returned' => null,
            'condition_out' => Condition::BAIK->value,
        ]);

        $service = app(ReportService::class);
        $period = now()->format('Y-m');

        $labData = $service->data($period, 'SEMUA', null, $admin);
        $fullData = $service->data($period, 'SEMUA', null, $wakasek);

        $this->assertSame('Seluruh laboratorium', $labData['scopeLabel']);
        $this->assertSame('Seluruh sekolah', $fullData['scopeLabel']);

        $this->assertTrue($labData['items']->every(fn (Item $item) => $item->room?->is_lab === true));
        $this->assertFalse($labData['items']->contains('name', $nonLabItem->name));
        $this->assertTrue($fullData['items']->contains('name', $nonLabItem->name));

        $this->assertSame([$labLoan->id], $labData['loans']->pluck('id')->all());
        $this->assertContains($nonLabLoan->id, $fullData['loans']->pluck('id')->all());
        $this->assertSame([$labItem->id], $labData['loans']->first()->loanItems->pluck('item_id')->all());
        $this->assertCount(2, $fullData['loans']->firstWhere('id', $labLoan->id)->loanItems);

        $this->assertSame($labData['items']->count(), $labData['summary']['total_barang']);
        $this->assertLessThan($fullData['summary']['total_barang'], $labData['summary']['total_barang']);
    }

    public function test_ringkasan_laporan_menghitung_unit_fisik_bukan_baris_katalog(): void
    {
        $wakasek = User::where('username', 'wakasek')->firstOrFail();

        $data = app(ReportService::class)->data(now()->format('Y-m'), 'SEMUA', null, $wakasek);
        $summary = $data['summary'];

        $units = AssetUnit::query()
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->get();

        $this->assertNotEmpty($units);
        $this->assertSame($units->count(), $summary['total_unit']);
        $this->assertSame($data['units']->count(), $summary['total_unit']);

        // Rincian saling eksklusif dan tetap: baik + rusak + hilang = total unit.
        $this->assertSame(
            $units->where('condition', Condition::RUSAK_BERAT->value)->count(),
            $summary['rusak_berat']
        );
        $this->assertSame($units->where('status', UnitStatus::HILANG->value)->count(), $summary['hilang']);
        $this->assertSame(
            $summary['total_unit'],
            $summary['baik'] + $summary['rusak_ringan'] + $summary['rusak_berat'] + $summary['hilang']
        );

        // Jumlah katalog berbeda dari jumlah unit (satu katalog dapat banyak unit).
        $this->assertSame($data['items']->count(), $summary['total_barang']);
        $this->assertGreaterThan($summary['total_barang'], $summary['total_unit']);
    }

    public function test_arsip_laporan_seluruh_sekolah_hanya_dapat_diakses_super_admin(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/semua.pdf', '%PDF-1.4 test');
        Storage::disk('local')->put('reports/milik-admin.pdf', '%PDF-1.4 test');

        $admin = User::where('username', 'ketua.laboran')->firstOrFail();
        $wakasek = User::where('username', 'wakasek')->firstOrFail();

        $systemReport = Report::create([
            'period' => now()->format('Y-m'),
            'scope' => 'SEMUA',
            'scope_id' => null,
            'pdf_path' => 'reports/semua.pdf',
            'generated_by' => null,
            'generated_at' => now(),
        ]);

        $ownReport = Report::create([
            'period' => now()->format('Y-m'),
            'scope' => 'RUANGAN',
            'scope_id' => 1,
            'pdf_path' => 'reports/milik-admin.pdf',
            'generated_by' => $admin->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('files.show', ['path' => $systemReport->pdf_path]))->assertForbidden();
        $this->actingAs($admin)->get(route('files.show', ['path' => $ownReport->pdf_path]))->assertOk();
        $this->actingAs($wakasek)->get(route('files.show', ['path' => $systemReport->pdf_path]))->assertOk();

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->total() === 1 && $reports->first()->is($ownReport));

        $this->actingAs($wakasek)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->total() === 2);
    }
}

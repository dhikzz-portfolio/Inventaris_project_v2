<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pembuatan laporan bulanan (PDF + Excel) — dijadwalkan tanggal 1 tiap bulan, retry 3x.
 * Idempoten: Report diupdateOrCreate berdasarkan periode+cakupan.
 */
class GenerateMonthlyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $period) {}

    public function handle(): void
    {
        app(ReportService::class)->generateMonthly($this->period, 'SEMUA', null, null);

        activity('scheduler')->log("GenerateMonthlyReport: laporan {$this->period} dibuat");
    }
}

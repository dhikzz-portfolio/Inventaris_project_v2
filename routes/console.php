<?php

use App\Jobs\CheckDueSoonLoans;
use App\Jobs\CheckLowStock;
use App\Jobs\CheckOverdueLoans;
use App\Jobs\GenerateMonthlyReport;
use Illuminate\Support\Facades\Schedule;

// Alert keterlambatan pengembalian — setiap hari pagi (retry via queue worker).
Schedule::job(new CheckOverdueLoans)
    ->dailyAt('07:00')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Pengingat tenggat pengembalian H-1.
Schedule::job(new CheckDueSoonLoans)
    ->dailyAt('07:30')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Alert stok barang habis pakai menipis.
Schedule::job(new CheckLowStock)
    ->dailyAt('07:15')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Laporan bulanan otomatis (PDF + Excel) setiap tanggal 1.
Schedule::job(new GenerateMonthlyReport(now()->subMonthNoOverflow()->format('Y-m')))
    ->monthlyOn(1, '01:00')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/scheduler.log'));

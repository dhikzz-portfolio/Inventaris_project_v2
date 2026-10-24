<?php

namespace App\Jobs;

use App\Enums\LoanStatus;
use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\Loan;
use App\Models\User;
use App\Services\NotifyApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Alert peminjaman terlambat dikembalikan — dijadwalkan harian, retry 3x.
 */
class CheckOverdueLoans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        $overdue = Loan::whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value])
            ->where('due_date', '<', now())
            ->with(['borrower'])
            ->get();

        foreach ($overdue as $loan) {
            $link = route('loans.show', $loan);
            $todayKey = fn () => AppNotification::where('user_id', $loan->borrower_id)
                ->where('type', NotificationType::TERLAMBAT->value)
                ->where('link', $link)
                ->whereDate('created_at', today())
                ->exists();

            if (! $todayKey()) {
                NotifyApp::to($loan->borrower, NotificationType::TERLAMBAT,
                    "⚠ Peminjaman \"{$loan->purpose}\" TERLAMBAT — jatuh tempo {$loan->due_date->format('d M Y H:i')}. Segera kembalikan barang.",
                    $link);
            }

            // Admin/wakasek juga diingatkan (sekali per hari per loan).
            foreach (User::whereIn('role', ['SUPER_ADMIN', 'KETUA_LABORAN'])->pluck('id') as $adminId) {
                $exists = AppNotification::where('user_id', $adminId)
                    ->where('type', NotificationType::TERLAMBAT->value)
                    ->where('link', $link)
                    ->whereDate('created_at', today())
                    ->exists();

                if (! $exists) {
                    NotifyApp::to(User::findOrFail($adminId), NotificationType::TERLAMBAT,
                        "Peminjaman \"{$loan->purpose}\" oleh {$loan->borrower->name} terlambat (jatuh tempo {$loan->due_date->format('d/m/Y')}).",
                        $link);
                }
            }
        }

        activity('scheduler')->log('CheckOverdueLoans: '.count($overdue).' pinjaman terlambat');
    }
}

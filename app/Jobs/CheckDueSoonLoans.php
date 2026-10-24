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
 * Pengingat tenggat pengembalian (H-1) — dijadwalkan harian, retry 3x,
 * dengan pencegahan notifikasi duplikat per pengguna per pinjaman per hari.
 */
class CheckDueSoonLoans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        $dueSoon = Loan::whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value])
            ->whereBetween('due_date', [now(), now()->addDay()])
            ->with(['borrower'])
            ->get();

        foreach ($dueSoon as $loan) {
            $link = route('loans.show', $loan);
            $message = "Pengingat: peminjaman \"{$loan->purpose}\" jatuh tempo {$loan->due_date->format('d M Y H:i')}. Segera kembalikan barang.";

            $this->notifyOnce($loan->borrower, NotificationType::PENGEMBALIAN, $message, $link);

            foreach (User::whereIn('role', ['SUPER_ADMIN', 'KETUA_LABORAN'])->get() as $admin) {
                $this->notifyOnce($admin, NotificationType::PENGEMBALIAN,
                    "Pengingat: \"{$loan->purpose}\" oleh {$loan->borrower->name} jatuh tempo {$loan->due_date->format('d/m/Y H:i')}.",
                    $link);
            }
        }

        activity('scheduler')->log('CheckDueSoonLoans: '.count($dueSoon).' pinjaman mendekati jatuh tempo');
    }

    private function notifyOnce(User $user, NotificationType $type, string $message, string $link): void
    {
        $exists = AppNotification::where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('link', $link)
            ->whereDate('created_at', today())
            ->exists();

        if (! $exists) {
            NotifyApp::to($user, $type, $message, $link);
        }
    }
}

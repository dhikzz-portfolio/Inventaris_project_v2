<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\Item;
use App\Models\User;
use App\Services\NotifyApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Alert stok barang habis pakai menipis — dijadwalkan harian, retry 3x.
 */
class CheckLowStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function handle(): void
    {
        $lowStock = Item::approved()
            ->whereNotNull('min_stock')
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('status', '!=', 'DIHAPUS')
            ->with(['room'])
            ->get();

        foreach ($lowStock as $item) {
            $link = route('items.show', $item);
            $alreadyToday = AppNotification::whereIn('user_id', User::whereIn('role', ['SUPER_ADMIN', 'ADMIN_SARPRAS'])->pluck('id'))
                ->where('type', NotificationType::STOK_MENIPIS->value)
                ->where('link', $link)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadyToday) {
                continue;
            }

            NotifyApp::toRoles(['SUPER_ADMIN', 'ADMIN_SARPRAS'], NotificationType::STOK_MENIPIS,
                "Stok menipis: \"{$item->name}\" sisa {$item->quantity} {$item->unit} (min. {$item->min_stock}) di {$item->room?->code}.",
                $link);
        }

        activity('scheduler')->log('CheckLowStock: '.count($lowStock).' barang stok menipis');
    }
}

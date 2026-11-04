<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Pembuat kegiatan atau Admin boleh menghapus, dan hanya bila belum ada
     * transaksi peminjaman. PIC tidak memperoleh hak kepemilikan.
     */
    public function delete(User $user, Event $event): bool
    {
        if ($event->hasLoans()) {
            return false;
        }

        return $user->isAdmin()
            || ($event->created_by !== null && (int) $event->created_by === (int) $user->id);
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isManageableBy($user);
    }
}

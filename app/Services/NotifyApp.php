<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\User;

class NotifyApp
{
    /** Kirim notifikasi in-app ke satu pengguna. */
    public static function to(User $user, NotificationType $type, string $message, ?string $link = null): AppNotification
    {
        return $user->notificationsApp()->create([
            'type' => $type->value,
            'message' => $message,
            'link' => $link,
        ]);
    }

    /**
     * Kirim ke semua pengguna dengan role tertentu.
     *
     * @param  list<string>  $roles
     */
    public static function toRoles(array $roles, NotificationType $type, string $message, ?string $link = null): void
    {
        User::whereIn('role', $roles)->get()
            ->each(fn (User $user) => self::to($user, $type, $message, $link));
    }

    public static function toApprovers(NotificationType $type, string $message, ?string $link = null): void
    {
        self::toRoles(['SUPER_ADMIN', 'ADMIN_SARPRAS', 'LABORAN'], $type, $message, $link);
    }
}

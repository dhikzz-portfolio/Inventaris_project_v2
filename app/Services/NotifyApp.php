<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\User;

/**
 * Penentuan penerima notifikasi in-app.
 *
 * Penerima ditetapkan berdasarkan kewenangan yang benar-benar dibutuhkan
 * untuk menindaklanjuti tahapan terkait — bukan seluruh pengguna. Akun yang
 * dinonaktifkan tidak menerima notifikasi baru.
 */
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
     * Kirim ke semua pengguna aktif dengan role tertentu.
     *
     * @param  list<string>  $roles
     */
    public static function toRoles(array $roles, NotificationType $type, string $message, ?string $link = null): void
    {
        User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => self::to($user, $type, $message, $link));
    }

    /**
     * Kirim notifikasi ke seluruh peninjau yang berwenang menyetujui.
     */
    public static function toApprovers(NotificationType $type, string $message, ?string $link = null): void
    {
        self::toRoles([Role::SUPER_ADMIN->value, Role::KETUA_LABORAN->value], $type, $message, $link);
    }

    /**
     * Pengelola inventaris tingkat sekolah/ketua laboran — dipakai untuk
     * aktivitas yang hanya dapat ditindaklanjuti role tersebut.
     */
    public static function toInventoryManagers(NotificationType $type, string $message, ?string $link = null): void
    {
        self::toRoles([Role::SUPER_ADMIN->value, Role::KETUA_LABORAN->value], $type, $message, $link);
    }

    /**
     * Administrator akun (Super Admin utama & Super Admin Pengguna).
     * Super Admin Pengguna tidak memperoleh notifikasi transaksi inventaris.
     */
    public static function toAccountAdmins(
        NotificationType $type,
        string $message,
        ?string $link = null,
        ?int $exceptUserId = null,
    ): void {
        User::query()
            ->whereIn('role', [Role::SUPER_ADMIN->value, Role::SUPER_ADMIN_PENGGUNA->value])
            ->where('is_active', true)
            ->when($exceptUserId !== null, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->get()
            ->each(fn (User $user) => self::to($user, $type, $message, $link));
    }
}

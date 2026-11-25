<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationLinkGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $guard = app(NotificationLinkGuard::class);

        $notifications = $user->notificationsApp()
            ->latest()
            ->paginate(15);

        // Tautan diperiksa terhadap kewenangan penerima saat ini. Notifikasi
        // lama tetap tersimpan sebagai riwayat, tetapi tidak ditawarkan sebagai
        // tautan yang akan berakhir di halaman 403.
        $notifications->getCollection()->each(function (AppNotification $notification) use ($user, $guard): void {
            $notification->setAttribute('link_accessible', $guard->isAccessible($user, $notification->link));
        });

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        if ($notification->link !== null
            && ! app(NotificationLinkGuard::class)->isAccessible($request->user(), $notification->link)) {
            return redirect()->route('notifications.index')
                ->with('success', 'Notifikasi ditandai sudah dibaca. Tautan tujuan tidak lagi tersedia untuk akun Anda.');
        }

        // Notifikasi dengan tautan langsung diarahkan ke halaman tujuannya;
        // notifikasi tanpa tautan cukup kembali ke daftar (sudah ditandai dibaca).
        return $notification->link ? redirect()->to($notification->link) : back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}

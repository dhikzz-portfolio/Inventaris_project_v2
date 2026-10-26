<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Notifikasi</h2>
                    @if ($unreadCount > 0)
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-700">{{ $unreadCount }} BARU</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500">Alert keterlambatan, persetujuan, dan aktivitas penting</p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="text-sm text-blue-700 font-semibold hover:underline">Tandai semua dibaca</button>
                </form>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <ul class="divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                <li @class([
                    'flex items-start gap-3 px-5 py-4',
                    'bg-blue-50/50 dark:bg-blue-950/20' => ! $notification->isRead(),
                ])>
                    <span @class([
                        'mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-full',
                        'bg-red-100 text-red-600' => $notification->type->value === 'TERLAMBAT',
                        'bg-blue-100 text-blue-600' => $notification->type->value === 'PERSETUJUAN',
                        'bg-green-100 text-green-600' => $notification->type->value === 'PENGEMBALIAN',
                        'bg-blue-100 text-blue-600' => $notification->type->value === 'PENGADAAN',
                        'bg-gray-100 text-gray-600' => in_array($notification->type->value, ['PENGHAPUSAN', 'LAINNYA']),
                    ])>
                        <span class="material-icons-round text-lg">
                            @switch($notification->type->value)
                                @case('TERLAMBAT') alarm @break
                                @case('PERSETUJUAN') how_to_reg @break
                                @case('PENGEMBALIAN') assignment_return @break
                                @case('PENGADAAN') add_box @break
                                @case('PENGHAPUSAN') delete_forever @break
                                @default info
                            @endswitch
                        </span>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">{{ $notification->type->label() }}</span>
                            @if (! $notification->isRead())
                                <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
                            @endif
                            <span class="ms-auto text-[11px] text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-gray-700 mt-0.5">{{ $notification->message }}</p>
                        @if ($notification->link)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="mt-1">
                                @csrf
                                <button class="text-xs font-semibold text-blue-700 hover:underline">Buka detail →</button>
                            </form>
                        @elseif (! $notification->isRead())
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="mt-1">
                                @csrf
                                <button class="text-xs font-semibold text-blue-700 hover:underline">Tandai sudah dibaca</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-gray-400 text-sm">
                    <span class="material-icons-round text-5xl block mb-2">notifications_none</span>
                    Belum ada notifikasi.
                </li>
            @endforelse
        </ul>
    </div>

    <div class="mt-5">{{ $notifications->links() }}</div>
</x-app-layout>

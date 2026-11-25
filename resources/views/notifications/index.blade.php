<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pemberitahuan</h2>
                    @if ($unreadCount > 0)
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ $unreadCount }} BARU</span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Kabar tentang peminjaman, persetujuan, dan aktivitas penting.</p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-base" aria-hidden="true">done_all</span> Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($notifications as $notification)
                <li @class([
                    'flex items-start gap-3 px-4 py-4 sm:px-5',
                    'bg-blue-50/60 dark:bg-blue-950/20' => ! $notification->isRead(),
                ])>
                    <span @class([
                        'mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300' => $notification->type->value === 'TERLAMBAT',
                        'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300' => $notification->type->value === 'PERSETUJUAN',
                        'bg-green-100 text-green-600 dark:bg-green-950/50 dark:text-green-300' => $notification->type->value === 'PENGEMBALIAN',
                        'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300' => $notification->type->value === 'PENGADAAN',
                        'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' => $notification->type->value === 'KERUSAKAN',
                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => in_array($notification->type->value, ['PENGHAPUSAN', 'AKUN', 'LAINNYA']),
                    ])>
                        <span class="material-icons-round text-lg" aria-hidden="true">
                            @switch($notification->type->value)
                                @case('TERLAMBAT') alarm @break
                                @case('PERSETUJUAN') how_to_reg @break
                                @case('PENGEMBALIAN') assignment_return @break
                                @case('PENGADAAN') add_box @break
                                @case('PENGHAPUSAN') delete_forever @break
                                @case('KERUSAKAN') report_problem @break
                                @case('AKUN') manage_accounts @break
                                @default info
                            @endswitch
                        </span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $notification->type->label() }}</span>
                            @if (! $notification->isRead())
                                <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
                            @endif
                            <span class="ms-auto text-[11px] text-slate-500 dark:text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $notification->message }}</p>
                        @if ($notification->link && $notification->link_accessible)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="mt-1">
                                @csrf
                                <button class="inline-flex min-h-8 items-center justify-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    Buka detail <span class="material-icons-round text-sm" aria-hidden="true">arrow_forward</span>
                                </button>
                            </form>
                        @elseif ($notification->link && ! $notification->isRead())
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="mt-1">
                                @csrf
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">Tautan tujuan tidak lagi tersedia untuk akun Anda.</span>
                                <button class="ms-1 inline-flex min-h-8 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50">Tandai sudah dibaca</button>
                            </form>
                        @elseif ($notification->link)
                            <span class="mt-1 inline-block text-[11px] text-slate-500 dark:text-slate-400">Tautan tujuan tidak lagi tersedia untuk akun Anda.</span>
                        @elseif (! $notification->isRead())
                            <form method="POST" action="{{ route('notifications.read', $notification) }}" class="mt-1">
                                @csrf
                                <button class="inline-flex min-h-8 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50">Tandai sudah dibaca</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-5 py-12 text-center">
                    <span class="mx-auto mb-3 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500" aria-hidden="true">
                        <span class="material-icons-round text-3xl">notifications_none</span>
                    </span>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Belum ada pemberitahuan</p>
                    <p class="mx-auto mt-1 max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Pemberitahuan baru akan muncul di sini saat ada informasi terkait akun atau aktivitas Anda.
                    </p>
                </li>
            @endforelse
        </ul>
    </div>

    <div class="mt-5">{{ $notifications->links() }}</div>
</x-app-layout>

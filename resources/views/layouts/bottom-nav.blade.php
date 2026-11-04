@php
    $user = auth()->user();
    $can = fn (string $permission): bool => (bool) $user?->can($permission);
    $isPeminjam = $user?->isMember();
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $primaryItems = [];

    if ($isPeminjam) {
        $primaryItems = [
            ['href' => route('dashboard'), 'icon' => 'dashboard', 'label' => 'Beranda', 'active' => request()->routeIs('dashboard'), 'badge' => 0],
            $can('loan.create') ? ['href' => route('loans.create'), 'icon' => 'add_shopping_cart', 'label' => 'Pinjam', 'active' => request()->routeIs('loans.create'), 'badge' => 0] : null,
            $can('loan.view') ? ['href' => route('loans.index'), 'icon' => 'swap_horiz', 'label' => 'Riwayat', 'active' => request()->routeIs('loans.index') || request()->routeIs('loans.show'), 'badge' => 0] : null,
        ];
    } else {
        $primaryItems = [
            ['href' => route('dashboard'), 'icon' => 'dashboard', 'label' => 'Beranda', 'active' => request()->routeIs('dashboard'), 'badge' => 0],
            $can('catalog.view') ? ['href' => route('catalogs.index'), 'icon' => 'inventory_2', 'label' => 'Katalog', 'active' => request()->routeIs('catalogs.*') || request()->routeIs('units.*'), 'badge' => 0] : null,
            $can('loan.create') ? ['href' => route('loans.create'), 'icon' => 'add_shopping_cart', 'label' => 'Pinjam', 'active' => request()->routeIs('loans.create'), 'badge' => 0] : null,
            $can('loan.approve')
                ? ['href' => route('loans.pending'), 'icon' => 'how_to_reg', 'label' => 'Persetujuan', 'active' => request()->routeIs('loans.pending'), 'badge' => 0]
                : ($can('loan.view') ? ['href' => route('loans.index'), 'icon' => 'swap_horiz', 'label' => 'Pinjaman', 'active' => request()->routeIs('loans.index') || request()->routeIs('loans.show'), 'badge' => 0] : null),
        ];
    }

    $primaryItems = array_values(array_filter($primaryItems));
    $primaryItems[] = ['href' => route('notifications.index'), 'icon' => 'notifications', 'label' => 'Notifikasi', 'active' => request()->routeIs('notifications.*'), 'badge' => $unread];
@endphp

{{-- Navigasi bawah khusus mobile — hanya menampilkan menu yang diizinkan permission. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 shadow-[0_-8px_24px_rgba(15,23,42,0.06)] backdrop-blur-xl safe-area-bottom dark:border-slate-800 dark:bg-slate-900/95 dark:shadow-black/20 md:hidden"
     aria-label="Navigasi utama">
    <div class="grid h-16 px-1" style="grid-template-columns: repeat({{ count($primaryItems) + 1 }}, minmax(0, 1fr));">
        @foreach ($primaryItems as $item)
            <a href="{{ $item['href'] }}"
               @if ($item['active']) aria-current="page" @endif
               aria-label="{{ $item['label'] }}{{ $item['badge'] > 0 ? ', '.$item['badge'].' belum dibaca' : '' }}"
               @class([
                   'group relative flex h-full min-w-0 flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-semibold transition-colors focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500',
                   'text-brand-700 dark:text-brand-300' => $item['active'],
                   'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! $item['active'],
               ])>
                <span @class([
                    'material-icons-round flex h-7 w-10 items-center justify-center rounded-full text-[21px] leading-none transition-colors',
                    'bg-brand-50 dark:bg-brand-900/60' => $item['active'],
                    'group-active:bg-slate-100 dark:group-active:bg-slate-800' => ! $item['active'],
                ]) aria-hidden="true">{{ $item['icon'] }}</span>
                <span class="truncate max-w-full px-0.5">{{ $item['label'] }}</span>
                @if ($item['badge'] > 0)
                    <span class="absolute right-[22%] top-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                @endif
            </a>
        @endforeach

        <button type="button" @click="menuOpen = ! menuOpen"
                :aria-expanded="menuOpen"
                aria-controls="main-menu"
                :aria-label="menuOpen ? 'Tutup menu lengkap' : 'Buka menu lengkap'"
                class="relative flex h-full min-w-0 flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-semibold text-slate-500 transition-colors hover:text-slate-700 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 dark:text-slate-400 dark:hover:text-slate-200"
                :class="menuOpen ? 'text-brand-700 dark:text-brand-300' : ''">
            <span class="material-icons-round flex h-7 w-10 items-center justify-center rounded-full text-[22px] leading-none transition-colors" :class="menuOpen ? 'bg-brand-50 dark:bg-brand-900/60' : ''" aria-hidden="true">menu</span>
            <span>Lainnya</span>
        </button>
    </div>
</nav>

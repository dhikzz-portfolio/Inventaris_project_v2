@php
    $user = auth()->user();
    $canApprove = $user?->canApproveLoans();
    $unread = \App\Models\AppNotification::where('user_id', auth()->id())->whereNull('read_at')->count();
@endphp

{{-- Navigasi bawah khusus mobile — akses utama dengan satu jempol --}}
<nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] safe-area-bottom"
     aria-label="Navigasi utama">
    <div class="grid grid-cols-5 h-16">
        @php($bottomItems = [
            ['href' => route('dashboard'), 'icon' => 'dashboard', 'label' => 'Beranda', 'active' => request()->routeIs('dashboard'), 'badge' => 0],
            ['href' => route('items.index'), 'icon' => 'inventory_2', 'label' => 'Katalog', 'active' => request()->routeIs('items.index') || request()->routeIs('items.show'), 'badge' => 0],
            ['href' => route('loans.create'), 'icon' => 'add_shopping_cart', 'label' => 'Pinjam', 'active' => request()->routeIs('loans.create'), 'badge' => 0],
            $canApprove
                ? ['href' => route('loans.pending'), 'icon' => 'how_to_reg', 'label' => 'Persetujuan', 'active' => request()->routeIs('loans.pending'), 'badge' => 0]
                : ['href' => route('loans.index'), 'icon' => 'swap_horiz', 'label' => 'Pinjaman', 'active' => request()->routeIs('loans.index') || request()->routeIs('loans.show'), 'badge' => 0],
        ])
        @foreach ($bottomItems as $item)
            <a href="{{ $item['href'] }}"
               @class([
                   'flex flex-col items-center justify-center gap-0.5 text-[10px] font-semibold h-full',
                   'text-amber-700' => $item['active'],
                   'text-gray-500 active:bg-gray-50' => ! $item['active'],
               ])>
                <span class="material-icons-round text-[22px] leading-none">{{ $item['icon'] }}</span>
                <span class="truncate max-w-full px-0.5">{{ $item['label'] }}</span>
            </a>
        @endforeach

        <button type="button" @click="menuOpen = ! menuOpen"
                class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-semibold text-gray-500 active:bg-gray-50 h-full relative"
                aria-label="Buka menu lengkap">
            <span class="material-icons-round text-[22px] leading-none">menu</span>
            <span>Lainnya</span>
            @if ($unread > 0)
                <span class="absolute top-2 right-[22%] inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </button>
    </div>
</nav>

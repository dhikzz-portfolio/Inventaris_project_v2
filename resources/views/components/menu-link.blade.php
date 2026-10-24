@props(['href', 'active', 'icon', 'label', 'badge' => 0])
<a href="{{ $href }}" @click="menuOpen = false" @if ($active) aria-current="page" @endif
    @class([
        'group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-500',
        'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => !$active,
    ])>
    <span class="material-icons-round text-xl leading-none" aria-hidden="true">{{ $icon }}</span>
    <span class="flex-1">{{ $label }}</span>
    @if ($badge > 0)
        <span
            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
</a>

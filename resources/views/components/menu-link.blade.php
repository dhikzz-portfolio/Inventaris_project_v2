@props(['href', 'active', 'icon', 'label', 'badge' => 0])
<a href="{{ $href }}" @click="menuOpen = false" @if ($active) aria-current="page" @endif
    @class([
        'group flex min-h-8 items-center gap-2 rounded-lg px-2 py-1.5 text-[11px] font-medium transition focus:outline-none focus:ring-2 focus:ring-brand-500 sm:gap-2.5',
        'bg-brand-50 text-brand-700 dark:bg-brand-900/60 dark:text-brand-300' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => !$active,
    ])>
    <span class="material-icons-round text-base leading-none" aria-hidden="true">{{ $icon }}</span>
    <span class="flex-1">{{ $label }}</span>
    @if ($badge > 0)
        <span
            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
</a>

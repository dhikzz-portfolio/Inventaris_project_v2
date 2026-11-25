@props(['href', 'active', 'icon', 'label', 'badge' => 0])
<a href="{{ $href }}" @click="menuOpen = false" @if ($active) aria-current="page" @endif
    @class([
        'group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500',
        'bg-brand-50 font-semibold text-brand-700 ring-1 ring-inset ring-brand-100 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-900' => $active,
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
    ])>
    <span @class([
        'material-icons-round text-[19px] leading-none transition-colors',
        'text-brand-600 dark:text-brand-300' => $active,
        'text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300' => ! $active,
    ]) aria-hidden="true">{{ $icon }}</span>
    <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
    @if ($badge > 0)
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ $badge > 99 ? '99+' : $badge }}</span>
    @endif
</a>

@props(['stat', 'operational' => false])

@php
    $tone = $stat['color'] ?? 'blue';
@endphp

<article class="flex h-full min-h-[112px] min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex items-start justify-between gap-2">
        <p class="min-w-0 text-[11px] font-semibold uppercase leading-4 tracking-wide text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
        <span @class([
            'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $tone === 'slate',
            'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300' => $tone === 'amber',
            'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' => $tone === 'red',
            'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300' => $tone === 'purple',
            'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-300' => $tone === 'green',
            'bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300' => ! in_array($tone, ['slate', 'amber', 'red', 'purple', 'green'], true),
        ])>
            <span class="material-icons-round text-lg" aria-hidden="true">{{ $stat['icon'] }}</span>
        </span>
    </div>
    <p class="mt-2 text-2xl font-bold leading-none tabular-nums text-slate-900 dark:text-slate-100 sm:text-[1.75rem]">{{ $stat['value'] }}</p>
    <p class="mt-auto pt-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $stat['unit'] }}</p>
</article>

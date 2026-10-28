@props(['stat', 'operational' => false])

<article @class([
    'h-full rounded-2xl border bg-white p-4 shadow-sm transition dark:bg-slate-900',
    'min-h-[112px]' => $operational,
    'min-h-[132px]' => ! $operational,
    'shadow-sm' => $operational,
    'shadow-sm hover:-translate-y-0.5 hover:shadow-md' => ! $operational,
    'border-sky-100 bg-white dark:border-slate-700 dark:bg-slate-900' => $operational && ! in_array($stat['color'], ['amber', 'red'], true),
    'border-amber-200 dark:border-amber-900' => $operational && $stat['color'] === 'amber',
    'border-red-200 dark:border-red-900' => $operational && $stat['color'] === 'red',
    'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ! $operational,
    'border-l-4 border-l-sky-600 dark:border-l-sky-400' => ! $operational,
    'border-l-slate-400' => ! $operational && $stat['color'] === 'slate',
    'border-l-blue-500' => ! $operational && $stat['color'] === 'blue',
    'border-l-amber-500' => ! $operational && $stat['color'] === 'amber',
    'border-l-red-500' => ! $operational && $stat['color'] === 'red',
    'border-l-purple-500' => ! $operational && $stat['color'] === 'purple',
    'border-l-green-500' => ! $operational && $stat['color'] === 'green',
])>
    <div class="flex h-full flex-col">
        <div class="flex min-h-8 items-center justify-between gap-2">
            <p class="line-clamp-2 min-h-8 min-w-0 text-xs font-semibold leading-4 text-slate-600 dark:text-slate-400">{{ $stat['label'] }}</p>
            <span @class([
                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-sm',
                'bg-gradient-to-br from-sky-500 to-sky-700 text-white shadow-sky-700/20' => $operational && ! in_array($stat['color'], ['amber', 'red'], true),
                'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-amber-700/20' => $operational && $stat['color'] === 'amber',
                'bg-gradient-to-br from-red-400 to-red-600 text-white shadow-red-700/20' => $operational && $stat['color'] === 'red',
                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $operational && $stat['color'] === 'slate',
                'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300' => ! $operational && $stat['color'] === 'blue',
                'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300' => ! $operational && $stat['color'] === 'amber',
                'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' => ! $operational && $stat['color'] === 'red',
                'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300' => ! $operational && $stat['color'] === 'purple',
                'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-300' => ! $operational && $stat['color'] === 'green',
            ])>
                <span class="material-icons-round text-lg" aria-hidden="true">{{ $stat['icon'] }}</span>
            </span>
        </div>
        <p class="mt-2 text-2xl font-bold leading-none tabular-nums text-slate-900 dark:text-slate-100 sm:text-[1.75rem]">{{ $stat['value'] }}</p>
        <p class="mt-auto pt-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $stat['unit'] }}</p>
    </div>
</article>

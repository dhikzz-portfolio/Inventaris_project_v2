@props(['stat', 'wakasek' => false])

<article @class([
    'h-full min-h-[132px] rounded-2xl border border-l-4 bg-white p-4 transition sm:p-4 dark:bg-slate-900',
    'shadow-none' => $wakasek,
    'shadow-sm hover:-translate-y-0.5 hover:shadow-md' => ! $wakasek,
    'border-sky-100 bg-white dark:border-slate-700 dark:bg-slate-900' => $wakasek,
    'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ! $wakasek,
    'border-l-sky-600 dark:border-l-sky-400' => $wakasek,
    'border-l-slate-400' => ! $wakasek && $stat['color'] === 'slate',
    'border-l-blue-500' => ! $wakasek && $stat['color'] === 'blue',
    'border-l-amber-500' => ! $wakasek && $stat['color'] === 'amber',
    'border-l-red-500' => ! $wakasek && $stat['color'] === 'red',
    'border-l-purple-500' => ! $wakasek && $stat['color'] === 'purple',
    'border-l-green-500' => ! $wakasek && $stat['color'] === 'green',
])>
    <div class="flex h-full flex-col">
        <div class="flex min-h-10 items-start justify-between gap-2">
            <p class="line-clamp-2 min-h-8 min-w-0 text-xs font-semibold leading-4 text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
            <span @class([
                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300' => $wakasek,
                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $wakasek && $stat['color'] === 'slate',
                'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-300' => ! $wakasek && $stat['color'] === 'blue',
                'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300' => ! $wakasek && $stat['color'] === 'amber',
                'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' => ! $wakasek && $stat['color'] === 'red',
                'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300' => ! $wakasek && $stat['color'] === 'purple',
                'bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-300' => ! $wakasek && $stat['color'] === 'green',
            ])>
                <span class="material-icons-round text-lg" aria-hidden="true">{{ $stat['icon'] }}</span>
            </span>
        </div>
        <p class="mt-1 text-2xl font-bold leading-none tabular-nums text-slate-900 sm:text-3xl dark:text-slate-100">{{ $stat['value'] }}</p>
        <p class="mt-auto pt-2 text-[11px] font-medium text-slate-400 dark:text-slate-500">{{ $stat['unit'] }}</p>
    </div>
</article>

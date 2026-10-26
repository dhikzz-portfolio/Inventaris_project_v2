@props(['title', 'description' => null])

<section {{ $attributes->class('rounded-2xl border border-sky-100 bg-white p-4 shadow-md shadow-sky-900/5 sm:p-4 dark:border-slate-700 dark:bg-slate-900') }}>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
            @endif
        </div>
        @if (isset($actions))
            <div class="shrink-0">{{ $actions }}</div>
        @endif
    </div>

    {{ $slot }}
</section>

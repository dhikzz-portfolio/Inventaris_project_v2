@props(['title', 'description' => null])

<section {{ $attributes->class('flex h-full min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5') }}>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-sm font-bold leading-snug text-slate-900 dark:text-slate-100">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
            @endif
        </div>
        @if (isset($actions))
            <div class="shrink-0">{{ $actions }}</div>
        @endif
    </div>

    <div class="flex-1">
        {{ $slot }}
    </div>
</section>

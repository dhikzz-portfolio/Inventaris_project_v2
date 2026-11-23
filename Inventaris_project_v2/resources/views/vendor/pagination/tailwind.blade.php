@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        // Jendela halaman: maksimal 3 nomor di sekitar halaman aktif.
    $start = max(1, $current - 1);
    $end = min($last, $current + 1);

    if ($start === 1) {
        $end = min($last, 3);
    }

    if ($end === $last) {
        $start = max(1, $last - 2);
    }

        $showStartEllipsis = $start > 2;
        $showEndEllipsis = $end < $last - 1;
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Menampilkan <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->firstItem() }}</span>
            sampai <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->lastItem() }}</span>
            dari <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->total() }}</span> data
        </p>

        <div class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-end">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Halaman sebelumnya" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_left</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya" wire:click.prevent="previousPage('{{ $paginator->getPageName() }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_left</span>
                </a>
            @endif

            {{-- Halaman pertama --}}
            @if ($start > 1)
                <a href="{{ $paginator->url(1) }}" aria-label="Ke halaman 1" wire:click.prevent="gotoPage(1, '{{ $paginator->getPageName() }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-xs text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">1</a>
            @endif

            @if ($showStartEllipsis)
                <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center text-xs text-slate-400">...</span>
            @endif

            {{-- Nomor halaman di sekitar halaman aktif --}}
            @for ($page = $start; $page <= $end; $page++)
                @if ($page === $current)
                    <span aria-current="page" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Ke halaman {{ $page }}" wire:click.prevent="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-xs text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
                @endif
            @endfor

            @if ($showEndEllipsis)
                <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center text-xs text-slate-400">...</span>
            @endif

            {{-- Halaman terakhir --}}
            @if ($end < $last)
                <a href="{{ $paginator->url($last) }}" aria-label="Ke halaman {{ $last }}" wire:click.prevent="gotoPage({{ $last }}, '{{ $paginator->getPageName() }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-xs text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">{{ $last }}</a>
            @endif

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman selanjutnya" wire:click.prevent="nextPage('{{ $paginator->getPageName() }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_right</span>
                </a>
            @else
                <span aria-disabled="true" aria-label="Halaman selanjutnya" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_right</span>
                </span>
            @endif
        </div>
    </nav>
@endif

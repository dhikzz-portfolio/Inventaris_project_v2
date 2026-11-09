@if ($paginator->hasPages())
    @php
        $pageGroupSize = 5;
        $firstVisiblePage = (int) (floor(($paginator->currentPage() - 1) / $pageGroupSize) * $pageGroupSize) + 1;
        $lastVisiblePage = min($firstVisiblePage + $pageGroupSize - 1, $paginator->lastPage());
        $previousGroupPage = max(1, $firstVisiblePage - $pageGroupSize);
        $nextGroupPage = $firstVisiblePage + $pageGroupSize;
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Menampilkan <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->firstItem() }}</span>
            sampai <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->lastItem() }}</span>
            dari <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->total() }}</span> data
        </p>

        <div class="flex flex-wrap items-center justify-center gap-1 sm:justify-end">
            @if ($firstVisiblePage > 1)
                <a href="{{ $paginator->url($previousGroupPage) }}" rel="prev" aria-label="Halaman {{ $previousGroupPage }} sampai {{ $firstVisiblePage - 1 }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <svg class="h-4 w-4" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @endif

            @for ($page = $firstVisiblePage; $page <= $lastVisiblePage; $page++)
                @if ($page === $paginator->currentPage())
                    <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-600 bg-brand-600 text-sm font-semibold text-white">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Ke halaman {{ $page }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-sm text-slate-700 transition hover:bg-slate-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
                @endif
            @endfor

            @if ($lastVisiblePage < $paginator->lastPage())
                <a href="{{ $paginator->url($nextGroupPage) }}" rel="next" aria-label="Halaman {{ $nextGroupPage }} sampai {{ min($nextGroupPage + $pageGroupSize - 1, $paginator->lastPage()) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_right</span>
                </a>
            @endif
        </div>
    </nav>
@endif

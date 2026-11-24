@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        // Paginasi per kelompok 5 halaman. Setiap kelompok menampilkan nomor
        // halaman internalnya ditambah panah lompat ke kelompok sebelumnya dan
        // berikutnya, sehingga daftar nomor tetap singkat walau halaman banyak.
        $groupSize = 5;
        $groupStart = intdiv($current - 1, $groupSize) * $groupSize + 1;
        $groupEnd = min($groupStart + $groupSize - 1, $last);

        $hasPreviousGroup = $groupStart > 1;
        $hasNextGroup = $groupEnd < $last;

        $previousGroupStart = max(1, $groupStart - $groupSize);
        $previousGroupEnd = $groupStart - 1;

        $nextGroupStart = $groupEnd + 1;
        $nextGroupEnd = min($nextGroupStart + $groupSize - 1, $last);
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Menampilkan <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->firstItem() }}</span>
            sampai <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->lastItem() }}</span>
            dari <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $paginator->total() }}</span> data
        </p>

        <div class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-end">
            {{-- Lompat ke kelompok sebelumnya --}}
            @if ($hasPreviousGroup)
                <a href="{{ $paginator->url($previousGroupStart) }}" rel="prev" aria-label="Halaman {{ $previousGroupStart }} sampai {{ $previousGroupEnd }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_left</span>
                </a>
            @endif

            {{-- Nomor halaman dalam kelompok aktif --}}
            @for ($page = $groupStart; $page <= $groupEnd; $page++)
                @if ($page === $current)
                    <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-brand-600 bg-brand-600 text-sm font-semibold text-white">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Ke halaman {{ $page }}"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-sm text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
                @endif
            @endfor

            {{-- Lompat ke kelompok berikutnya --}}
            @if ($hasNextGroup)
                <a href="{{ $paginator->url($nextGroupStart) }}" rel="next" aria-label="Halaman {{ $nextGroupStart }} sampai {{ $nextGroupEnd }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base" aria-hidden="true">chevron_right</span>
                </a>
            @endif
        </div>
    </nav>
@endif

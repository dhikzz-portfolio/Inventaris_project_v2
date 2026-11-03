<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengembalian Barang</h2>
                    @if ($loans->total() > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ $loans->total() }} MENUNGGU DIPERIKSA</span>
                    @endif
                </div>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400 sm:text-sm">Periksa jumlah dan kondisi barang sebelum menyelesaikan pengembalian.</p>
            </div>
            <a href="{{ route('loans.pending') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 sm:self-auto dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">
                <span class="material-icons-round text-base" aria-hidden="true">gavel</span>
                Persetujuan
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($loans->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-600 dark:bg-green-950/40 dark:text-green-400">
                <span class="material-icons-round text-2xl" aria-hidden="true">task_alt</span>
            </span>
            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Tidak ada pengembalian yang menunggu.</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pinjaman aktif yang perlu diperiksa akan tampil di sini.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($loans as $loan)
                @php
                    $borrowerName = trim((string) ($loan->borrower?->name ?? 'Peminjam'));
                    $borrowerInitials = collect(preg_split('/\s+/', $borrowerName))
                        ->filter()
                        ->take(2)
                        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                        ->implode('');
                @endphp
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">{{ $borrowerInitials ?: 'P' }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $borrowerName }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                    @if ($loan->isOverdue())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                            <span class="material-icons-round text-xs" aria-hidden="true">warning</span>
                                            Terlambat {{ now()->diffForHumans($loan->due_date) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower?->major?->name ?: $loan->borrower?->role }}</p>
                                <div class="mt-2 flex flex-col gap-1.5 text-xs text-slate-500 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 dark:text-slate-400">
                                    @if ($loan->purpose)
                                        <span class="inline-flex min-w-0 items-start gap-1.5">
                                            <span class="material-icons-round mt-px text-sm" aria-hidden="true">description</span>
                                            <span class="break-words">{{ $loan->purpose }}</span>
                                        </span>
                                    @endif
                                    <span class="inline-flex items-start gap-1.5">
                                        <span class="material-icons-round mt-px text-sm" aria-hidden="true">event</span>
                                        <span>Batas pengembalian {{ $loan->due_date->format('d M Y, H:i') }}</span>
                                    </span>
                                </div>
                                @if ($loan->event)
                                    <p class="mt-1.5 break-words text-xs text-slate-500 dark:text-slate-400">
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">Kegiatan:</span> {{ $loan->event->name }}
                                        @if ($loan->event->pic_name)
                                            <span class="text-slate-400 dark:text-slate-500"> · PJ {{ $loan->event->pic_name }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('loans.return-form', $loan) }}"
                           class="inline-flex min-h-10 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:bg-blue-800 sm:ml-[3.25rem] sm:w-auto lg:ml-0 dark:focus:ring-offset-slate-900">
                            <span class="material-icons-round text-base" aria-hidden="true">fact_check</span>
                            Periksa Barang
                        </a>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-start sm:gap-3 sm:px-5 dark:border-slate-800 dark:bg-slate-800/30">
                        <span class="shrink-0 pt-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Barang</span>
                        <div class="flex min-w-0 flex-wrap gap-1.5">
                            @foreach ($loan->loanItems as $li)
                                <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    <span class="break-words">{{ $li->item?->name }}</span>
                                    <strong class="shrink-0">×{{ $li->qty_borrowed }}</strong>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-5">{{ $loans->links() }}</div>
    @endif
</x-app-layout>

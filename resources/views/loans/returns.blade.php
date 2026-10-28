<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengembalian Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Periksa jumlah dan kondisi barang sebelum menyelesaikan pengembalian.</p>
            </div>
            <a href="{{ route('loans.pending') }}" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Persetujuan</a>
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
            <span class="material-icons-round text-5xl text-green-500" aria-hidden="true">task_alt</span>
            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Tidak ada pengembalian yang menunggu.</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pinjaman aktif yang perlu diperiksa akan tampil di sini.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($loans as $loan)
                <article class="flex flex-col gap-4 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 md:flex-row md:items-center">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $loan->borrower?->name }}</span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            @if ($loan->event)
                                <span class="text-xs text-blue-700 dark:text-blue-300">🎯 {{ $loan->event->name }}</span>
                            @endif
                        </div>
                        <div class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $loan->purpose }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $loan->loanItems->count() }} barang •
                            Kembali {{ $loan->due_date->format('d M Y H:i') }}
                            @if ($loan->isOverdue())
                                <span class="font-bold text-red-600">⚠ TERLAMBAT {{ now()->diffForHumans($loan->due_date) }}</span>
                            @endif
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($loan->loanItems as $li)
                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ $li->item?->name }} ×{{ $li->qty_borrowed }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('loans.return-form', $loan) }}"
                       class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:bg-blue-800 md:w-auto md:py-2.5 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-lg">fact_check</span> Validasi Pengembalian
                    </a>
                </article>
            @endforeach
        </div>
        <div class="mt-5">{{ $loans->links() }}</div>
    @endif
</x-app-layout>

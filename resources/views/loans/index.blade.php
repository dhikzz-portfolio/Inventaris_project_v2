<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Peminjaman</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if (auth()->user()->isPeminjam() && ! auth()->user()->canApproveLoans())
                        Lihat status dan riwayat pinjaman Anda.
                    @else
                        Lihat permintaan pinjam dan barang yang sedang dipinjam.
                    @endif
                </p>
            </div>
            <a href="{{ route('loans.create') }}"
               class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                <span class="material-icons-round text-lg" aria-hidden="true">add_shopping_cart</span>
                Pinjam Barang
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php($tabs = [
        '' => ['Semua', $counts['all']],
        'PENDING' => ['Menunggu', $counts['PENDING']],
        'APPROVED' => ['Disetujui', $counts['APPROVED']],
        'DIPINJAM' => ['Sedang dipinjam', $counts['DIPINJAM']],
        'SELESAI' => ['Selesai', $counts['SELESAI']],
        'DIBATALKAN' => ['Dibatalkan', $counts['DIBATALKAN']],
    ])

    <nav class="mb-4 -mx-4 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" aria-label="Filter status peminjaman">
        <div class="flex w-max min-w-full gap-2 sm:w-auto sm:min-w-0 sm:flex-wrap">
            @foreach ($tabs as $key => [$label, $count])
                <a href="{{ route('loans.index', array_filter(['status' => $key])) }}"
                   @if (($status ?? '') === $key) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold transition sm:px-4 sm:text-sm',
                       'border-blue-600 bg-blue-600 text-white shadow-sm' => ($status ?? '') === $key,
                       'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800' => ($status ?? '') !== $key,
                   ])>
                    {{ $label }}
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                        'bg-white/20 text-white' => ($status ?? '') === $key,
                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ($status ?? '') !== $key,
                    ])>{{ $count }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 md:block">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left">Peminjam</th>
                        <th scope="col" class="px-5 py-3 text-left">Tujuan</th>
                        <th scope="col" class="px-5 py-3 text-left">Jadwal</th>
                        <th scope="col" class="px-5 py-3 text-center">Barang</th>
                        <th scope="col" class="px-5 py-3 text-center">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($loans as $loan)
                        <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $loan->borrower?->name ?? 'Peminjam' }}</div>
                                <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower?->major?->name ?: 'Peminjam' }}</div>
                            </td>
                            <td class="max-w-[260px] px-5 py-4">
                                <p class="line-clamp-2 text-slate-700 dark:text-slate-200">{{ $loan->purpose }}</p>
                                @if ($loan->event)
                                    <span class="mt-1 inline-flex items-center gap-1 text-xs text-blue-700 dark:text-blue-300">
                                        <span class="material-icons-round text-sm" aria-hidden="true">event</span>
                                        {{ $loan->event->name }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600 dark:text-slate-300">
                                <div>{{ $loan->start_date->format('d/m/Y, H:i') }}</div>
                                <div class="mt-1 text-slate-400 dark:text-slate-500">sampai {{ $loan->due_date->format('d/m/Y, H:i') }}</div>
                                @if ($loan->isOverdue())
                                    <span class="mt-1 inline-flex items-center gap-1 font-semibold text-red-600 dark:text-red-400">
                                        <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                                        Terlambat {{ $loan->due_date->diffForHumans(now()) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $loan->loanItems->count() }}</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"> jenis</span>
                                @if ($loan->needs_high_value_approval)
                                    <span class="mt-1 block text-[10px] font-semibold text-purple-700 dark:text-purple-300">Perlu persetujuan khusus</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
                                    <span class="mt-1 block text-[10px] text-slate-400 dark:text-slate-500">Menunggu barang diserahkan</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('loans.show', $loan) }}"
                                   class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">
                                    Lihat detail
                                    <span class="material-icons-round text-base" aria-hidden="true">arrow_forward</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <span class="material-icons-round text-4xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Belum ada peminjaman</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Peminjaman yang sesuai akan muncul di sini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-3 md:hidden">
        @forelse ($loans as $loan)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @unless (auth()->user()->isPeminjam() && ! auth()->user()->canApproveLoans())
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $loan->borrower?->name ?? 'Peminjam' }}</p>
                        @endunless
                        <h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-5 text-slate-900 dark:text-white">{{ $loan->purpose }}</h3>
                    </div>
                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>

                @if ($loan->event)
                    <p class="mt-2 inline-flex items-center gap-1.5 text-xs text-blue-700 dark:text-blue-300">
                        <span class="material-icons-round text-sm" aria-hidden="true">event</span>
                        {{ $loan->event->name }}
                    </p>
                @endif

                <div class="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/70">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Mulai</p>
                        <p class="mt-1 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $loan->start_date->format('d/m/Y, H:i') }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Batas kembali</p>
                        <p class="mt-1 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $loan->due_date->format('d/m/Y, H:i') }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <span class="material-icons-round text-base text-slate-400" aria-hidden="true">inventory_2</span>
                        {{ $loan->loanItems->count() }} jenis barang
                    </span>
                    @if ($loan->needs_high_value_approval)
                        <span class="text-[10px] font-semibold text-purple-700 dark:text-purple-300">Perlu persetujuan khusus</span>
                    @endif
                </div>

                @if ($loan->isOverdue())
                    <p class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                        Terlambat {{ $loan->due_date->diffForHumans(now()) }}
                    </p>
                @elseif ($loan->status === \App\Enums\LoanStatus::APPROVED)
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Menunggu barang diserahkan.</p>
                @endif

                <a href="{{ route('loans.show', $loan) }}"
                   class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-blue-200 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 dark:border-slate-700 dark:text-blue-300 dark:hover:bg-slate-800">
                    Lihat detail
                    <span class="material-icons-round text-base" aria-hidden="true">arrow_forward</span>
                </a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center dark:border-slate-700 dark:bg-slate-900">
                <span class="material-icons-round text-4xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Belum ada peminjaman</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Peminjaman yang sesuai akan muncul di sini.</p>
            </div>
        @endforelse
    </div>

    @if ($loans->hasPages())
        <div class="mt-5">{{ $loans->links() }}</div>
    @endif
</x-app-layout>

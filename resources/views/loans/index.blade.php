<x-app-layout>
    @php($isSelfServiceBorrower = auth()->user()->isMember() && ! auth()->user()->canApproveLoans())

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $isSelfServiceBorrower ? 'Riwayat peminjaman' : 'Peminjaman' }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if ($isSelfServiceBorrower)
                        Pantau status dan jadwal pengajuan peminjaman Anda.
                    @else
                        Lihat permintaan pinjam dan barang yang sedang dipinjam.
                    @endif
                </p>
            </div>
            <a href="{{ route('loans.create') }}"
               class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                <span class="material-icons-round text-lg" aria-hidden="true">add_shopping_cart</span>
                {{ $isSelfServiceBorrower ? 'Ajukan peminjaman' : 'Pinjam barang' }}
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
        'PENDING' => ['Menunggu persetujuan', $counts['PENDING']],
        'APPROVED' => ['Disetujui', $counts['APPROVED']],
        'DIPINJAM' => ['Sedang dipinjam', $counts['DIPINJAM']],
        'SELESAI' => ['Selesai', $counts['SELESAI']],
        'DIBATALKAN' => ['Dibatalkan', $counts['DIBATALKAN']],
    ])
    @php($activeStatus = $tabs[$status ?? ''] ?? $tabs[''])

    <form method="GET" action="{{ route('loans.index') }}" class="sticky top-16 z-20 mb-2 grid grid-cols-2 items-center gap-2 bg-slate-50/95 py-2 backdrop-blur sm:flex dark:bg-slate-950/95">
        @if (filled($status ?? null))
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <label for="loan-search" class="sr-only">Cari peminjaman</label>
        <div class="relative col-span-2 min-w-0 sm:flex-1">
            <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
            <input
                id="loan-search"
                type="search"
                name="search"
                value="{{ $search }}"
                maxlength="100"
                placeholder="Cari peminjam, tujuan, kegiatan, barang, atau ID"
                class="h-11 w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500"
            >
        </div>
        <div class="shrink-0 sm:w-auto">
            <label for="loan-status-filter" class="sr-only">Filter status</label>
            <select id="loan-status-filter" name="status"
                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:w-auto"
                    @change="this.form.requestSubmit()">
                <option value="">Semua</option>
                @foreach ($tabs as $key => [$label, $count])
                    <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $label }} ({{ $count }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex w-full gap-2 sm:w-auto">
            <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 sm:flex-none">
                Cari
            </button>
            @if ($search !== '' || $status !== '')
                <a href="{{ route('loans.index') }}"
                   class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    Hapus
                </a>
            @endif
        </div>
    </form>
    <div class="mb-4 text-xs text-slate-500 dark:text-slate-400">
        {{ $activeStatus[1] }} peminjaman ditemukan
    </div>

    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 md:block">
        <div class="max-h-[65vh] overflow-auto overscroll-contain">
            <table @class([
                'w-full table-fixed text-sm',
                'min-w-[760px]' => $isSelfServiceBorrower,
                'min-w-[900px]' => ! $isSelfServiceBorrower,
            ])>
                <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        @unless ($isSelfServiceBorrower)
                            <th scope="col" class="px-4 py-3.5 text-left">Peminjam</th>
                        @endunless
                        <th scope="col" class="px-4 py-3.5 text-left">Tujuan peminjaman</th>
                        <th scope="col" class="px-4 py-3.5 text-left">Jadwal</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Jenis barang</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($loans as $loan)
                        <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            @unless ($isSelfServiceBorrower)
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $loan->borrowerDisplayName() }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower?->major?->name ?: ($loan->borrowerIdentityLabel() ?: 'Peminjam') }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">Dibuat oleh {{ $loan->requesterDisplayName() }}</div>
                                </td>
                            @endunless
                            <td class="max-w-[260px] px-4 py-4">
                                <p class="line-clamp-2 text-slate-700 dark:text-slate-200">{{ $loan->purpose }}</p>
                                @if ($loan->event)
                                    <span class="mt-1 inline-flex items-center gap-1 text-xs text-brand-700 dark:text-brand-300">
                                        <span class="material-icons-round text-sm" aria-hidden="true">event</span>
                                        {{ $loan->event->name }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-600 dark:text-slate-300">
                                <div>{{ $loan->start_date->format('d/m/Y, H:i') }}</div>
                                <div class="mt-1 text-slate-400 dark:text-slate-500">sampai {{ $loan->due_date->format('d/m/Y, H:i') }}</div>
                                @if ($loan->isOverdue())
                                    <span class="mt-1 inline-flex items-center gap-1 font-semibold text-red-600 dark:text-red-400">
                                        <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                                        Terlambat {{ $loan->due_date->diffForHumans(now()) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-9 justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $loan->loanItems->count() }}</span>
                                <span class="mt-1 block text-[10px] text-slate-500 dark:text-slate-400">jenis barang</span>
                                @if ($loan->needs_high_value_approval)
                                    <span class="mt-1 block text-[10px] font-semibold text-purple-700 dark:text-purple-300">Perlu persetujuan khusus</span>
                                @endif
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex justify-center rounded-lg px-2.5 py-1 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                                @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
                                    <span class="mt-1 block text-[10px] text-slate-400 dark:text-slate-500">Menunggu serah terima barang</span>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-center">
                                <a href="{{ route('loans.show', $loan) }}"
                                   class="inline-flex min-h-9 items-center justify-center rounded-lg border border-brand-600 bg-brand-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:border-brand-700 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-brand-500 dark:bg-brand-500 dark:hover:border-brand-400 dark:hover:bg-brand-400 dark:focus:ring-offset-slate-900">
                                    Lihat detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSelfServiceBorrower ? 5 : 6 }}" class="px-4 py-14 text-center">
                                <span class="material-icons-round text-4xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $search !== '' ? 'Tidak ada peminjaman yang cocok dengan pencarian.' : ($isSelfServiceBorrower ? 'Belum ada pengajuan peminjaman' : 'Belum ada data peminjaman') }}</p>
                                @if ($search === '')
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $isSelfServiceBorrower ? 'Pengajuan Anda akan ditampilkan di sini.' : 'Data peminjaman akan ditampilkan di sini.' }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid max-h-[65vh] gap-2 overflow-y-auto overscroll-contain pr-1 md:hidden">
        @forelse ($loans as $loan)
            <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        @unless ($isSelfServiceBorrower)
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">Dipinjam oleh {{ $loan->borrowerDisplayName() }}</p>
                            <p class="truncate text-[11px] text-slate-400 dark:text-slate-500">Dibuat oleh {{ $loan->requesterDisplayName() }}</p>
                        @endunless
                        <h3 class="line-clamp-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $loan->purpose }}</h3>
                    </div>
                    <span class="inline-flex shrink-0 rounded-full px-2 py-1 text-[10px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ $loan->start_date->format('d/m/Y') }} – {{ $loan->due_date->format('d/m/Y') }}</span>
                    <span>{{ $loan->loanItems->count() }} jenis barang</span>
                    @if ($loan->event)
                        <span class="inline-flex items-center gap-1 text-brand-700 dark:text-brand-300">
                            <span class="material-icons-round text-sm" aria-hidden="true">event</span>
                            {{ $loan->event->name }}
                        </span>
                    @endif
                </div>

                @if ($loan->isOverdue())
                    <p class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 dark:text-red-300">
                        <span class="material-icons-round text-sm" aria-hidden="true">warning</span>
                        Terlambat {{ $loan->due_date->diffForHumans(now()) }}
                    </p>
                @endif

                <a href="{{ route('loans.show', $loan) }}"
                   class="mt-2 inline-flex min-h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">
                    Lihat detail
                    <span class="material-icons-round text-sm" aria-hidden="true">arrow_forward</span>
                </a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center dark:border-slate-700 dark:bg-slate-900">
                <span class="material-icons-round text-4xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $search !== '' ? 'Tidak ada peminjaman yang cocok dengan pencarian.' : ($isSelfServiceBorrower ? 'Belum ada pengajuan peminjaman' : 'Belum ada data peminjaman') }}</p>
                @if ($search === '')
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $isSelfServiceBorrower ? 'Pengajuan Anda akan ditampilkan di sini.' : 'Data peminjaman akan ditampilkan di sini.' }}</p>
                @endif
            </div>
        @endforelse
    </div>

    @if ($loans->hasPages())
        <div class="mt-5">{{ $loans->links() }}</div>
    @endif
</x-app-layout>

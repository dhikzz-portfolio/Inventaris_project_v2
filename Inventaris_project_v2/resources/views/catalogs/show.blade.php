<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('catalogs.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Katalog</a>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $catalog->name }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $catalog->category?->name }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $specificationCount }} spesifikasi • {{ $unitCount }} unit inventaris
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if (auth()->user()->canApproveLoans())
                    <a href="{{ route('labels.index', ['catalog' => $catalog->id]) }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-lg">print</span> Cetak Label Unit
                    </a>
                @endif
                @if (auth()->user()->managesInventory() && ! $catalog->isArchived())
                    <a href="{{ route('items.edit', $catalog) }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-lg">edit</span> Ubah Katalog
                    </a>
                @endif
                @if ($canDelete)
                    @php
                        $deleteMessage = 'Hapus katalog "'.$catalog->name.'"?'."\n\n".($canDeletePermanently
                            ? 'Katalog belum memiliki unit/transaksi sehingga akan dihapus permanen.'
                            : 'Katalog memiliki '.$unitCount.' unit fisik dan '.$loanItemCount.' riwayat transaksi. Katalog TIDAK dihapus permanen; data akan dinonaktifkan (arsip) dan riwayat tetap tersimpan.');
                    @endphp
                    <form method="POST" action="{{ route('catalogs.destroy', $catalog) }}"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from($deleteMessage) }})">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/70">
                            <span class="material-icons-round text-lg">{{ $canDeletePermanently ? 'delete' : 'archive' }}</span>
                            {{ $canDeletePermanently ? 'Hapus Katalog' : 'Nonaktifkan Katalog' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    @if ($catalog->isArchived())
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <span class="material-icons-round text-lg shrink-0">archive</span>
            <div>
                <strong>Katalog dinonaktifkan</strong> pada {{ $catalog->archived_at?->format('d M Y H:i') }}.
                Katalog ini tidak ditawarkan untuk peminjaman baru. Unit dan riwayat transaksi tetap tersimpan untuk audit.
                @if ($catalog->archive_reason)<div class="mt-1 text-xs">{{ $catalog->archive_reason }}</div>@endif
                <div class="mt-1 text-xs">Spesifikasi: {{ $specificationCount }} • Unit: {{ $unitCount }} • Riwayat transaksi: {{ $loanItemCount }}</div>
            </div>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span class="font-semibold text-slate-700 dark:text-slate-200">Spesifikasi barang</span>
        <span aria-hidden="true">›</span>
        <span>Pilih merek dan tipe/model untuk melihat unit inventarisnya.</span>
    </div>

    <livewire:catalog.specification-directory :catalog-id="$catalog->id" />
</x-app-layout>

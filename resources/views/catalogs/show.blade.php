<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('catalogs.index') }}" class="text-sm text-gray-500 hover:text-amber-700">Katalog</a>
                    <span class="text-gray-300">/</span>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $catalog->name }}</h2>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $catalog->category?->name }}</span>
                </div>
                <p class="text-sm text-gray-500">
                    {{ $catalog->brand ?: 'Tanpa merek' }}{{ $catalog->model_type ? ' • '.$catalog->model_type : '' }}
                    {{ $catalog->specification ? ' • '.$catalog->specification : '' }}
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if (auth()->user()->canApproveLoans())
                    <a href="{{ route('labels.index', ['catalog' => $catalog->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium">
                        <span class="material-icons-round text-lg">print</span> Cetak Label Unit
                    </a>
                @endif
                @if (auth()->user()->managesInventory() && ! $catalog->isArchived())
                    <a href="{{ route('items.edit', $catalog) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium">
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
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium">
                            <span class="material-icons-round text-lg">{{ $canDeletePermanently ? 'delete' : 'archive' }}</span>
                            {{ $canDeletePermanently ? 'Hapus Katalog' : 'Nonaktifkan Katalog' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    @if ($catalog->isArchived())
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm flex items-start gap-2">
            <span class="material-icons-round text-lg shrink-0">archive</span>
            <div>
                <strong>Katalog dinonaktifkan</strong> pada {{ $catalog->archived_at?->format('d M Y H:i') }}.
                Katalog ini tidak ditawarkan untuk peminjaman baru. Unit dan riwayat transaksi tetap tersimpan untuk audit.
                @if ($catalog->archive_reason)<div class="mt-1 text-xs">{{ $catalog->archive_reason }}</div>@endif
                <div class="mt-1 text-xs">Unit: {{ $unitCount }} • Riwayat transaksi: {{ $loanItemCount }}</div>
            </div>
        </div>
    @endif

    <livewire:catalog.catalog-units :catalog-id="$catalog->id" />
</x-app-layout>

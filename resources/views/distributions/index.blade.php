<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Distribusi barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tentukan merek, model, ruangan, kondisi, dan status unit dari stok yang sudah diterima.</p>
            </div>
            <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Katalog barang</a>
        </div>
    </x-slot>

    <livewire:distribution.stock-distribution-form />

    <div class="mt-8" x-data="{ open: false }">
        <button type="button" @click="open = !open"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
            <span class="material-icons-round text-base" x-text="open ? 'expand_less' : 'expand_more'"></span>
            Relokasi unit yang sudah ada
        </button>

        <div x-show="open" x-cloak class="mt-4">
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                Alur lama: memindahkan unit yang sudah terbit ke ruangan lain. Untuk pemindahan resmi gunakan menu Mutasi agar riwayat lokasi tercatat.
            </p>
            <livewire:distribution.distribution-form />
        </div>
    </div>
</x-app-layout>

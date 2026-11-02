<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Distribusi barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Bagikan unit berdasarkan katalog, merek, dan tipe/model ke lokasi tujuan.</p>
            </div>
            <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">← Katalog barang</a>
        </div>
    </x-slot>

    <livewire:distribution.distribution-form />
</x-app-layout>

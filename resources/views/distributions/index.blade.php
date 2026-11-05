<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Distribusi barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Bagikan unit berdasarkan katalog, merek, dan tipe/model ke lokasi tujuan.</p>
            </div>
            <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Katalog barang</a>
        </div>
    </x-slot>

    <livewire:distribution.distribution-form />
</x-app-layout>

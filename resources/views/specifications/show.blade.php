<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('catalogs.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">Katalog</a>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    <a href="{{ route('catalogs.show', $catalog) }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $catalog->name }}</a>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $specification->displayName() }}</h2>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $catalog->category?->name }} • {{ $catalog->name }} • Daftar unit inventaris
                </p>
            </div>
            <a href="{{ route('catalogs.show', $catalog) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">← Semua spesifikasi</a>
        </div>
    </x-slot>

    @if ($specification->description)
        <div class="mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
            {{ $specification->description }}
        </div>
    @endif

    <livewire:catalog.specification-units :specification-id="$specification->id" />
</x-app-layout>

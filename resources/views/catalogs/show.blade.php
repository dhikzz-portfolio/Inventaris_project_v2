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
                @if (auth()->user()->managesInventory())
                    <a href="{{ route('items.edit', $catalog) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium">
                        <span class="material-icons-round text-lg">edit</span> Ubah Katalog
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <livewire:catalog.catalog-units :catalog-id="$catalog->id" />
</x-app-layout>

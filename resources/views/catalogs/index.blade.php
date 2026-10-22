<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Katalog Barang</h2>
                <p class="text-sm text-gray-500">Jenis barang dan jumlah unit fisik yang terdaftar</p>
            </div>
            @if (auth()->user()->managesInventory())
                <a href="{{ route('items.create') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium">
                    <span class="material-icons-round text-lg">add_box</span> Tambah Katalog
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <livewire:catalog.catalog-directory />
</x-app-layout>

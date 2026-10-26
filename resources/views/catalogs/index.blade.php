<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Katalog Barang</h2>
                <p class="text-sm text-gray-500">Jenis barang dan jumlah unit fisik yang terdaftar</p>
            </div>
            @if (auth()->user()->managesInventory())
                <a href="{{ route('items.create') }}"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
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

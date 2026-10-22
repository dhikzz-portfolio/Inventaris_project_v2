<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="relative col-span-full lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Cari nama barang atau kode unit (G1-02-12)"
                       class="w-full pl-10 pr-3 py-2.5 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
            </div>

            <select wire:model.live="category" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="building" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Gedung</option>
                @foreach ($buildings as $letter)
                    <option value="{{ $letter }}">Gedung {{ $letter }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Lantai</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor }}">Lantai {{ $floor }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">
                        {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="availability" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Ketersediaan</option>
                <option value="available">Ada unit tersedia</option>
                <option value="empty">Tidak ada unit tersedia</option>
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
            <div class="flex items-center gap-2">
                <span>{{ $catalogs->total() }} katalog ditemukan</span>
                @if ($activeFilters > 0)
                    <button wire:click="resetFilters" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-1">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($catalogs as $catalog)
            <a href="{{ route('catalogs.show', $catalog) }}"
               class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">
                <div class="h-28 bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center overflow-hidden">
                    @if ($catalog->photo_path)
                        <img src="{{ asset('storage/'.$catalog->photo_path) }}" alt="{{ $catalog->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="material-icons-round text-5xl text-slate-400">inventory_2</span>
                    @endif
                </div>
                <div class="p-3 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-sm text-gray-800 group-hover:text-amber-700 leading-snug">{{ $catalog->name }}</h3>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">{{ $catalog->units_count }} unit</span>
                    </div>
                    <div class="mt-1 text-[11px] text-gray-500 space-y-0.5">
                        <div>{{ $catalog->category?->name }}@if ($catalog->brand) • {{ $catalog->brand }}@endif</div>
                        @php($effectiveAvailable = max(0, $catalog->available_count - $catalog->activeLoanRemainingQuantity()))
                        <div>Tersedia: <strong>{{ $effectiveAvailable }}</strong> • Rusak: {{ $catalog->damaged_count }}</div>
                    </div>
                    <div class="mt-auto pt-2 flex items-center justify-between text-[11px]">
                        <span class="text-gray-400 inline-flex items-center gap-0.5">
                            @if ($catalog->is_high_value)
                                <span class="material-icons-round text-xs text-purple-600" title="Barang bernilai tinggi">workspace_premium</span>
                            @endif
                            {{ $catalog->unit }}
                        </span>
                        <span class="text-gray-400 group-hover:text-amber-600">Lihat unit →</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full py-16 text-center text-gray-400">
                <span class="material-icons-round text-6xl">{{ $scopeMissing ? 'key_off' : 'search_off' }}</span>
                @if ($scopeMissing)
                    <p class="mt-2 text-sm text-gray-500">
                        Akun Laboran ini belum memiliki cakupan laboratorium.<br>
                        Minta administrator menetapkan ruangan laboratorium pada menu <strong>Pengguna</strong>.
                    </p>
                @else
                    <p class="mt-2 text-sm">Tidak ada katalog yang cocok dengan pencarian/filter.</p>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $catalogs->links() }}</div>
</div>

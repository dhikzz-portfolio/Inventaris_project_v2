<div>
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="relative col-span-full lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Cari nama barang atau kode unit (G1-02-12)"
                       aria-label="Cari katalog barang"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>

            <select wire:model.live="category" aria-label="Filter kategori" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="building" aria-label="Filter gedung" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Gedung</option>
                @foreach ($buildings as $buildingOption)
                    <option value="{{ $buildingOption['code'] }}">{{ $buildingOption['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" aria-label="Filter lantai" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Lantai</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor }}">Lantai {{ $floor }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" aria-label="Filter ruangan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">
                        {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="availability" aria-label="Filter ketersediaan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Ketersediaan</option>
                <option value="available">Ada unit tersedia</option>
                <option value="empty">Tidak ada unit tersedia</option>
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-2">
                <span>{{ $catalogs->total() }} katalog ditemukan</span>
                @if ($activeFilters > 0)
                    <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-blue-700 transition hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-950">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($catalogs as $catalog)
            <a href="{{ route('catalogs.show', $catalog) }}"
               class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 border-l-4 border-l-blue-500 bg-white transition hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:border-l-blue-400 dark:bg-slate-900 dark:hover:border-blue-700">
                <div class="flex h-24 items-center justify-center overflow-hidden bg-slate-100 sm:h-28 dark:bg-slate-800">
                    @if ($catalog->photo_path)
                        <img src="{{ asset('storage/'.$catalog->photo_path) }}" alt="{{ $catalog->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="material-icons-round text-4xl text-blue-400 sm:text-5xl dark:text-blue-300" aria-hidden="true">inventory_2</span>
                    @endif
                </div>
                <div class="flex flex-1 flex-col p-3">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="line-clamp-2 min-w-0 text-sm font-semibold leading-snug text-slate-800 group-hover:text-blue-700 dark:text-slate-100 dark:group-hover:text-blue-300">{{ $catalog->name }}</h3>
                        <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">{{ $catalog->units_count }}</span>
                    </div>
                    <div class="mt-1 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        <div class="line-clamp-1">{{ $catalog->category?->name }}@if ($catalog->brand) • {{ $catalog->brand }}@endif</div>
                        @php($effectiveAvailable = max(0, $catalog->available_count - $catalog->activeLoanRemainingQuantity()))
                        <div class="line-clamp-1">Tersedia: <strong class="text-slate-700 dark:text-slate-200">{{ $effectiveAvailable }}</strong> • Rusak: {{ $catalog->damaged_count }}</div>
                    </div>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-2 text-[11px]">
                        <span class="inline-flex min-w-0 items-center gap-0.5 truncate text-slate-400 dark:text-slate-500">
                            @if ($catalog->is_high_value)
                                <span class="material-icons-round text-xs text-blue-600 dark:text-blue-400" title="Barang bernilai tinggi">workspace_premium</span>
                            @endif
                            <span class="truncate">{{ $catalog->unit }}</span>
                        </span>
                        <span class="shrink-0 font-medium text-blue-600 group-hover:text-blue-800 dark:text-blue-400 dark:group-hover:text-blue-300">Lihat unit →</span>
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

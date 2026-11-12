<div>
    @php
        $selectedFilterCount = collect([$category, $building, $floor, $room, $condition, $status, $availability])->filter(fn ($value) => $value !== '')->count();
        $categoryFilterOptions = $categories->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->name])->values()->all();
        $buildingFilterOptions = collect($buildings)->map(fn ($option) => ['value' => (string) $option['code'], 'label' => $option['name']])->values()->all();
        $floorFilterOptions = collect($floors)->map(fn ($option) => ['value' => (string) $option, 'label' => 'Lantai '.$option])->values()->all();
        $roomFilterOptions = $rooms->map(fn ($option) => [
            'value' => (string) $option->id,
            'label' => $option->building_letter.$option->floor.'-'.str_pad((string) $option->room_no, 2, '0', STR_PAD_LEFT).' — '.$option->name,
        ])->values()->all();
        $conditionFilterOptions = collect($conditions)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
        $statusFilterOptions = collect($statuses)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
        $availabilityFilterOptions = [
            ['value' => 'available', 'label' => 'Ada unit tersedia'],
            ['value' => 'empty', 'label' => 'Tidak ada unit tersedia'],
        ];
    @endphp

    <div x-data="{ filtersOpen: false }" class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative min-w-0 flex-1">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Cari nama katalog atau kategori"
                       aria-label="Cari nama katalog atau kategori"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="catalog-filter-panel"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                Filter
                @if ($selectedFilterCount > 0)
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $selectedFilterCount }}</span>
                @endif
            </button>
        </div>

        <div id="catalog-filter-panel" x-show="filtersOpen" x-cloak x-transition
             class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-searchable-select id="catalog-filter-category" wire-model="category" :selected="$category"
                                     :options="$categoryFilterOptions" placeholder="Semua kategori"
                                     aria-label="Filter kategori" class="min-w-0" />

                <x-searchable-select id="catalog-filter-building" wire-model="building" :selected="$building"
                                     :options="$buildingFilterOptions" placeholder="Semua gedung"
                                     aria-label="Filter gedung" class="min-w-0" />

                <x-searchable-select id="catalog-filter-floor" wire-model="floor" :selected="$floor"
                                     :options="$floorFilterOptions" placeholder="Semua lantai"
                                     aria-label="Filter lantai" class="min-w-0" />

                <x-searchable-select id="catalog-filter-room" wire-model="room" :selected="$room"
                                     :options="$roomFilterOptions" placeholder="Semua ruangan"
                                     aria-label="Filter ruangan" class="min-w-0" />

                <x-searchable-select id="catalog-filter-condition" wire-model="condition" :selected="$condition"
                                     :options="$conditionFilterOptions" placeholder="Semua kondisi"
                                     aria-label="Filter kondisi fisik" class="min-w-0" />

                <x-searchable-select id="catalog-filter-status" wire-model="status" :selected="$status"
                                     :options="$statusFilterOptions" placeholder="Semua status"
                                     aria-label="Filter status operasional" class="min-w-0" />

                <x-searchable-select id="catalog-filter-availability" wire-model="availability" :selected="$availability"
                                     :options="$availabilityFilterOptions" placeholder="Semua ketersediaan"
                                     aria-label="Filter ketersediaan" class="min-w-0" />
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-2">
                <span>{{ $catalogs->total() }} jenis barang ditemukan</span>
                @if ($activeFilters > 0)
                    <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-blue-700 transition hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-950">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
        @forelse ($catalogs as $catalog)
            <a href="{{ route('catalogs.show', $catalog) }}"
               class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 border-l-4 border-l-brand-500 bg-white transition hover:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:border-l-brand-400 dark:bg-slate-900 dark:hover:border-brand-700">
                <div class="flex h-24 items-center justify-center overflow-hidden bg-slate-100 sm:h-28 dark:bg-slate-800">
                    @if ($catalog->photo_path)
                        <img src="{{ asset('storage/'.$catalog->photo_path) }}" alt="{{ $catalog->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="material-icons-round text-4xl text-brand-400 sm:text-5xl dark:text-brand-300" aria-hidden="true">inventory_2</span>
                    @endif
                </div>
                <div class="flex flex-1 flex-col p-3">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="line-clamp-2 min-w-0 text-sm font-semibold leading-snug text-slate-800 group-hover:text-brand-700 dark:text-slate-100 dark:group-hover:text-brand-300">{{ $catalog->name }}</h3>
                        <span class="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-900/60 dark:text-brand-300">{{ $catalog->units_count }}</span>
                    </div>
                    <div class="mt-1 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        <div class="line-clamp-1">{{ $catalog->category?->name }}</div>
                        @php($effectiveAvailable = max(0, $catalog->available_count - $catalog->activeLoanRemainingQuantity()))
                        <div class="line-clamp-1">Tersedia {{ $effectiveAvailable }} · Rusak {{ $catalog->damaged_count }}</div>
                    </div>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-2 text-[11px]">
                        <span class="inline-flex min-w-0 items-center gap-0.5 truncate text-slate-400 dark:text-slate-500">
                            @if ($catalog->is_high_value)
                                <span class="material-icons-round text-xs text-brand-600 dark:text-brand-400" title="Barang bernilai tinggi">workspace_premium</span>
                            @endif
                            <span class="truncate">{{ $catalog->unit }}</span>
                        </span>
                        <span class="shrink-0 font-medium text-brand-600 group-hover:text-brand-800 dark:text-brand-400 dark:group-hover:text-brand-300">Lihat unit <span aria-hidden="true">→</span></span>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full py-16 text-center text-slate-400 dark:text-slate-500">
                <span class="material-icons-round text-6xl text-slate-300 dark:text-slate-600">{{ $scopeMissing ? 'key_off' : 'search_off' }}</span>
                @if ($scopeMissing)
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Akun Laboran ini belum memiliki cakupan laboratorium.<br>
                        Minta administrator menetapkan ruangan laboratorium pada menu <strong>Pengguna</strong>.
                    </p>
                @else
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Tidak ada barang yang cocok dengan pencarian atau filter.</p>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $catalogs->links() }}</div>
</div>

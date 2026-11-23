<div>
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah unit</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $totalUnits }}</div>
        </div>
        <div class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm dark:border-green-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Tersedia</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-300">{{ $availableUnits }}</div>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-white p-4 shadow-sm dark:border-amber-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Rusak</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ $damagedUnits }}</div>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Spesifikasi</div>
            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $specification->brand ?: 'Tanpa Merek' }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $specification->model_type ?: 'Tidak Ditentukan' }}</div>
        </div>
    </div>

    @php
        $selectedFilterCount = collect([$building, $floor, $room, $condition, $status, $year])->filter(fn ($value) => $value !== '')->count();
        $buildingFilterOptions = collect($buildings)->map(fn ($option) => ['value' => (string) $option['code'], 'label' => $option['name']])->values()->all();
        $floorFilterOptions = collect($floors)->map(fn ($option) => ['value' => (string) $option, 'label' => 'Lantai '.$option])->values()->all();
        $roomFilterOptions = $rooms->map(fn ($option) => [
            'value' => (string) $option->id,
            'label' => 'Gedung '.$option->building_letter.$option->floor.'-'.str_pad((string) $option->room_no, 2, '0', STR_PAD_LEFT).' — '.$option->name,
        ])->values()->all();
        $conditionFilterOptions = collect($conditions)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
        $statusFilterOptions = collect($statuses)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
    @endphp
    <div x-data="{ filtersOpen: {{ $selectedFilterCount > 0 ? 'true' : 'false' }} }" class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative min-w-0 flex-1">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari kode inventaris atau nama barang"
                       aria-label="Cari kode inventaris atau nama barang"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="specification-units-filter-panel"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                Filter
                @if ($selectedFilterCount > 0)
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $selectedFilterCount }}</span>
                @endif
            </button>
        </div>

        <div id="specification-units-filter-panel" x-show="filtersOpen" x-cloak x-transition class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-searchable-select id="unit-filter-building" wire-model="building" :selected="$building"
                                 :options="$buildingFilterOptions" placeholder="Semua gedung"
                                 aria-label="Filter gedung" />

            <x-searchable-select id="unit-filter-floor" wire-model="floor" :selected="$floor"
                                 :options="$floorFilterOptions" placeholder="Semua lantai"
                                 aria-label="Filter lantai" />

            <x-searchable-select id="unit-filter-room" wire-model="room" :selected="$room"
                                 :options="$roomFilterOptions" placeholder="Semua ruangan"
                                 aria-label="Filter ruangan" />

            <x-searchable-select id="unit-filter-condition" wire-model="condition" :selected="$condition"
                                 :options="$conditionFilterOptions" placeholder="Semua kondisi"
                                 aria-label="Filter kondisi unit" />

            <x-searchable-select id="unit-filter-status" wire-model="status" :selected="$status"
                                 :options="$statusFilterOptions" placeholder="Semua status"
                                 aria-label="Filter status unit" />

            <input type="number" wire:model.live.debounce.400ms="year" min="1990" max="{{ now()->year + 1 }}"
                   placeholder="Tahun pengadaan" aria-label="Filter tahun pengadaan"
                   class="min-h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
          </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $units->total() }} unit ditemukan</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Hapus filter
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="space-y-3 p-3 lg:hidden" wire:loading.class="opacity-60">
            @forelse ($units as $unit)
                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kode inventaris</p>
                            <p class="break-all font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</p>
                        </div>
                        <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-brand-600 bg-brand-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:border-brand-700 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-brand-500 dark:bg-brand-500 dark:hover:border-brand-400 dark:hover:bg-brand-400 dark:focus:ring-offset-slate-900">Detail</a>
                    </div>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                        Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $unit->room?->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $unit->condition->label() }}</span>
                        <span class="rounded-full px-2 py-0.5 {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Perolehan {{ $unit->acquisition_year ?: '—' }}</span>
                    </div>
                </article>
            @empty
                <p class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada unit yang cocok dengan pencarian atau filter.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[980px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[6%]">
                    <col class="w-[17%]">
                    <col class="w-[12%]">
                    <col class="w-[23%]">
                    <col class="w-[12%]">
                    <col class="w-[12%]">
                    <col class="w-[9%]">
                    <col class="w-[9%]">
                </colgroup>
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-3 py-3.5 text-center">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Kode inventaris</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Gedung</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Ruangan</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Kondisi</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Status</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Pengadaan</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800" wire:loading.class="opacity-60">
                    @forelse ($units as $index => $unit)
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-3 py-4 text-center text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $units->firstItem() + $index }}</td>
                            <td class="whitespace-nowrap px-3 py-4 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                            <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">Gedung {{ $unit->room?->building_letter }}</td>
                            <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                                Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                <div class="mt-0.5 truncate text-slate-400 dark:text-slate-500" title="{{ $unit->room?->name }}">{{ $unit->room?->name }}</div>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex justify-center rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $unit->condition->label() }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-semibold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-10 justify-center rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $unit->acquisition_year ?: '—' }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-brand-600 bg-brand-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:border-brand-700 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-brand-500 dark:bg-brand-500 dark:hover:border-brand-400 dark:hover:bg-brand-400 dark:focus:ring-offset-slate-900">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada unit yang cocok dengan pencarian atau filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($units->hasPages())
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
        @endif
    </div>
</div>

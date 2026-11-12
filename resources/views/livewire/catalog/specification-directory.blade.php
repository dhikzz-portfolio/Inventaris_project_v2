<div>
    @php
        $selectedFilterCount = collect([$brand, $model, $condition, $status, $building, $room])->filter(fn ($value) => $value !== '')->count();
        $brandFilterOptions = collect($brands)->map(fn ($option) => ['value' => (string) $option, 'label' => (string) $option])->values()->all();
        $conditionFilterOptions = collect($conditions)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
        $statusFilterOptions = collect($statuses)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
        $buildingFilterOptions = collect($buildings)->map(fn ($option) => ['value' => (string) $option['code'], 'label' => $option['name']])->values()->all();
        $roomFilterOptions = $rooms->map(fn ($option) => [
            'value' => (string) $option->id,
            'label' => 'Gedung '.$option->building_letter.$option->floor.'-'.str_pad((string) $option->room_no, 2, '0', STR_PAD_LEFT).' — '.$option->name,
        ])->values()->all();
    @endphp
    <div x-data="{ filtersOpen: {{ $selectedFilterCount > 0 ? 'true' : 'false' }} }" class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative min-w-0 flex-1">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari merek atau tipe/model"
                       aria-label="Cari merek atau tipe model"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="specification-directory-filter-panel"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                Filter
                @if ($selectedFilterCount > 0)
                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $selectedFilterCount }}</span>
                @endif
            </button>
        </div>

        <div id="specification-directory-filter-panel" x-show="filtersOpen" x-cloak x-transition class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-searchable-select id="specification-filter-brand" wire-model="brand" :selected="$brand"
                                 :options="$brandFilterOptions" placeholder="Semua merek"
                                 aria-label="Filter merek" />

            <input type="text" wire:model.live.debounce.350ms="model" placeholder="Filter tipe/model" aria-label="Filter tipe model"
                   class="min-h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">

            <x-searchable-select id="specification-filter-condition" wire-model="condition" :selected="$condition"
                                 :options="$conditionFilterOptions" placeholder="Semua kondisi"
                                 aria-label="Filter kondisi unit" />

            <x-searchable-select id="specification-filter-status" wire-model="status" :selected="$status"
                                 :options="$statusFilterOptions" placeholder="Semua status"
                                 aria-label="Filter status unit" />

            <x-searchable-select id="specification-filter-building" wire-model="building" :selected="$building"
                                 :options="$buildingFilterOptions" placeholder="Semua gedung"
                                 aria-label="Filter gedung" />

            <x-searchable-select id="specification-filter-room" wire-model="room" :selected="$room"
                                 :options="$roomFilterOptions" placeholder="Semua ruangan"
                                 aria-label="Filter ruangan" />
          </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $specifications->total() }} spesifikasi ditemukan</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[20%]">
                    <col class="w-[24%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                </colgroup>
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-4 py-3.5 text-left">Merek</th>
                        <th scope="col" class="px-4 py-3.5 text-left">Tipe / model</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Jumlah unit</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Tersedia</th>
                        <th scope="col" class="px-2 py-3.5 text-center">Rusak</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($specifications as $specification)
                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $specification->brand ?: 'Tanpa Merek' }}</td>
                            <td class="truncate px-4 py-4 text-slate-600 dark:text-slate-300" title="{{ $specification->model_type ?: 'Tidak Ditentukan' }}">{{ $specification->model_type ?: 'Tidak Ditentukan' }}</td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-9 justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $specification->total_units }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-9 justify-center rounded-lg bg-green-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-green-700 dark:bg-green-950/50 dark:text-green-300">{{ $specification->available_units }}</span>
                            </td>
                            <td class="px-2 py-4 text-center">
                                <span class="inline-flex min-w-9 justify-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold tabular-nums text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ $specification->damaged_units }}</span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <a href="{{ route('specifications.show', $specification) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg border border-brand-600 bg-brand-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:border-brand-700 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-brand-500 dark:bg-brand-500 dark:text-white dark:hover:border-brand-400 dark:hover:bg-brand-400 dark:focus:ring-offset-slate-900">
                                    Lihat unit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                Belum ada spesifikasi/unit yang cocok dengan pencarian atau filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($specifications->hasPages())
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $specifications->links() }}</div>
        @endif
    </div>
</div>

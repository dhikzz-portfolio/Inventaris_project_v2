<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Cetak Label Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih unit yang ingin dicetak labelnya atau cetak semua hasil filter.</p>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-3 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif        <div class="space-y-3">
        {{-- Filter --}}
        @php
            $activeFilterCount = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->count();
        @endphp
        <div x-data="{ filtersOpen: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }"
             class="overflow-hidden rounded-xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <form method="GET" action="{{ route('labels.index') }}" onchange="this.requestSubmit()">
                <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-end">
                    <label for="label-search" class="sr-only">Cari kode unit atau nama barang</label>
                    <div class="min-w-0 flex-1">
                        <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                        <input id="label-search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama barang atau kode unit, contoh: Helm Las / G1-02-12"
                               class="min-h-10 w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                    <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="label-filter-panel"
                            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                        <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                        Filter
                        @if ($activeFilterCount > 0)
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="sr-only">Cari unit</button>
                </div>

                <div id="label-filter-panel" x-show="filtersOpen" x-cloak x-transition
                     class="mt-3 border-t border-slate-100 p-3 dark:border-slate-800">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="label-catalog" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Katalog</label>
                            <x-searchable-select id="label-catalog" name="catalog" :selected="request('catalog')" submit
                                :options="collect([['value' => '', 'label' => 'Semua katalog']])->merge(collect($catalogs)->map(fn ($name, $id) => ['value' => $id, 'label' => $name]))->values()->all()"
                                placeholder="Cari katalog…" class="mt-1 w-full" />
                        </div>
                        <div>
                            <label for="label-room" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                            <x-searchable-select id="label-room" name="room" :selected="request('room')" submit
                                :options="collect([['value' => '', 'label' => 'Semua ruangan']])->merge($rooms->map(fn ($room) => ['value' => $room->id, 'label' => $room->code.' — '.$room->name, 'hint' => $room->building?->name]))->values()->all()"
                                placeholder="Cari ruangan…" class="mt-1 w-full" />
                        </div>
                        <div>
                            <label for="label-category" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kategori</label>
                            <x-searchable-select id="label-category" name="category" :selected="request('category')" submit
                                :options="collect([['value' => '', 'label' => 'Semua kategori']])->merge($categories->map(fn ($category) => ['value' => $category->id, 'label' => $category->name]))->values()->all()"
                                placeholder="Cari kategori…" class="mt-1 w-full" />
                        </div>
                        <div>
                            <label for="label-condition" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                            <x-searchable-select id="label-condition" name="condition" :selected="request('condition')" submit
                                :options="collect([['value' => '', 'label' => 'Semua kondisi']])->merge(collect($conditions)->map(fn ($conditionOption) => ['value' => $conditionOption->value, 'label' => $conditionOption->label()]))->values()->all()"
                                placeholder="Cari kondisi…" class="mt-1 w-full" />
                        </div>
                        <div>
                            <label for="label-status" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Status</label>
                            <x-searchable-select id="label-status" name="status" :selected="request('status')" submit
                                :options="collect([['value' => '', 'label' => 'Semua status']])->merge(collect($statuses)->map(fn ($statusOption) => ['value' => $statusOption->value, 'label' => $statusOption->label()]))->values()->all()"
                                placeholder="Cari status…" class="mt-1 w-full" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col-reverse gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 sm:flex-row sm:justify-end">
                        @if ($activeFilterCount > 0)
                            <a href="{{ route('labels.index') }}"
                               class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                Hapus filter
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <p class="text-[11px] text-slate-400 dark:text-slate-500 md:hidden">Pilih unit yang ingin dicetak labelnya.</p>

        {{-- Daftar unit + aksi cetak --}}
        <form id="label-print-form" method="POST" action="{{ route('labels.print') }}" target="_blank">
            @csrf
            @foreach ($filters as $filterKey => $filterValue)
                @if ($filterValue !== '')
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endif
            @endforeach

            <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <input type="hidden" name="select_all" value="0">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                    <div class="flex items-center gap-2">
                        <label class="inline-flex min-h-8 items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                            <input type="checkbox" id="select-all-labels" onclick="toggleAllLabels(this)"
                                   class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950">
                            Pilih semua di halaman
                        </label>
                        <button type="button" onclick="clearLabelSelection()"
                                class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Kosongkan pilihan
                        </button>
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><strong id="label-selected-count">0</strong> dipilih</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="label-count" class="rounded-full bg-slate-50 border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-200">0 unit dipilih</span>
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="label-print-menu" aria-label="Buka pilihan cetak label"
                                    class="inline-flex min-h-8 items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                <span class="material-icons-round text-base" aria-hidden="true">print</span>
                                Cetak Label
                                <span class="material-icons-round text-base transition-transform" x-bind:class="{ 'rotate-180': open }" aria-hidden="true">expand_more</span>
                            </button>

                            <div id="label-print-menu" x-show="open" x-cloak x-transition aria-label="Pilihan cetak label"
                                 class="absolute right-0 top-full z-30 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                                <button id="label-print-btn" type="submit" disabled x-on:click="open = false; prepareSelectedLabelPrint()"
                                        class="flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2 text-left transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-slate-800">
                                    <span class="material-icons-round mt-0.5 text-base text-brand-600 dark:text-brand-400" aria-hidden="true">print</span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-semibold text-slate-800 dark:text-slate-100">Cetak unit terpilih</span>
                                        <span id="label-selection-summary" class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">Belum ada unit dipilih</span>
                                    </span>
                                </button>
                                <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
                                <button type="button" x-on:click="open = false; submitAllFilteredLabels()"
                                        class="flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2 text-left transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-500 dark:hover:bg-slate-800">
                                    <span class="material-icons-round mt-0.5 text-base text-slate-500 dark:text-slate-400" aria-hidden="true">select_all</span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-semibold text-slate-800 dark:text-slate-100">Cetak semua sesuai filter</span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">{{ number_format($units->total(), 0, ',', '.') }} unit · semua halaman</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>                <div class="max-h-[60vh] overflow-auto" x-data="{ open: {}, toggleGroup(id) { this.open[id] = ! (this.open[id] ?? false); } }">
                <table class="w-full min-w-[820px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[6%]">
                        <col class="w-[18%]">
                        <col class="w-[22%]">
                        <col class="w-[22%]">
                        <col class="w-[14%]">
                        <col class="w-[14%]">
                        <col class="w-[4%]">
                    </colgroup>
                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-2 py-3 text-center"></th>
                            <th scope="col" class="px-3 py-3 text-left">Kode Inventaris</th>
                            <th scope="col" class="px-3 py-3 text-left">Merek / Tipe</th>
                            <th scope="col" class="px-3 py-3 text-left">Lokasi</th>
                            <th scope="col" class="px-2 py-3 text-center">Kondisi</th>
                            <th scope="col" class="px-2 py-3 text-center">Status</th>
                            <th scope="col" class="px-2 py-3 text-center">Satuan</th>
                        </tr>
                    </thead>                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @php
                                $unitGroups = $units->getCollection()->groupBy(fn ($unit) => $unit->item_catalog_id);
                            @endphp
                            @forelse ($unitGroups as $groupUnits)
                                @php
                                    $groupId = $groupUnits->first()->item_catalog_id;
                                    $groupCatalog = $groupUnits->first()->catalog;
                                    $groupBrands = $groupUnits
                                        ->groupBy(fn ($unit) => $unit->specification?->brandLabel() ?? 'Tanpa Merek')
                                        ->map(fn ($brandUnits) => $brandUnits->count());
                                @endphp
                                {{-- Judul kelompok per jenis barang: bisa dilipat, tanpa checkbox. --}}
                                <tr class="cursor-pointer select-none bg-slate-50/80 transition hover:bg-slate-100 dark:bg-slate-800/40 dark:hover:bg-slate-800/70"
                                    :aria-expanded="open[{{ $groupId }}] === true"
                                    @click="toggleGroup({{ $groupId }})"
                                    @keydown.enter.prevent="toggleGroup({{ $groupId }})"
                                    @keydown.space.prevent="toggleGroup({{ $groupId }})"
                                    class="h-10">
                                    <td colspan="7" class="px-3 py-2">
                                        <div class="flex flex-nowrap items-center gap-x-2">
                                            <span class="material-icons-round shrink-0 text-base text-brand-600">inventory_2</span>
                                            <span class="truncate min-w-0 flex-1 text-xs font-bold text-slate-800 dark:text-slate-100" title="{{ $groupCatalog?->name }}">{{ $groupCatalog?->name ?? 'Tanpa katalog' }}</span>
                                            <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">{{ $groupUnits->count() }} unit</span>
                                            <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ $groupCatalog?->category?->name }}</span>
                                            <span class="ml-auto flex flex-nowrap items-center gap-1">
                                                @foreach ($groupBrands as $brandLabel => $brandCount)
                                                    <span class="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $brandLabel }} × {{ $brandCount }}</span>
                                                @endforeach
                                            </span>
                                            <span class="shrink-0 material-icons-round text-base text-slate-400 transition-transform duration-200 dark:text-slate-500"
                                                  :class="open[{{ $groupId }}] === true ? 'rotate-180' : ''">expand_more</span>
                                        </div>
                                    </td>
                                </tr>
                                @foreach ($groupUnits as $unit)
                                    <tr x-show="open[{{ $groupId }}] === true" x-cloak
                                        class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                        <td class="px-2 py-3 text-center">
                                            <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" aria-label="Pilih unit {{ $unit->inventory_code }}"
                                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950"
                                                   onchange="updateLabelCount()"
                                                   @checked(in_array($unit->id, old('unit_ids', [])))>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                        <td class="px-3 py-3">
                                            <div class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200" title="{{ $unit->specification?->displayName() }}">{{ $unit->specification?->brandLabel() ?? 'Tanpa Merek' }}</div>
                                            <div class="truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $unit->specification?->modelLabel() ?? '—' }}</div>
                                        </td>
                                        <td class="truncate px-3 py-3 text-xs text-slate-500 dark:text-slate-400" title="{{ $unit->room?->name ?: 'Lokasi belum diatur' }}">
                                            Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                            <div class="mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">{{ $unit->room?->name ?: 'Lokasi belum diatur' }}</div>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-50 text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-300' : 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300') }}">{{ $unit->condition->label() }}</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <a href="{{ route('units.label', $unit) }}" target="_blank" title="Cetak label satuan / re-print" aria-label="Cetak label unit {{ $unit->inventory_code }}"
                                               class="inline-flex min-h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-500 transition hover:border-brand-200 hover:bg-slate-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:border-brand-800 dark:hover:bg-slate-800/60 dark:hover:text-brand-300">
                                                <span class="material-icons-round text-sm" aria-hidden="true">print</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="7" class="h-9 px-3 py-10 text-center text-sm text-slate-400 dark:text-slate-500">Tidak ada unit yang sesuai filter dalam cakupan akses Anda.</td></tr>
                            @endforelse
                        </tbody>
                </table>
            </div>

                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5">{{ $units->links() }}</div>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif
            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                Daftar ini dibatasi cakupan akses Anda. Centang "Cetak semua yang sesuai filter" untuk mencetak unit di seluruh halaman sekaligus.
            </p>
        </form>
    </div>

    @push('scripts')
    <script>
        function updateLabelCount() {
            const checkboxes = document.querySelectorAll('input[name="unit_ids[]"]');
            const checked = document.querySelectorAll('input[name="unit_ids[]"]:checked').length;
            const countEl = document.getElementById('label-count');
            if (countEl) countEl.textContent = checked + ' unit dipilih';

            const selectedCountEl = document.getElementById('label-selected-count');
            if (selectedCountEl) selectedCountEl.textContent = String(checked);

            const selectionSummary = document.getElementById('label-selection-summary');
            if (selectionSummary) {
                selectionSummary.textContent = checked > 0
                    ? checked + ' unit di halaman ini'
                    : 'Belum ada unit dipilih';
            }

            const selectAllPage = document.getElementById('select-all-labels');
            if (selectAllPage) selectAllPage.indeterminate = checked > 0 && checked < checkboxes.length;

            const clearBtn = document.querySelector('button[onclick="clearLabelSelection()"]');
            if (clearBtn) clearBtn.disabled = checked === 0;

            const printButton = document.getElementById('label-print-btn');
            if (printButton) printButton.disabled = checked === 0;
        }

        function clearLabelSelection() {
            document.querySelectorAll('input[name="unit_ids[]"]').forEach(cb => { cb.checked = false; });
            updateLabelCount();
        }

        function toggleAllLabels(source) {
            document.querySelectorAll('input[name="unit_ids[]"]').forEach((cb) => { cb.checked = source.checked; });
            updateLabelCount();
        }

        function prepareSelectedLabelPrint() {
            const selectAll = document.querySelector('input[name="select_all"]');
            if (selectAll) selectAll.value = '0';
        }

        function submitAllFilteredLabels() {
            const form = document.getElementById('label-print-form');
            const selectAll = form.querySelector('input[name="select_all"]');

            selectAll.value = '1';
            form.requestSubmit();
        }

        document.addEventListener('DOMContentLoaded', updateLabelCount);
    </script>
    @endpush
</x-app-layout>

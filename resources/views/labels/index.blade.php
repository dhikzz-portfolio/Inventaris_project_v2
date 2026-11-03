<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Cetak Label Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih unit yang ingin dicetak labelnya atau cetak semua hasil filter.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:bg-brand-500 dark:hover:bg-brand-600 dark:focus:ring-brand-400 dark:focus:ring-offset-slate-900">
                    <span class="material-icons-round text-base" aria-hidden="true">inventory_2</span> Katalog
                </a>
                <a href="{{ route('scan') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:border-slate-600 dark:focus:ring-brand-400 dark:focus:ring-offset-slate-900">
                    <span class="material-icons-round text-base" aria-hidden="true">qr_code_scanner</span> Scan QR
                </a>
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
    @endif

    <div class="space-y-3">
        {{-- Filter --}}
        @php
            $activeFilterCount = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->count();
        @endphp
        <div x-data="{ filtersOpen: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }"
             class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <form method="GET" action="{{ route('labels.index') }}">
                <div class="flex flex-col gap-2 p-4 sm:flex-row">
                    <label for="label-search" class="sr-only">Cari kode unit atau nama barang</label>
                    <div class="relative min-w-0 flex-1">
                        <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                        <input id="label-search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari kode unit, contoh: G1-02-12"
                               class="min-h-10 w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                    <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="label-filter-panel"
                            class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                        <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                        Filter
                        @if ($activeFilterCount > 0)
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="sr-only">Cari unit</button>
                </div>

                <div id="label-filter-panel" x-show="filtersOpen" x-cloak x-transition
                     class="border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="label-catalog" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Katalog</label>
                            <select id="label-catalog" name="catalog" class="min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Semua katalog</option>
                                @foreach ($catalogs as $catalogId => $catalogName)
                                    <option value="{{ $catalogId }}" @selected(request('catalog') == $catalogId)>{{ $catalogName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="label-room" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Ruangan</label>
                            <select id="label-room" name="room" class="min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Semua ruangan</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected(request('room') == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="label-category" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Kategori</label>
                            <select id="label-category" name="category" class="min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Semua kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="label-condition" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Kondisi</label>
                            <select id="label-condition" name="condition" class="min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Semua kondisi</option>
                                @foreach ($conditions as $conditionOption)
                                    <option value="{{ $conditionOption->value }}" @selected(request('condition') == $conditionOption->value)>{{ $conditionOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="label-status" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">Status</label>
                            <select id="label-status" name="status" class="min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Semua status</option>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption->value }}" @selected(request('status') == $statusOption->value)>{{ $statusOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col-reverse gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($units->total(), 0, ',', '.') }} unit sesuai filter</span>
                        <div class="flex justify-end gap-2">
                            @if ($activeFilterCount > 0)
                                <a href="{{ route('labels.index') }}"
                                   class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                    Hapus filter
                                </a>
                            @endif
                            <button type="submit" x-on:click="filtersOpen = false"
                                    class="inline-flex min-h-9 items-center justify-center rounded-xl bg-brand-600 px-4 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                Terapkan filter
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Daftar unit + aksi cetak --}}
        <form id="label-print-form" method="POST" action="{{ route('labels.print') }}" target="_blank">
            @csrf
            @foreach ($filters as $filterKey => $filterValue)
                @if ($filterValue !== '')
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endif
            @endforeach

            <div class="rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <input type="hidden" name="select_all" value="0">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <label class="inline-flex min-h-9 items-center gap-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                        <input type="checkbox" id="select-all-labels" onclick="toggleAllLabels(this)"
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950">
                        Pilih semua di halaman ini
                    </label>

                    <div class="flex flex-wrap items-center gap-2 self-end sm:self-auto">
                        <span id="label-count" class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-[11px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">0 unit dipilih</span>
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                            <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="label-print-menu" aria-label="Buka pilihan cetak label"
                                    class="inline-flex min-h-9 items-center gap-2 rounded-xl bg-brand-600 px-3.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
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
                </div>

                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full min-w-[860px] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 shadow-[inset_0_-1px_0_0_#e2e8f0] dark:bg-slate-800 dark:text-slate-400 dark:shadow-[inset_0_-1px_0_0_#334155]">
                            <tr>
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3 text-left">Kode Inventaris</th>
                                <th class="px-4 py-3 text-left">Nama Barang</th>
                                <th class="px-4 py-3 text-left">Lokasi</th>
                                <th class="px-4 py-3 text-left">Kondisi</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-center">Cetak Terakhir</th>
                                <th class="px-4 py-3 text-center">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($units as $unit)
                                <tr class="group transition-colors hover:bg-sky-50/60 dark:hover:bg-slate-800/70">
                                    <td class="border-b border-slate-100 px-4 py-3 text-center dark:border-slate-800">
                                        <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" aria-label="Pilih unit {{ $unit->inventory_code }}"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950"
                                               onchange="updateLabelCount()"
                                               @checked(in_array($unit->id, old('unit_ids', [])))>
                                    </td>
                                    <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-bold tracking-tight text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $unit->inventory_code }}</span>
                                    </td>
                                    <td class="min-w-[180px] border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <a href="{{ route('units.show', $unit) }}" class="font-semibold text-slate-800 transition-colors group-hover:text-brand-700 dark:text-slate-100 dark:group-hover:text-brand-300">{{ $unit->catalog?->name }}</a>
                                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $unit->catalog?->category?->name ?: 'Tanpa kategori' }}</div>
                                    </td>
                                    <td class="min-w-[190px] border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <div class="flex items-start gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                                            <span class="material-icons-round mt-0.5 text-sm text-slate-400" aria-hidden="true">location_on</span>
                                            <span>Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}</span>
                                        </div>
                                        <div class="mt-0.5 pl-5 text-xs text-slate-500 dark:text-slate-400">{{ $unit->room?->name ?: 'Lokasi belum diatur' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-200 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-800' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:ring-yellow-800' : 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:ring-orange-800') }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ $unit->condition->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                    </td>
                                    <td class="whitespace-nowrap border-b border-slate-100 px-4 py-3 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $unit->last_label_printed_at?->format('d/m/Y') ?: 'Belum pernah' }}</td>
                                    <td class="border-b border-slate-100 px-4 py-3 text-center dark:border-slate-800">
                                        <a href="{{ route('units.label', $unit) }}" target="_blank" title="Cetak label satuan / re-print" aria-label="Cetak label unit {{ $unit->inventory_code }}"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:border-brand-800 dark:hover:bg-brand-900/40 dark:hover:text-brand-300">
                                            <span class="material-icons-round text-lg" aria-hidden="true">print</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">Tidak ada unit yang sesuai filter dalam cakupan akses Anda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($units->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
                @endif
            </div>

            <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500 md:hidden">
                Geser tabel ke samping untuk melihat semua kolom.
            </p>
            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                Daftar ini dibatasi cakupan akses Anda. Centang "Cetak semua yang sesuai filter" untuk mencetak unit di seluruh halaman sekaligus.
                @if ($errors->any()) <span class="text-red-600 dark:text-red-400">{{ $errors->first() }}</span> @endif
            </p>
        </form>
    </div>

    @push('scripts')
    <script>
        function updateLabelCount() {
            const checkboxes = document.querySelectorAll('input[name="unit_ids[]"]');
            const checked = document.querySelectorAll('input[name="unit_ids[]"]:checked').length;
            const el = document.getElementById('label-count');
            if (el) el.textContent = checked + ' unit dipilih';

            const selectionSummary = document.getElementById('label-selection-summary');
            if (selectionSummary) {
                selectionSummary.textContent = checked > 0
                    ? checked + ' unit di halaman ini'
                    : 'Belum ada unit dipilih';
            }

            const selectAllPage = document.getElementById('select-all-labels');
            if (selectAllPage) selectAllPage.indeterminate = checked > 0 && checked < checkboxes.length;

            const printButton = document.getElementById('label-print-btn');
            if (printButton) printButton.disabled = checked === 0;
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

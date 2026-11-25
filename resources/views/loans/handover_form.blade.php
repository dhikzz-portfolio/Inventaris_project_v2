<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Serah Terima Barang</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pinjaman #{{ $loan->id }} — {{ $loan->purpose }}</p>
            </div>
            <a href="{{ route('loans.show', $loan) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Kembali ke detail</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @php
        $limits = collect($availableUnits)->mapWithKeys(fn ($bucket, $id) => [(int) $id => (int) $bucket['qty_borrowed']])->all();
        $unitMeta = [];
        foreach ($availableUnits as $loanItemId => $bucket) {
            foreach ($bucket['units'] as $unit) {
                $unitMeta[(int) $unit->id] = (int) $loanItemId;
            }
        }
        $totalCapacity = (int) array_sum($limits);

        $categories = collect($availableUnits)->pluck('item.category')->filter()->unique('id')->sortBy('name')->values();
        $catalogs = collect($availableUnits)->pluck('item')->filter()->unique('id')->sortBy('name')->values();
        $specifications = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('specification'))
            ->filter()
            ->unique('id')
            ->sortBy(fn ($spec) => $spec->displayName())
            ->values();
        $buildings = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('room'))
            ->filter()
            ->map(fn ($room) => $room->buildingCode())
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $rooms = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('room'))
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
    @endphp

    <div class="w-full min-w-0" x-data="handoverForm({
            limits: {{ \Illuminate\Support\Js::from($limits) }},
            unitItem: {{ \Illuminate\Support\Js::from($unitMeta) }},
            scanUrl: '{{ route('loans.handover-scan', $loan) }}',
            totalCapacity: {{ $totalCapacity }},
        })" x-init="init()">
        <p class="mb-4 rounded-xl border border-brand-100 bg-brand-50/70 p-4 text-sm leading-relaxed text-brand-900 dark:border-brand-900/60 dark:bg-brand-900/30 dark:text-brand-100">
            Serah terima barang ke peminjam dan pilih unit inventaris fisik yang diserahkan.
            Setiap unit yang dipilih akan tercatat kode inventaris, kondisi saat keluar, dan status akan berubah menjadi <strong class="text-brand-700">Sedang Dipinjam</strong>.
        </p>

        {{-- Pencarian & filter unit --}}
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @php
                $categoryFilterOptions = $categories->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->name])->values()->all();
                $catalogFilterOptions = $catalogs->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->name])->values()->all();
                $specificationFilterOptions = $specifications->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->displayName()])->values()->all();
                $buildingFilterOptions = collect($buildings)->map(fn ($option) => ['value' => (string) $option, 'label' => 'Gedung '.$option])->values()->all();
                $roomFilterOptions = $rooms->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->code.' — '.$option->name])->values()->all();
            @endphp
            <div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Pilih unit yang akan diserahkan</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Cari atau saring unit berdasarkan kategori, katalog, spesifikasi, dan lokasi.</p>
                </div>
                <div class="mt-3 flex items-end gap-2">
                    <div class="min-w-0 flex-1">
                        <label for="handover-search" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Cari unit</label>
                        <input id="handover-search" type="search" x-model="search" @input.debounce.200ms="applyFilters()"
                               placeholder="Nama, spesifikasi, kode, gedung, ruangan…"
                               class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                    <button type="button" @click="filtersOpen = !filtersOpen"
                            :aria-expanded="filtersOpen.toString()"
                            aria-controls="handover-filter-panel"
                            class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-lg text-brand-600 dark:text-brand-300" aria-hidden="true">filter_list</span>
                        Filter
                        <span x-show="totalFiltersActive()" x-cloak class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-100 px-1.5 text-[10px] font-bold text-brand-700 dark:bg-brand-900/60 dark:text-brand-300" x-text="activeFilterCount()"></span>
                        <span class="material-icons-round text-base text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                    </button>
                </div>
            </div>

            <div id="handover-filter-panel" x-show="filtersOpen" x-cloak x-transition
                 class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-700">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="handover-filter-category" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Kategori</label>
                        <x-searchable-select id="handover-filter-category" x-model="filters.category" x-on:change="applyFilters()"
                                             :options="$categoryFilterOptions" placeholder="Semua kategori"
                                             aria-label="Filter kategori" />
                    </div>
                    <div>
                        <label for="handover-filter-catalog" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Katalog</label>
                        <x-searchable-select id="handover-filter-catalog" x-model="filters.catalog" x-on:change="applyFilters()"
                                             :options="$catalogFilterOptions" placeholder="Semua katalog"
                                             aria-label="Filter katalog" />
                    </div>
                    <div>
                        <label for="handover-filter-spec" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Spesifikasi</label>
                        <x-searchable-select id="handover-filter-spec" x-model="filters.spec" x-on:change="applyFilters()"
                                             :options="$specificationFilterOptions" placeholder="Semua spesifikasi"
                                             aria-label="Filter spesifikasi" />
                    </div>
                    <div>
                        <label for="handover-filter-building" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Gedung</label>
                        <x-searchable-select id="handover-filter-building" x-model="filters.building" x-on:change="applyFilters()"
                                             :options="$buildingFilterOptions" placeholder="Semua gedung"
                                             aria-label="Filter gedung" />
                    </div>
                    <div>
                        <label for="handover-filter-room" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Ruangan</label>
                        <x-searchable-select id="handover-filter-room" x-model="filters.room" x-on:change="applyFilters()"
                                             :options="$roomFilterOptions" placeholder="Semua ruangan"
                                             aria-label="Filter ruangan" />
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="button" @click="resetFilters()" x-show="totalFiltersActive()" x-cloak
                            class="inline-flex min-h-9 items-center justify-center rounded-xl px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                        Reset filter
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-200">
                        <span x-text="selected.length"></span> unit terpilih
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span x-text="Math.max(0, totalCapacity - selected.length)"></span> unit masih dapat diserahkan
                    </span>
                    <span class="text-slate-500 dark:text-slate-400" x-show="totalFiltersActive()">
                        Menampilkan <strong x-text="totalVisible"></strong> unit sesuai filter
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="selectAll()" :disabled="selected.length >= totalCapacity"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-icons-round text-base">done_all</span> Pilih Semua Unit yang Dapat Diserahkan
                    </button>
                    <button type="button" @click="clearAll()" x-show="selected.length > 0"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base">remove_done</span> Batal Pilih Semua
                    </button>
                    <button type="button" @click="toggleScanner()"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base">qr_code_scanner</span> Pindai QR Unit
                    </button>
                </div>
            </div>
        </div>

        {{-- Panel pindai QR --}}
        <div x-show="scannerOpen" x-cloak class="mb-4 rounded-2xl border border-sky-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Pindai QR Unit</h3>
                </div>
                <button type="button" @click="toggleScanner()" aria-label="Tutup panel pindai"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg">close</span>
                </button>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="relative flex min-h-[180px] items-center justify-center overflow-hidden rounded-lg bg-slate-900">
                        <div id="handover-qr-reader" class="w-full h-full min-h-[180px]"></div>
                        <span x-show="!cameraRunning" class="absolute px-4 text-center text-sm text-slate-400 pointer-events-none">Kamera belum aktif</span>
                    </div>
                    <div class="mt-2 flex justify-center gap-2">
                        <button type="button" x-show="!cameraRunning" @click="startCamera()"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white hover:bg-brand-700">
                            <span class="material-icons-round text-base">photo_camera</span> Aktifkan Kamera
                        </button>
                        <button type="button" x-show="cameraRunning" @click="stopCamera()"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
                            <span class="material-icons-round text-base">photo_camera_back</span> Matikan Kamera
                        </button>
                    </div>
                </div>
                <div>
                    <label for="handover-scan-code" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Masukkan kode inventaris secara manual</label>
                    <div class="flex gap-2">
                        <input id="handover-scan-code" type="text" x-model="scanCode" @keydown.enter.prevent="submitScan()"
                               placeholder="Contoh: G1-02-12" autocomplete="off"
                               class="min-w-0 flex-1 rounded-xl border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button type="button" @click="submitScan()"
                                class="inline-flex min-h-10 items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Cari</button>
                    </div>
                </div>
            </div>

            <p x-show="scanMessage" class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200" x-text="scanMessage" role="status"></p>
            <p x-show="scanError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" x-text="scanError" role="alert"></p>
        </div>

        <div x-show="totalVisible === 0" x-cloak class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            Tidak ada unit yang cocok dengan pencarian/filter.
            <button type="button" @click="resetFilters()" class="ms-1 inline-flex min-h-8 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50">Hapus filter</button>
        </div>

        <form method="POST" action="{{ route('loans.handover', $loan) }}" id="handover-form">
            @csrf

            <div class="space-y-6">
                @foreach ($availableUnits as $loanItemId => $bucket)
                    @php
                        $item = $bucket['item'];
                        $room = $bucket['room'];
                        $qtyBorrowed = $bucket['qty_borrowed'];
                    @endphp
                    <section data-item-section="{{ $loanItemId }}"
                             x-show="(visibleCount[{{ $loanItemId }}] ?? 0) > 0"
                             class="min-w-0 rounded-2xl border border-sky-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                                <div class="mb-3 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="break-words text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">{{ $item->name }}</span>
                                            <span class="break-all font-mono text-[10px] text-slate-500 dark:text-slate-400">{{ $item->internal_id }}</span>
                                        @if ($item->is_high_value)
                                            <span class="rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">NILAI TINGGI</span>
                                        @endif
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ $item->category?->name ?: 'Tanpa kategori' }}
                                            • {{ $item->specification ?: 'Spesifikasi belum dicatat' }}
                                    @if ($item->brand || $item->model_type)
                                        • {{ trim(($item->brand ?? '').' '.($item->model_type ?? '')) }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500 sm:shrink-0 sm:justify-end sm:text-right sm:text-xs dark:text-slate-400">
                                <div>{{ $qtyBorrowed }} {{ $item->unit }} • Ruang asal: {{ $room?->code ?? '-' }}</div>
                                <div><span class="sm:block">Terpilih <strong x-text="countFor({{ $loanItemId }})"></strong> / {{ $qtyBorrowed }}</span>
                                    <span class="hidden sm:inline">• </span>Sisa <strong x-text="remainingFor({{ $loanItemId }})"></strong></div>
                            </div>
                        </div>

                        @if ($bucket['units']->isEmpty())
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Tidak ada unit yang dapat diserahkan untuk barang ini.
                                Bisa karena seluruh unit sedang dipinjam, tidak aktif, atau rusak.
                            </div>
                        @else
                            <div class="max-h-[min(60vh,560px)] divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                @foreach ($bucket['units'] as $unit)
                                    @php
                                        $unitRoom = $unit->room;
                                        $specLabel = $unit->specification?->displayName() ?: ($item->specification ?: '');
                                        $searchText = strtolower(trim(implode(' ', array_filter([
                                            $unit->inventory_code,
                                            $item->name,
                                            $item->internal_id,
                                            $specLabel,
                                            $item->brand,
                                            $item->model_type,
                                            $unit->locationLabel(),
                                            $unitRoom?->name,
                                            $unitRoom?->code,
                                            $item->category?->name,
                                        ]))));
                                    @endphp
                                    <label id="handover-unit-{{ $unit->id }}"
                                           data-unit-card
                                           data-unit-id="{{ $unit->id }}"
                                           data-item-id="{{ $loanItemId }}"
                                           data-category="{{ $item->category_id }}"
                                           data-catalog="{{ $item->id }}"
                                           data-spec="{{ $unit->specification_id }}"
                                           data-building="{{ $unitRoom?->buildingCode() }}"
                                           data-room="{{ $unitRoom?->id }}"
                                           data-search="{{ $searchText }}"
                                           class="flex min-w-0 cursor-pointer items-start gap-3 p-3 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                                           :class="isDisabled({{ $unit->id }}) ? 'cursor-not-allowed opacity-40' : ''">
                                        <input type="checkbox"
                                               name="unit_assignments[{{ $loanItemId }}][unit_ids][]"
                                               value="{{ $unit->id }}"
                                               x-model="selected"
                                               :disabled="isDisabled({{ $unit->id }})"
                                               class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-950">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $unit->condition->isDamaged() ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $unit->condition->label() }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                            </div>
                                            <div class="text-[12px] font-medium text-slate-700 dark:text-slate-200">{{ $item->name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $specLabel ?: 'Spesifikasi belum dicatat' }}
                                                @if ($item->brand || $item->model_type)
                                                    • {{ trim(($item->brand ?? '').' '.($item->model_type ?? '')) }}
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                                {{ $unit->locationLabel() }}@if ($unitRoom?->name) • {{ $unitRoom->name }} @endif
                                            </div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Unit No. {{ $unit->unit_no }} • {{ $unit->funding_source ? 'Dana: '.$unit->funding_source : '' }}</div>
                                        </div>
                                    </label>
                                @endforeach
                                <div class="flex flex-col gap-2 bg-slate-50 p-3 text-[11px] text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:bg-slate-800 dark:text-slate-300">
                                    <span class="min-w-0">
                                        Dipilih <strong x-text="countFor({{ $loanItemId }})"></strong> dari {{ $qtyBorrowed }} unit yang wajib diserahkan.
                                        Unit tambahan otomatis dinonaktifkan setelah batas terpenuhi.
                                    </span>
                                    <span x-show="countFor({{ $loanItemId }}) === {{ $qtyBorrowed }}" class="inline-flex shrink-0 items-center gap-1 font-semibold text-green-700 dark:text-green-300"><span class="material-icons-round text-sm" aria-hidden="true">check_circle</span> lengkap</span>
                                </div>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Pilih tepat {{ $qtyBorrowed }} unit untuk barang ini. Hapus centang untuk mengganti pilihan sebelum menyimpan.
                            </p>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label value="Catatan serah terima (opsional)" />
                    <textarea name="note" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Kondisi saat serah terima, catatan khusus..."></textarea>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                    <button type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-slate-950">
                        <span class="material-icons-round text-lg">inventory</span>
                        Serahkan Barang + Tandai Unit Dipinjam
                    </button>
                    <a href="{{ route('loans.show', $loan) }}"
                       class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </a>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function handoverForm(config) {
                return {
                    limits: config.limits,
                    unitItem: config.unitItem,
                    scanUrl: config.scanUrl,
                    totalCapacity: config.totalCapacity,
                    selected: [],
                    search: '',
                    filters: { category: '', catalog: '', spec: '', building: '', room: '' },
                    filtersOpen: false,
                    visibleCount: {},
                    totalVisible: Object.keys(config.unitItem).length,
                    scannerOpen: false,
                    cameraRunning: false,
                    html5: null,
                    scanCode: '',
                    scanMessage: '',
                    scanError: '',

                    init() {
                        this.applyFilters();
                    },

                    countFor(itemId) {
                        const key = String(itemId);

                        return this.selected.filter((id) => String(this.unitItem[id]) === key).length;
                    },

                    remainingFor(itemId) {
                        return Math.max(0, (this.limits[itemId] || 0) - this.countFor(itemId));
                    },

                    isDisabled(unitId) {
                        const itemId = this.unitItem[unitId];

                        if (this.selected.map(String).includes(String(unitId))) {
                            return false;
                        }

                        return this.countFor(itemId) >= (this.limits[itemId] || 0);
                    },

                    selectAll() {
                        // Pilih sebanyak kuota tiap barang; tidak memaksakan unit
                        // melebihi batas penyerahan.
                        const perItem = {};
                        Object.keys(this.unitItem).forEach((unitId) => {
                            const itemId = String(this.unitItem[unitId]);
                            const limit = this.limits[itemId] || 0;
                            perItem[itemId] = perItem[itemId] || 0;

                            if (this.selected.map(String).includes(String(unitId))) {
                                perItem[itemId]++;

                                return;
                            }

                            if (perItem[itemId] < limit) {
                                this.selected.push(String(unitId));
                                perItem[itemId]++;
                            }
                        });

                        this.scanError = '';
                        this.scanMessage = 'Seluruh unit yang memenuhi syarat dipilih sesuai batas penyerahan.';
                    },

                    clearAll() {
                        this.selected = [];
                        this.scanMessage = 'Pilihan unit dibatalkan.';
                        this.scanError = '';
                    },

                    resetFilters() {
                        this.search = '';
                        this.filters = { category: '', catalog: '', spec: '', building: '', room: '' };
                        this.applyFilters();
                    },

                    totalFiltersActive() {
                        return this.search.trim() !== '' || Object.values(this.filters).some((value) => value !== '');
                    },

                    activeFilterCount() {
                        return Number(this.search.trim() !== '') + Object.values(this.filters).filter((value) => value !== '').length;
                    },

                    applyFilters() {
                        const term = this.search.toLowerCase().trim();
                        const counts = {};
                        let total = 0;

                        this.$root.querySelectorAll('[data-unit-card]').forEach((el) => {
                            const data = el.dataset;
                            let matches = term === '' || (data.search || '').includes(term);

                            if (matches && this.filters.category && data.category !== this.filters.category) matches = false;
                            if (matches && this.filters.catalog && data.catalog !== this.filters.catalog) matches = false;
                            if (matches && this.filters.spec && data.spec !== this.filters.spec) matches = false;
                            if (matches && this.filters.building && data.building !== this.filters.building) matches = false;
                            if (matches && this.filters.room && data.room !== this.filters.room) matches = false;

                            el.classList.toggle('hidden', !matches);

                            if (matches) {
                                const itemId = data.itemId;
                                counts[itemId] = (counts[itemId] || 0) + 1;
                                total++;
                            }
                        });

                        this.visibleCount = counts;
                        this.totalVisible = total;
                    },

                    async toggleScanner() {
                        this.scannerOpen = !this.scannerOpen;
                        this.scanError = '';
                        this.scanMessage = '';

                        if (this.scannerOpen) {
                            await this.$nextTick();
                            if (!window.Html5Qrcode) {
                                this.scanError = 'Modul kamera belum dimuat — masukkan kode secara manual.';
                            }
                        } else {
                            await this.stopCamera();
                        }
                    },

                    async startCamera() {
                        this.scanError = '';
                        this.scanMessage = 'Menghubungkan ke kamera...';

                        try {
                            if (window.AppScanner) {
                                this.html5 = await window.AppScanner.start('handover-qr-reader', {
                                    onScan: (decoded) => this.onDecoded(decoded),
                                });
                            } else if (window.Html5Qrcode) {
                                const instance = new window.Html5Qrcode('handover-qr-reader');
                                let cameras = [];
                                try { cameras = await window.Html5Qrcode.getCameras(); } catch (e) {}
                                const config = {
                                    fps: 10,
                                    qrbox: (w, h) => {
                                        const edge = Math.floor(Math.min(w, h) * 0.75);
                                        return { width: Math.max(edge, 160), height: Math.max(edge, 160) };
                                    }
                                };
                                if (cameras && cameras.length > 0) {
                                    let selectedId = cameras[0].id;
                                    for (const c of cameras) {
                                        const lbl = (c.label || '').toLowerCase();
                                        if (lbl.includes('back') || lbl.includes('rear') || lbl.includes('belakang') || lbl.includes('environment')) {
                                            selectedId = c.id;
                                            break;
                                        }
                                    }
                                    await instance.start(selectedId, config, (decoded) => this.onDecoded(decoded), () => {});
                                } else {
                                    try {
                                        await instance.start({ facingMode: 'environment' }, config, (decoded) => this.onDecoded(decoded), () => {});
                                    } catch (e2) {
                                        await instance.start({ facingMode: 'user' }, config, (decoded) => this.onDecoded(decoded), () => {});
                                    }
                                }
                                this.html5 = instance;
                            } else {
                                throw new Error('Modul kamera belum siap dimuat.');
                            }
                            this.cameraRunning = true;
                            this.scanMessage = 'Kamera aktif — arahkan ke QR unit.';
                        } catch (e) {
                            this.cameraRunning = false;
                            this.scanError = (e && e.message) ? e.message : 'Kamera tidak tersedia atau izin ditolak. Masukkan kode secara manual.';
                            this.scanMessage = '';
                        }
                    },

                    async stopCamera() {
                        if (this.html5) {
                            if (window.AppScanner) {
                                await window.AppScanner.stop(this.html5);
                            } else {
                                try {
                                    if (this.html5.isScanning) {
                                        await this.html5.stop();
                                    }
                                    await this.html5.clear();
                                } catch (e) {}
                            }
                            this.html5 = null;
                        }

                        this.cameraRunning = false;
                    },

                    async onDecoded(text) {
                        await this.stopCamera();
                        await this.resolveScan(text);
                    },

                    async submitScan() {
                        const value = (this.scanCode || '').trim();

                        if (value === '') {
                            this.scanError = 'Masukkan kode inventaris terlebih dahulu.';

                            return;
                        }

                        await this.resolveScan(value);
                    },

                    async resolveScan(raw) {
                        this.scanError = '';
                        this.scanMessage = '';

                        const params = new URLSearchParams();
                        params.set('code', raw);
                        this.selected.forEach((id) => params.append('selected[]', id));

                        try {
                            const response = await fetch(this.scanUrl + '?' + params.toString(), {
                                headers: { 'Accept': 'application/json' },
                            });
                            const data = await response.json();

                            if (data.status !== 'ok') {
                                this.scanError = data.message || 'QR tidak dapat diproses.';

                                return;
                            }

                            const unit = data.unit;

                            if (!this.selected.map(String).includes(String(unit.id))) {
                                this.selected.push(String(unit.id));
                            }

                            this.scanCode = '';
                            this.scanMessage = 'Unit ' + unit.inventory_code + ' berhasil ditambahkan ke daftar serah terima.';
                            this.search = '';
                            this.filters = { category: '', catalog: '', spec: '', building: '', room: '' };
                            this.applyFilters();

                            await this.$nextTick();
                            document.getElementById('handover-unit-' + unit.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } catch (e) {
                            this.scanError = 'Gagal memproses hasil pindai. Periksa koneksi lalu coba lagi.';
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

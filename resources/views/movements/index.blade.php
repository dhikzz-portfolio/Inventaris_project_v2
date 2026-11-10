<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Mutasi &amp; Pindah Barang</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Satu transaksi dapat memindahkan unit ke beberapa lokasi tujuan sekaligus</p>
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
        $activeFilterCount = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->count();
        $activeAdditionalFilterCount = collect($filters)->except('search')->filter(fn ($value) => $value !== null && $value !== '')->count();
    @endphp
    <form method="GET" action="{{ route('movements.index') }}"
          x-data="{ filtersOpen: {{ $activeAdditionalFilterCount > 0 ? 'true' : 'false' }}, ...directScanner(), submitForm() { this.$el.requestSubmit(); } }"
          class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <div class="mb-3">
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Filter Unit</h3>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Cari nama barang atau kode unit, atau gunakan filter lanjutan.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="movement-search" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Cari kode unit atau nama barang</label>
                <div class="relative">
                    <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                    <input id="movement-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama barang atau kode unit, contoh: Server Rack / G1-02-12"
                           class="min-h-10 w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen.toString()"
                        aria-controls="movement-filter-panel"
                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30 sm:flex-none">
                    <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                    Filter
                    @if ($activeFilterCount > 0)
                        <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $activeFilterCount }}</span>
                    @endif
                    <span class="material-icons-round text-base text-slate-400 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                </button>

                <button type="button" @click="scanOpen ? closeScanner() : openScanner()" :aria-expanded="scanOpen.toString()"
                        aria-controls="direct-scan-section"
                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-sky-200 bg-white px-3 text-sm font-semibold text-sky-700 transition hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-500 dark:border-sky-600 dark:bg-slate-900 dark:text-sky-300 dark:hover:bg-slate-800 sm:flex-none"
                        title="Scan QR code unit untuk mutasi">
                    <span class="material-icons-round text-base" aria-hidden="true">qr_code_scanner</span>
                    <span>Scan</span>
                </button>
            </div>

            <button type="submit" class="sr-only">Cari unit</button>
        </div>

        <!-- Direct Scan Mutasi Section -->
        <div id="direct-scan-section" x-show="scanOpen" x-cloak x-transition
             class="mt-4 overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <div class="flex items-start gap-2">
                    <span class="material-icons-round text-lg text-sky-600 dark:text-sky-400" aria-hidden="true">qr_code_scanner</span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Scan Mutasi Cepat</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">Hasil scan langsung masuk ke daftar unit mutasi tanpa pindah halaman.</p>
                    </div>
                </div>
                <button type="button" @click="closeScanner()"
                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-sm" aria-hidden="true">close</span> Tutup
                </button>
            </div>

            <div class="p-4">
                <div class="relative mx-auto flex min-h-[240px] w-full max-w-sm items-center justify-center overflow-hidden rounded-xl bg-slate-900">
                    <div id="direct-qr-reader" class="h-full w-full min-h-[240px]"></div>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 px-4 text-center" x-show="!running">
                        <span class="material-icons-round text-3xl text-slate-500" aria-hidden="true">photo_camera</span>
                        <span class="text-sm text-slate-400">Kamera belum aktif</span>
                    </div>
                </div>

                <p class="mt-3 text-center text-xs" :class="error ? 'text-red-600 dark:text-red-300' : 'text-slate-500 dark:text-slate-400'"
                   x-text="message || 'Nyalakan kamera lalu arahkan ke QR code unit.'"></p>

                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" x-show="!running" @click="startCamera()"
                            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="material-icons-round text-lg" aria-hidden="true">qr_code_scanner</span> Aktifkan Kamera
                    </button>
                    <button type="button" x-show="running" @click="closeScanner()"
                            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-lg" aria-hidden="true">check</span> Selesai
                    </button>
                </div>
            </div>
        </div>
        <!-- End Direct Scan Mutasi Section -->

        <div id="movement-filter-panel" x-show="filtersOpen" x-cloak x-transition
             class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="movement-catalog" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Jenis barang</label>
                    <select id="movement-catalog" name="catalog" @change="submitForm()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($catalogs as $catalog)
                            <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="movement-building" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Gedung</label>
                    <select id="movement-building" name="building" @change="submitForm()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="movement-floor" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Lantai</label>
                    <select id="movement-floor" name="floor" @change="submitForm()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @for ($floor = 1; $floor <= 9; $floor++)
                            <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="movement-room" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                    <select id="movement-room" name="room" @change="submitForm()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="movement-condition" class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                    <select id="movement-condition" name="condition" @change="submitForm()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-col-reverse gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-end">
                <div class="flex justify-end gap-2">
                    @if ($activeFilterCount > 0)
                        <a href="{{ route('movements.index') }}"
                           class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                            Hapus filter
                        </a>
                    @endif
                    <button type="submit"
                            class="inline-flex min-h-9 items-center justify-center rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-brand-600 dark:text-white">
                        Terapkan filter
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('movements.store') }}"
          x-data="movementSelection({ unitOptions: {{ \Illuminate\Support\Js::from($unitOptions) }}, roomLabels: {{ \Illuminate\Support\Js::from($roomLabels) }}, moveCode: '{{ request('move') ? request('move') : '' }}' })"
          x-init="init()">
        @csrf

        <div class="space-y-5">
            {{-- Daftar unit: tinggi dibatasi + header tabel menempel --}}
            <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Unit ({{ $units->total() }})</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="selectAll()" :disabled="selected.length === pageUnitIds.length"
                                class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Pilih semua di halaman
                        </button>
                        <button type="button" @click="clearSelection()" :disabled="selected.length === 0"
                                class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Kosongkan pilihan
                        </button>
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><strong x-text="selected.length"></strong> unit dipilih</span>
                    </div>
                    <p class="w-full text-[11px] font-normal text-slate-400 md:hidden dark:text-slate-500">Ketuk nama barang untuk membuka daftar unitnya.</p>
                </div>
                @php
                    $unitGroups = $units->getCollection()->groupBy(fn ($unit) => $unit->item_catalog_id);
                @endphp

                {{-- Mobile: setiap jenis barang menjadi kartu yang bisa dibuka, tanpa perlu geser tabel. --}}
                <div class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
                    @forelse ($unitGroups as $groupUnits)
                        @php
                            $groupId = $groupUnits->first()->item_catalog_id;
                            $groupCatalog = $groupUnits->first()->catalog;
                        @endphp
                        <div>
                            <button type="button"
                                    class="flex w-full items-center gap-2 px-4 py-3 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-slate-800/60"
                                    :aria-expanded="openGroups[{{ $groupId }}] === true"
                                    @click="toggleGroup({{ $groupId }})">
                                <span class="material-icons-round shrink-0 text-base text-brand-600" aria-hidden="true">inventory_2</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ $groupCatalog?->name ?? 'Tanpa katalog' }}</span>
                                    <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ $groupUnits->count() }} unit{{ $groupCatalog?->category?->name ? ' · '.$groupCatalog->category->name : '' }}
                                    </span>
                                </span>
                                <span class="material-icons-round shrink-0 text-base text-slate-400 transition-transform duration-200 dark:text-slate-500"
                                      :class="openGroups[{{ $groupId }}] === true ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                            </button>

                            <div x-show="openGroups[{{ $groupId }}] === true" x-cloak class="space-y-2 px-4 pb-3">
                                @foreach ($groupUnits as $unit)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800/60">
                                        <input type="checkbox" value="{{ $unit->id }}" x-model="selected"
                                               aria-label="Pilih unit {{ $unit->inventory_code }}"
                                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</span>
                                                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                            </span>
                                            <span class="mt-1 block truncate text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                {{ $unit->specification?->brandLabel() ?? 'Tanpa Merek' }}<span class="font-normal text-slate-400 dark:text-slate-500"> · {{ $unit->specification?->modelLabel() ?? '—' }}</span>
                                            </span>
                                            <span class="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $unit->locationLabel() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-slate-400">Tidak ada unit yang cocok dengan filter.</p>
                    @endforelse
                </div>

                {{-- Desktop/tablet: tabel penuh dengan header menempel. --}}
                <div class="hidden max-h-[60vh] overflow-auto md:block">
                    <table class="w-full min-w-[820px] table-fixed text-sm">
                        <colgroup>
                            <col class="w-[6%]">
                            <col class="w-[20%]">
                            <col class="w-[24%]">
                            <col class="w-[23%]">
                            <col class="w-[14%]">
                            <col class="w-[13%]">
                        </colgroup>
                        <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-2 py-3 text-center"></th>
                                <th scope="col" class="px-3 py-3 text-left">Kode Inventaris</th>
                                <th scope="col" class="px-3 py-3 text-left">Merek / Tipe</th>
                                <th scope="col" class="px-3 py-3 text-left">Lokasi Asal</th>
                                <th scope="col" class="px-2 py-3 text-center">Kondisi</th>
                                <th scope="col" class="px-2 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
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
                                    tabindex="0" role="button"
                                    :aria-expanded="openGroups[{{ $groupId }}] === true"
                                    @click="toggleGroup({{ $groupId }})"
                                    @keydown.enter.prevent="toggleGroup({{ $groupId }})"
                                    @keydown.space.prevent="toggleGroup({{ $groupId }})"
                                    class="h-10">
                                    <td colspan="6" class="px-3 py-2">
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
                                                  :class="openGroups[{{ $groupId }}] === true ? 'rotate-180' : ''">expand_more</span>
                                        </div>
                                    </td>
                                </tr>
                                @foreach ($groupUnits as $unit)
                                    <tr x-show="openGroups[{{ $groupId }}] === true" x-cloak
                                        class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                        <td class="px-2 py-3 text-center">
                                            <input type="checkbox" value="{{ $unit->id }}" x-model="selected"
                                                   aria-label="Pilih unit {{ $unit->inventory_code }}"
                                                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                        <td class="px-3 py-3">
                                            <div class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200" title="{{ $unit->specification?->displayName() }}">{{ $unit->specification?->brandLabel() ?? 'Tanpa Merek' }}</div>
                                            <div class="truncate text-[10px] text-slate-400 dark:text-slate-500">{{ $unit->specification?->modelLabel() ?? '—' }}</div>
                                        </td>
                                        <td class="truncate px-3 py-3 text-xs text-slate-500 dark:text-slate-400" title="{{ $unit->locationLabel() }}">{{ $unit->locationLabel() }}</td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada unit yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="overflow-x-auto border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">{{ $units->links() }}</div>
            </div>

            {{-- Panel tujuan: di bawah daftar unit --}}
            <div class="space-y-4 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="flex flex-wrap items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">place</span> Tujuan perpindahan
                    <span class="ml-auto rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                        <span x-text="totalCount">0</span> unit
                    </span>
                </h3>

                {{-- Jumlah pindah bersifat otomatis: diturunkan dari unit yang dicentang pada Daftar Unit. --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/60">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Jumlah pindah barang</p>
                            <p class="mt-0.5 text-2xl font-bold leading-none text-slate-900 dark:text-white">
                                <span x-text="totalCount">0</span>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">unit</span>
                            </p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                              :class="remaining === 0 && totalCount > 0 ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200'">
                            Sisa <span x-text="remaining">0</span> unit
                        </span>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                        Otomatis dari unit yang dicentang pada Daftar Unit — tiap unit berisi 1 barang fisik.
                    </p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div class="h-full rounded-full bg-brand-600 transition-all duration-200" :style="`width: ${progressPercent}%`"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] font-semibold" :class="remaining === 0 && totalCount > 0 ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300'">
                        Terpakai <span x-text="assignedCount">0</span> / <span x-text="totalCount">0</span>
                        <span x-show="remaining > 0">• Sisa <span x-text="remaining">0</span> unit belum dialokasikan</span>
                        <span x-show="remaining === 0 && totalCount > 0">• Semua unit sudah dialokasikan</span>
                    </p>
                </div>

                {{-- Distribusi lokasi: bagi jumlah pindah ke beberapa ruangan sampai sisa 0. --}}
                <div>
                    <x-input-label value="Distribusi lokasi" />

                    <div class="mt-1.5 space-y-2">
                        <p x-show="destinations.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                            Belum ada lokasi tujuan. Centang unit di Daftar Unit, lalu tekan Tambah Lokasi.
                        </p>

                        <template x-for="(group, groupIndex) in destinations" :key="group.key">
                            <div class="rounded-xl border p-2.5 transition"
                                 :class="group.unitIds.length > 0 && group.roomId === '' ? 'border-red-300 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20' : 'border-slate-200 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/60'">
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" :for="`destination-room-${group.key}`">1. Ruangan tujuan</label>
                                        <select :id="`destination-room-${group.key}`" x-model="group.roomId"
                                                class="block w-full rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">— pilih ruangan —</option>
                                            <template x-for="[roomId, label] in Object.entries(roomLabels)" :key="roomId">
                                                <option :value="roomId" :disabled="isRoomTaken(roomId, groupIndex)" x-text="label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <span class="mt-4 inline-flex h-7 shrink-0 items-center gap-1 rounded-md bg-white px-2 text-[11px] font-bold text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200">
                                        <span x-text="group.unitIds.length">0</span>
                                        <span class="font-normal text-slate-500 dark:text-slate-400">unit</span>
                                    </span>
                                    <button type="button" @click="removeGroup(groupIndex)" aria-label="Hapus lokasi tujuan"
                                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">×</button>
                                </div>

                                {{-- Pilih unit fisik per kode inventaris, bukan sekadar jumlah, agar merek dan barangnya jelas. --}}
                                <div class="mt-2">
                                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">2. Kode inventaris yang masuk</span>
                                    <div class="max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-950/40">
                                        <template x-for="unitId in groupCandidates(groupIndex)" :key="`pick-${group.key}-${unitId}`">
                                            <label class="flex cursor-pointer items-start gap-2 rounded-md px-1.5 py-1 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                <input type="checkbox" :checked="group.unitIds.map(String).includes(String(unitId))"
                                                       @change="toggleUnit(groupIndex, unitId)"
                                                       class="mt-1 h-3.5 w-3.5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                                                <span class="min-w-0 flex-1 leading-tight">
                                                    <span class="block font-mono text-[11px] font-semibold text-slate-800 dark:text-slate-100" x-text="unitOptions[unitId]?.code || unitId"></span>
                                                    <span class="block truncate text-[10px] text-slate-500 dark:text-slate-400" x-text="`${unitOptions[unitId]?.name || 'Barang'} · ${unitOptions[unitId]?.brand || 'Tanpa Merek'}${unitOptions[unitId]?.room ? ' · dari ' + unitOptions[unitId].room : ''}`"></span>
                                                </span>
                                            </label>
                                        </template>
                                        <p x-show="groupCandidates(groupIndex).length === 0" class="px-1.5 py-1 text-[11px] text-slate-400 dark:text-slate-500">Tidak ada unit tersisa untuk dialokasikan.</p>
                                    </div>
                                    <p x-show="groupCandidates(groupIndex).length > 0" class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        Centang kode inventaris yang pindah ke ruangan ini.
                                    </p>
                                </div>

                                <p x-show="group.unitIds.length > 0 && group.roomId === ''" class="mt-1 text-[11px] font-semibold text-red-700 dark:text-red-300">
                                    Pilih ruangan tujuan untuk lokasi ini.
                                </p>

                                {{-- Payload tetap memakai kontrak lama: room_id + unit_ids[]; panjang unit_ids = jumlah pindah. --}}
                                <template x-if="group.unitIds.length > 0">
                                    <div>
                                        <input type="hidden" :name="`destinations[${groupIndex}][room_id]`" :value="group.roomId">
                                        <template x-for="unitId in group.unitIds" :key="`input-${group.key}-${unitId}`">
                                            <input type="hidden" :name="`destinations[${groupIndex}][unit_ids][]`" :value="unitId">
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addDestinationRow()" :disabled="remaining <= 0"
                            class="mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-brand-300 bg-brand-50/60 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-brand-800 dark:bg-brand-900/20 dark:text-brand-300">
                        <span class="material-icons-round text-base">add_location_alt</span> Tambah Lokasi
                    </button>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                        Centang kode inventaris tiap ruangan sampai sisa 0. Contoh: 10 unit → 6 ke Lab A dan 4 ke Lab B.
                    </p>
                </div>

                <div>
                    <x-input-label for="note" value="Catatan perpindahan" />
                    <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('note') }}</textarea>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Setiap perpindahan menerbitkan kode inventaris baru sesuai ruangan tujuan. Kode lama disimpan sebagai riwayat dan tetap dapat ditelusuri. Cetak ulang label setelah pindah.
                </div>

                <button type="submit" :disabled="!canSubmit"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:disabled:bg-slate-700">
                    <span class="material-icons-round text-lg">swap_horiz</span> Pindah Barang
                </button>
                <p x-show="totalCount > 0 && remaining > 0" class="text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                    Sisa <span x-text="remaining">0</span> unit belum dialokasikan — tombol simpan dinonaktifkan.
                </p>
                <p x-show="missingRoomCount > 0" class="text-[11px] font-semibold text-red-700 dark:text-red-300">
                    <span x-text="missingRoomCount">0</span> lokasi tujuan belum dipilih ruangannya.
                </p>
                <p x-show="totalCount === 0" class="text-[11px] text-slate-400 dark:text-slate-500">
                    Centang minimal satu unit pada Daftar Unit untuk memulai.
                </p>
                <p x-show="moveCode && !Object.values(unitOptions).some((info) => info && info.code === moveCode)"
                   class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Unit <span class="font-mono font-semibold" x-text="moveCode"></span> tidak ditemukan pada data yang tersedia.
                    Coba pilih ruangan yang berbeda atau cek kembali kode inventarisnya.
                </p>
                <p x-show="moveCode && Object.values(unitOptions).some((info) => info && info.code === moveCode)"
                   class="mt-3 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-[11px] leading-relaxed text-green-900 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">
                    Unit <span class="font-mono font-semibold" x-text="moveCode"></span> sudah masuk ke pilihan.
                    Pilih ruangan tujuan pada <strong>Distribusi lokasi</strong>, lalu tekan <strong>Pindah Barang</strong>.
                </p>
            </div>
        </div>
    </form>

    @php
        // Satu kali tekan "Pindah Barang" menerbitkan satu nomor mutasi untuk semua unitnya.
        // Baris riwayat dikelompokkan per nomor itu; baris lama tanpa nomor berdiri sendiri.
        $movementGroups = $movements->getCollection()->groupBy(
            fn ($movement) => $movement->movement_no ?: 'legacy-'.$movement->id
        );
    @endphp

    <div class="mt-6 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5"
         x-data="{ openHistory: {} }">
        <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
            <span class="material-icons-round text-brand-600">route</span> Riwayat perpindahan barang ({{ $movements->total() }} unit)
        </h3>
        <p class="mb-2 text-[11px] text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm md:table-fixed">
                <colgroup>
                    <col class="w-[30%]">
                    <col class="w-[28%]">
                    <col class="w-[24%]">
                    <col class="w-[18%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="py-2.5 pl-9 pr-3 text-left">Unit</th>
                        <th class="px-3 py-2.5 text-left">Kode Lama → Baru</th>
                        <th class="px-3 py-2.5 text-left">Asal → Tujuan</th>
                        <th class="px-3 py-2.5 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movementGroups as $movementKey => $groupMovements)
                        @php
                            $groupFirst = $groupMovements->first();
                            $groupOrigins = $groupMovements->map(fn ($movement) => $movement->fromRoom?->code)->filter()->unique();
                            $groupDestinations = $groupMovements
                                ->groupBy(fn ($movement) => $movement->toRoom?->code ?? '—')
                                ->map(fn ($rows, $roomCode) => $roomCode.' ('.$rows->count().')');
                        @endphp

                        {{-- Baris judul transaksi: semua informasi yang sama untuk satu nomor mutasi tampil sekali. --}}
                        <tr class="bg-slate-50/70 transition hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                            <td colspan="4" class="p-0">
                                <button type="button" class="flex w-full items-start gap-2 px-3 py-2.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                        @click="openHistory['{{ $movementKey }}'] = openHistory['{{ $movementKey }}'] !== true"
                                        :aria-expanded="openHistory['{{ $movementKey }}'] === true">
                                    <span class="material-icons-round mt-0.5 shrink-0 text-base text-slate-400 transition-transform dark:text-slate-500"
                                          :class="openHistory['{{ $movementKey }}'] === true ? 'rotate-180' : ''">expand_more</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="truncate font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ $groupFirst->movement_no ?: 'Tanpa nomor mutasi' }}</span>
                                                <span class="inline-flex shrink-0 items-center rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $groupMovements->count() }} unit</span>
                                            </span>
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $groupFirst->moved_at?->format('d M Y H:i') }}@if ($groupFirst->mover?->name) · {{ $groupFirst->mover->name }}@endif
                                            </span>
                                        </span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">
                                            <span class="font-mono">{{ $groupOrigins->implode(', ') ?: '—' }}</span>
                                            <span class="px-1 text-slate-400 dark:text-slate-500">→</span>
                                            <span class="font-mono">{{ $groupDestinations->implode(', ') }}</span>
                                        </span>
                                    </span>
                                </button>
                            </td>
                        </tr>

                        @foreach ($groupMovements as $movement)
                            <tr x-show="openHistory['{{ $movementKey }}'] === true" x-cloak
                                class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                <td class="py-2.5 pl-9 pr-3">
                                    <a href="{{ route('units.show', $movement->unit) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $movement->unit?->catalog?->name }}</a>
                                    <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $movement->unit?->inventory_code }}</div>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $movement->old_code }} → {{ $movement->new_code }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $movement->fromRoom?->code }} → {{ $movement->toRoom?->code }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-slate-400">Belum ada perpindahan yang tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>

    @push('scripts')
    <script>
        function movementSelection(config) {
            return {
                unitOptions: config.unitOptions || {},
                roomLabels: config.roomLabels || {},
                moveCode: config.moveCode || '',
                selected: [],
                destinations: [],
                nextKey: 1,
                // Semua kelompok daftar unit tertutup saat halaman dibuka; status
                // buka hanya disimpan untuk kelompok yang diklik pengguna.
                openGroups: {},

                toggleGroup(catalogId) {
                    this.openGroups[catalogId] = this.openGroups[catalogId] !== true;
                },

                get pageUnitIds() {
                    return Object.keys(this.unitOptions);
                },

                get assignedCount() {
                    return this.destinations.reduce((total, group) => total + group.unitIds.length, 0);
                },

                // Jumlah pindah otomatis: unit yang sudah dialokasikan + unit yang belum.
                get totalCount() {
                    return this.assignedCount + this.selected.length;
                },

                get remaining() {
                    return this.selected.length;
                },

                get progressPercent() {
                    return this.totalCount === 0
                        ? 0
                        : Math.min(100, Math.round((this.assignedCount / this.totalCount) * 100));
                },

                get missingRoomCount() {
                    return this.destinations.filter((group) => group.unitIds.length > 0 && String(group.roomId) === '').length;
                },

                get canSubmit() {
                    return this.totalCount > 0 && this.remaining === 0 && this.missingRoomCount === 0;
                },

                isRoomTaken(roomId, groupIndex) {
                    if (String(roomId) === '') {
                        return false;
                    }

                    return this.destinations.some(
                        (group, index) => index !== groupIndex && String(group.roomId) === String(roomId)
                    );
                },

                addDestinationRow() {
                    if (this.remaining <= 0) {
                        return;
                    }

                    this.destinations.push({ key: this.nextKey++, roomId: '', unitIds: [] });
                },

                // Daftar unit pada satu baris tujuan: unit milik ruangan itu + unit yang belum dialokasikan.
                groupCandidates(groupIndex) {
                    const group = this.destinations[groupIndex];

                    if (!group) {
                        return [];
                    }

                    return [...new Set([
                        ...group.unitIds.map(String),
                        ...this.selected.map(String),
                    ])];
                },

                // Centang = tarik unit ke ruangan ini; lepas centang = kembalikan ke pilihan.
                toggleUnit(groupIndex, unitId) {
                    const group = this.destinations[groupIndex];
                    const id = String(unitId);

                    if (!group) {
                        return;
                    }

                    if (group.unitIds.map(String).includes(id)) {
                        group.unitIds = group.unitIds.filter((value) => String(value) !== id);

                        if (!this.selected.map(String).includes(id)) {
                            this.selected.push(id);
                        }

                        return;
                    }

                    group.unitIds.push(id);
                    this.selected = this.selected.filter((value) => String(value) !== id);
                },

                // Unit yang sudah masuk ke sebuah lokasi tidak boleh kembali ke pilihan,
                // kalau tidak total dan sisa akan menghitungnya dua kali.
                init() {
                    // Pilihan unit dibagikan ke modul scan QR di halaman ini.
                    window.movementSelectionConfig = { unitOptions: this.unitOptions };

                    // Hasil scan QR masuk ke pilihan unit pada form mutasi.
                    window.addEventListener('movement:scan-unit', (event) => {
                        const unitId = String(event.detail && event.detail.unitId ? event.detail.unitId : '');

                        if (!unitId || !this.unitOptions[unitId]) {
                            return;
                        }

                        if (!this.selected.map(String).includes(unitId)) {
                            this.selected.push(unitId);
                        }
                    });

                    // Jika kode inventaris dibawa dari halaman scan inventaris,
                    // tambahkan unit tersebut ke pilihan agar siap dialokasikan.
                    if (!this.moveCode) {
                        return;
                    }

                    const match = Object.entries(this.unitOptions).find(
                        ([unitId, info]) => info && info.code === this.moveCode
                    );

                    if (match && !this.selected.map(String).includes(match[0])) {
                        this.selected.push(match[0]);
                    }
                },

                selectAll() {
                    const assigned = this.destinations.flatMap((group) => group.unitIds.map(String));

                    this.selected = this.pageUnitIds.filter((unitId) => !assigned.includes(String(unitId)));
                },

                clearSelection() {
                    this.selected = [];
                },

                removeGroup(groupIndex) {
                    const group = this.destinations[groupIndex];

                    if (!group) {
                        return;
                    }

                    group.unitIds.forEach((unitId) => {
                        if (!this.selected.map(String).includes(String(unitId))) {
                            this.selected.push(String(unitId));
                        }
                    });

                    this.destinations.splice(groupIndex, 1);
                },
            };
        }
    </script>
    <script>
    function directScanner() {
        return {
            scanOpen: false,
            scannerInstance: null,
            running: false,
            message: "",
            error: "",

            openScanner() {
                this.scanOpen = true;
                this.error = "";

                if (!this.running) {
                    this.startCamera();
                }
            },

            closeScanner() {
                this.scanOpen = false;
                this.stopCamera();
            },

            async startCamera() {
                if (this.running) {
                    return;
                }

                this.error = "";
                this.message = "Menghubungkan ke kamera...";

                try {
                    this.scannerInstance = await window.AppScanner.start("direct-qr-reader", {
                        onScan: (decoded) => this.onScan(decoded),
                    });

                    this.running = true;
                    this.message = "Kamera aktif — arahkan ke QR code unit.";
                } catch (e) {
                    this.running = false;
                    this.error = (e && e.message) ? e.message : "Kamera tidak tersedia.";
                    this.message = "";
                    alert("Device tidak memiliki kamera. Fitur scan QR tidak tersedia pada device ini.");
                }
            },

            async stopCamera() {
                const instance = this.scannerInstance;

                this.scannerInstance = null;
                this.running = false;

                if (!instance) {
                    return;
                }

                try {
                    await window.AppScanner.stop(instance);
                } catch (e) {
                    // Abaikan kegagalan saat mematikan kamera.
                }
            },

            onScan(text) {
                const value = (text || "").trim();

                if (!value) {
                    return;
                }

                const options = (window.movementSelectionConfig || {}).unitOptions || {};
                const match = Object.entries(options).find(([, info]) => info && info.code === value);

                if (!match) {
                    this.message = "Kode " + value + " tidak ditemukan di daftar unit.";

                    return;
                }

                window.dispatchEvent(new CustomEvent("movement:scan-unit", {
                    detail: { unitId: String(match[0]), code: value },
                }));

                this.error = "";
                this.message = "Unit " + value + " ditambahkan ke daftar mutasi.";
            },
        };
    }
</script>
    @endpush
</x-app-layout>

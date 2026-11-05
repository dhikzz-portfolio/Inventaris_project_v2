@php
    $initialAllocations = old('allocations', []);

    if (empty($initialAllocations)) {
        $initialAllocations = [['room_id' => '', 'quantity' => old('quantity', 1)]];
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengadaan Barang Multi-Lokasi
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Satu pengadaan, jumlah unit dibagi ke beberapa
                    gedung/ruangan</p>
            </div>
            <a href="{{ route('catalogs.index') }}"
                class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">←
                Kembali ke katalog</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <ul class="list-disc space-y-1 ms-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('items.store') }}" enctype="multipart/form-data"
        class="max-w-5xl rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"
        x-data="procurement({
            rooms: {{ \Illuminate\Support\Js::from($roomOptions) }},
            catalogs: {{ \Illuminate\Support\Js::from($catalogMap) }},
            catalogOptions: {{ \Illuminate\Support\Js::from($catalogs->map(fn ($catalogOption) => ['id' => $catalogOption->id, 'name' => $catalogOption->name, 'category_name' => $catalogOption->category?->name])->values()) }},
            allBrands: {{ \Illuminate\Support\Js::from($brands) }},
            initialCatalog: {{ \Illuminate\Support\Js::from((string) old('catalog_id', '')) }},
            initialCatalogName: {{ \Illuminate\Support\Js::from((string) ($catalogs->firstWhere('id', old('catalog_id'))?->name ?? '')) }},
            initialName: {{ \Illuminate\Support\Js::from((string) old('name', '')) }},
            initialCategory: {{ \Illuminate\Support\Js::from((string) old('category_id', '')) }},
            initialBrand: {{ \Illuminate\Support\Js::from((string) old('brand', '')) }},
            initialModel: {{ \Illuminate\Support\Js::from((string) old('model_type', '')) }},
            initialTotal: {{ (int) old('quantity', 1) }},
            initialRows: {{ \Illuminate\Support\Js::from($initialAllocations) }}
        })">
        @csrf

        {{-- Bagian A — Katalog & jumlah pengadaan --}}
        <div class="flex items-center gap-2 mb-4">
            <span
                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">A</span>
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Data Pengadaan &amp; Katalog</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <x-input-label for="catalog_search" value="Katalog barang yang sudah ada (opsional)" />
                <div class="relative mt-1" @click.outside="catalogOpen = false">
                    <input id="catalog_search" type="text" x-model="catalogQuery"
                        @focus="catalogQuery = ''; catalogOpen = true"
                        @click="catalogOpen = true; catalogHighlight = 0"
                        @input="catalogOpen = true; catalogHighlight = 0"
                        @keydown.arrow-down.prevent="highlightNext()"
                        @keydown.arrow-up.prevent="highlightPrev()"
                        @keydown.enter.prevent="chooseHighlighted()"
                        @keydown.escape="catalogOpen = false"
                        @blur="restoreCatalogQuery()"
                        :placeholder="selectedCatalog ? selectedCatalog.name : 'Ketik nama atau kategori katalog…'"
                        autocomplete="off" role="combobox" aria-autocomplete="list"
                        aria-controls="catalog-options" :aria-expanded="catalogOpen.toString()"
                        class="block w-full rounded-xl border-slate-300 bg-white pr-16 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <button type="button" x-show="catalogId" x-cloak @mousedown.prevent="clearCatalog()"
                        aria-label="Hapus pilihan katalog"
                        class="absolute right-8 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                        <span class="material-icons-round text-base">close</span>
                    </button>
                    <span x-show="!catalogId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">expand_more</span>
                    <span x-show="catalogId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">search</span>

                    <ul x-show="catalogOpen" x-cloak id="catalog-options" role="listbox"
                        class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <li role="option" @mousedown.prevent="clearCatalog()" :aria-selected="!catalogId"
                            class="cursor-pointer px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                            — buat katalog baru —
                        </li>
                        <template x-for="(option, index) in filteredCatalogs" :key="option.id">
                            <li role="option" @mousedown.prevent="selectCatalog(option.id)"
                                @mouseenter="catalogHighlight = index" :aria-selected="catalogId === option.id"
                                :class="catalogHighlight === index ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200' : 'text-slate-700 dark:text-slate-200'"
                                class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50 dark:hover:bg-blue-950/40">
                                <span class="block font-medium" x-text="option.name"></span>
                                <span x-show="option.category_name" class="block text-[11px] text-slate-500 dark:text-slate-400" x-text="option.category_name"></span>
                            </li>
                        </template>
                        <li x-show="filteredCatalogs.length === 0" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                            <span class="block font-semibold text-slate-700 dark:text-slate-200">Tidak ada katalog yang cocok.</span>
                            <span class="mt-1 block">Kata kunci pencarian bukan pembuatan barang. Barang baru harus diajukan
                                melalui pengadaan dengan nama &amp; kategori yang sah — bukan dibuat otomatis dari teks pencarian.</span>
                            @if (auth()->user()->isSuperAdmin())
                                <a href="{{ route('masters.index') }}" class="mt-2 inline-flex min-h-8 items-center justify-center rounded-lg bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Kelola kategori di Data Sekolah</a>
                            @endif
                        </li>
                    </ul>
                </div>
                <input type="hidden" name="catalog_id" :value="catalogId || ''">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Ketik untuk mencari katalog, atau biarkan kosong untuk membuat katalog baru. Jangan buat katalog baru
                    hanya karena merek atau tipe/model berbeda.
                </p>
                <div x-show="catalogId" x-cloak
                    class="mt-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                    <span class="font-semibold" x-text="selectedCatalog?.name"></span>
                    — kategori otomatis: <span class="font-semibold"
                        x-text="selectedCatalog?.category_name || '—'"></span>.
                    Nama katalog dan kategori dikunci; isi hanya merek, tipe/model, jumlah, sumber, tahun, dan
                    spesifikasi.
                </div>
            </div>

            <div>
                <x-input-label for="name" value="Nama katalog barang *" />
                <input id="name" name="name" type="text" x-model="name" :disabled="!!catalogId"
                    placeholder="mis. Kursi Umum"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-800" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                    x-text="catalogId ? 'Mengikuti katalog terpilih (tidak dapat diubah).' : 'Wajib untuk katalog baru.'">
                </p>
            </div>

            <div>
                <x-input-label for="category_id" value="Kategori barang" />
                <select id="category_id" name="category_id" x-model.number="categoryId" :disabled="!!catalogId"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-800">
                    <option value="">— pilih kategori —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                    x-text="catalogId ? 'Mengikuti kategori katalog terpilih (tidak dapat diubah).' : 'Wajib untuk katalog baru.'">
                </p>
            </div>

            <div>
                <x-input-label for="brand" value="Merek / brand" />
                <input id="brand" name="brand" type="text" list="brand-options" x-model="brand"
                    placeholder="Futura, Chitose, atau Tanpa Merek"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <datalist id="brand-options">
                    <template x-for="brandOption in brandOptions" :key="brandOption">
                        <option :value="brandOption"></option>
                    </template>
                </datalist>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kosongkan bila tidak bermerek; sistem
                    menyimpan “Tanpa Merek”.</p>
            </div>

            <div>
                <x-input-label for="model_type" value="Tipe / model" />
                <input id="model_type" name="model_type" type="text" list="model-options" x-model="modelType"
                    placeholder="mis. Kursi Plastik Tipe A"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <datalist id="model-options">
                    <template x-for="modelOption in modelOptions" :key="modelOption">
                        <option :value="modelOption"></option>
                    </template>
                </datalist>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kosongkan bila tidak ditentukan; sistem
                    menyimpan “Tidak Ditentukan”.</p>
            </div>

            <div>
                <x-input-label for="quantity" value="Jumlah Pengadaan *" />
                <input id="quantity" name="quantity" type="number" min="1" required x-model.number="quantity"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total unit yang harus dialokasikan tepat
                    habis pada Bagian B.</p>
            </div>

            <div>
                <x-input-label for="unit" value="Satuan *" />
                <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', 'unit')"
                    placeholder="unit, pak, lembar..." required />
            </div>

            <div>
                <x-input-label for="acquisition_year" value="Tahun Perolehan *" />
                <x-text-input id="acquisition_year" name="acquisition_year" type="number" min="1990"
                    max="{{ now()->year + 1 }}" class="mt-1 block w-full" :value="old('acquisition_year', now()->year)" required />
            </div>

            <div>
                <x-input-label for="funding_source" value="Sumber Dana *" />
                <x-text-input id="funding_source" name="funding_source" type="text" class="mt-1 block w-full"
                    :value="old('funding_source', 'BOS')" placeholder="BOS, komite, hibah..." required />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="specification" value="Spesifikasi" />
                <textarea id="specification" name="specification" rows="2"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('specification') }}</textarea>
            </div>

            <div>
                <x-input-label for="photo" value="Foto Barang (opsional)" />
                <input id="photo" name="photo" type="file" accept="image/*"
                    class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 dark:text-slate-300 dark:file:bg-blue-950 dark:file:text-blue-300" />
            </div>

            <label class="flex items-center gap-2 md:col-span-2">
                <input type="checkbox" name="is_high_value" value="1" @checked(old('is_high_value'))
                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Barang <strong>bernilai tinggi</strong> —
                    pengajuan oleh Laboran/K3 wajib persetujuan Wakasek.</span>
            </label>
        </div>

        {{-- Bagian B — Distribusi lokasi --}}
        <div class="mt-8 border-t border-slate-100 pt-6 dark:border-slate-700">
            <div class="flex items-center gap-2 mb-3">
                <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">B</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Distribusi Lokasi</h3>
                <span class="ms-auto text-xs text-slate-500 dark:text-slate-400">
                    Terpakai <strong x-text="allocated"></strong> / <strong x-text="quantity || 0"></strong>
                    • Sisa <strong :class="remaining === 0 ? 'text-green-700' : 'text-red-600'"
                        x-text="remaining"></strong>
                </span>
            </div>

            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="row.key">
                    <div
                        class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-start">
                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Gedung</label>
                                <select x-model.number="row.building_id" @change="row.room_id = ''"
                                    :name="'allocations[' + index + '][building_hint]'"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="">— pilih gedung —</option>
                                    <template x-for="building in buildings" :key="building.id">
                                        <option :value="building.id" x-text="building.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Ruangan *</label>
                                <select x-model.number="row.room_id" :name="'allocations[' + index + '][room_id]'"
                                    required
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="">— pilih ruangan —</option>
                                    <template x-for="room in roomsFor(row)" :key="room.id">
                                        <option :value="room.id" x-text="(room.code ? room.code + ' — ' : '') + (room.name || '')"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Jumlah *</label>
                                <input type="number" min="1" required x-model.number="row.quantity"
                                    :name="'allocations[' + index + '][quantity]'"
                                    :disabled="remaining === 0 && !row.quantity"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>

                            <div class="sm:col-span-2 flex sm:justify-end items-center pt-1">
                                <button type="button" @click="removeRow(index)" :disabled="rows.length === 1"
                                    class="text-xs font-semibold text-red-600 hover:underline disabled:text-gray-300 disabled:no-underline">
                                    Hapus baris
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                            <div class="text-gray-500">
                                Lantai: <strong x-text="rowMeta(row).floor ?? '—'"></strong>
                                • Kode lokasi: <span class="font-mono" x-text="rowMeta(row).code ?? '—'"></span>
                            </div>
                            <div class="text-gray-500">
                                Pratinjau kode inventaris:
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-100"
                                    x-text="preview(row)"></span>
                                <span class="text-slate-500 dark:text-slate-400">(readonly — dibuat backend)</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3 flex items-center gap-3 flex-wrap">
                <button type="button" @click="addRow()" :disabled="remaining <= 0"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-950">
                    <span class="material-icons-round text-base">add</span> Tambah Lokasi
                </button>
                <p class="text-xs" :class="remaining === 0 ? 'text-green-700' : 'text-red-600'">
                    <span x-show="remaining > 0">Sisa <span x-text="remaining"></span> unit belum dialokasikan —
                        simpan dinonaktifkan.</span>
                    <span x-show="remaining === 0">Seluruh unit sudah dialokasikan.</span>
                    <span x-show="remaining < 0" class="font-bold">Alokasi melebihi jumlah pengadaan.</span>
                </p>
            </div>

            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                Satu ruangan hanya boleh muncul sekali. Kode inventaris diterbitkan otomatis mengikuti gedung, lantai,
                ruangan, dan nomor unit berikutnya.
            </p>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500"
                x-bind:disabled="remaining !== 0 || (quantity || 0) < 1">
                Ajukan Pengadaan
            </x-primary-button>
            <a href="{{ route('catalogs.index') }}"
                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">Batal</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function procurement(config) {
                return {
                    quantity: Number(config.initialTotal) || 1,
                    rooms: config.rooms || [],
                    catalogMap: config.catalogs || {},
                    allBrands: config.allBrands || [],
                    catalogId: Number(config.initialCatalog) || '',
                    catalogQuery: config.initialCatalogName || '',
                    catalogOpen: false,
                    catalogHighlight: 0,
                    catalogOptions: config.catalogOptions || [],
                    name: config.initialName || '',
                    categoryId: Number(config.initialCategory) || '',
                    brand: config.initialBrand || '',
                    modelType: config.initialModel || '',
                    rows: (config.initialRows || []).map((row, index) => ({
                        key: index + '-' + Math.random().toString(36).slice(2),
                        building_id: row.building_id ? Number(row.building_id) : '',
                        room_id: row.room_id ? Number(row.room_id) : '',
                        quantity: Number(row.quantity) || 1,
                    })),

                    get buildings() {
                        const seen = new Map();
                        this.rooms.forEach((room) => {
                            if (room.building_id && !seen.has(room.building_id)) {
                                seen.set(room.building_id, {
                                    id: room.building_id,
                                    name: room.building_name || ('Gedung ' + room.building)
                                });
                            }
                        });
                        return [...seen.values()];
                    },

                    get selectedCatalog() {
                        return this.catalogMap[this.catalogId] || null;
                    },

                    get filteredCatalogs() {
                        const term = (this.catalogQuery || '').trim().toLowerCase();
                        if (term === '') return this.catalogOptions;
                        return this.catalogOptions.filter((option) =>
                            option.name.toLowerCase().includes(term) ||
                            (option.category_name || '').toLowerCase().includes(term)
                        );
                    },

                    selectCatalog(id) {
                        this.catalogId = Number(id) || '';
                        this.applyCatalog();
                        this.catalogQuery = this.selectedCatalog?.name || '';
                        this.catalogOpen = false;
                    },

                    clearCatalog() {
                        this.catalogId = '';
                        this.catalogQuery = '';
                        this.catalogOpen = false;
                        this.applyCatalog();
                    },

                    restoreCatalogQuery() {
                        this.catalogQuery = this.catalogId ? (this.selectedCatalog?.name || '') : '';
                    },

                    chooseHighlighted() {
                        const option = this.filteredCatalogs[this.catalogHighlight];
                        if (option) this.selectCatalog(option.id);
                    },

                    highlightNext() {
                        this.catalogOpen = true;
                        this.catalogHighlight = (this.catalogHighlight + 1) % Math.max(1, this.filteredCatalogs.length);
                    },

                    highlightPrev() {
                        this.catalogOpen = true;
                        const total = Math.max(1, this.filteredCatalogs.length);
                        this.catalogHighlight = (this.catalogHighlight - 1 + total) % total;
                    },

                    get brandOptions() {
                        const brands = this.catalogId && this.selectedCatalog ?
                            this.selectedCatalog.brands :
                            this.allBrands;
                        return [...new Set((brands || []).filter(Boolean))];
                    },

                    get modelOptions() {
                        if (!this.catalogId || !this.selectedCatalog) return [];
                        return [...new Set((this.selectedCatalog.models || [])
                            .filter((item) => !this.brand || item.brand === this.brand)
                            .map((item) => item.model)
                            .filter(Boolean))];
                    },

                    /**
                     * Memilih katalog lama mengunci nama & kategori. Nilai diambil
                     * dari katalog, bukan dari input pengguna.
                     */
                    applyCatalog() {
                        if (!this.catalogId || !this.selectedCatalog) {
                            this.name = '';
                            this.categoryId = '';
                            this.brand = '';
                            this.modelType = '';
                            return;
                        }

                        this.name = this.selectedCatalog.name;
                        this.categoryId = this.selectedCatalog.category_id;
                        this.brand = '';
                        this.modelType = '';
                    },

                    get allocated() {
                        return this.rows.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
                    },

                    get remaining() {
                        return (Number(this.quantity) || 0) - this.allocated;
                    },

                    roomsFor(row) {
                        if (!row.building_id) return [];
                        return this.rooms.filter((room) => Number(room.building_id) === Number(row.building_id));
                    },

                    rowMeta(row) {
                        return this.rooms.find((room) => Number(room.id) === Number(row.room_id)) || {};
                    },

                    preview(row) {
                        const room = this.rowMeta(row);
                        if (!room.preview) return '—';
                        const qty = Math.max(1, Number(row.quantity) || 1);
                        if (qty === 1) return room.preview;
                        const parts = room.preview.split('-');
                        const last = Number(parts[parts.length - 1]);
                        parts[parts.length - 1] = String(last + qty - 1).padStart(2, '0');
                        return room.preview + ' … ' + parts.join('-');
                    },

                    addRow() {
                        this.rows.push({
                            key: Date.now() + '-' + Math.random().toString(36).slice(2),
                            building_id: '',
                            room_id: '',
                            quantity: Math.max(1, this.remaining),
                        });
                    },

                    removeRow(index) {
                        if (this.rows.length > 1) this.rows.splice(index, 1);
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

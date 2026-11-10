@php
    $initialGroups = old('spec_groups');

    if (empty($initialGroups)) {
        $initialGroups = [[
            'brand' => old('brand', ''),
            'model_type' => old('model_type', ''),
            'acquisition_year' => old('acquisition_year', now()->year),
            'funding_source' => old('funding_source', 'BOS'),
            'quantity' => old('quantity', 1),
            'allocations' => old('allocations', [[
                'room_id' => '',
                'quantity' => old('quantity', 1),
                'condition' => \App\Enums\Condition::BAIK->value,
                'status' => \App\Enums\UnitStatus::AKTIF->value,
                'note' => '',
            ]]),
        ]];
    }

    $conditionOptions = collect($conditions)->map(fn ($condition) => [
        'value' => $condition->value,
        'label' => $condition->label(),
    ])->values();

    $statusOptions = collect($statuses)->map(fn ($status) => [
        'value' => $status->value,
        'label' => $status->label(),
    ])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengadaan Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Catat katalog, kelompok spesifikasi, dan alokasi ruangan dalam satu pengajuan.</p>
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
        class="max-w-6xl rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"
        x-data="procurement({
            rooms: {{ \Illuminate\Support\Js::from($roomOptions) }},
            catalogs: {{ \Illuminate\Support\Js::from($catalogMap) }},
            catalogOptions: {{ \Illuminate\Support\Js::from($catalogs->map(fn ($catalogOption) => ['id' => $catalogOption->id, 'name' => $catalogOption->name, 'category_name' => $catalogOption->category?->name])->values()) }},
            conditions: {{ \Illuminate\Support\Js::from($conditionOptions) }},
            statuses: {{ \Illuminate\Support\Js::from($statusOptions) }},
            initialCatalog: {{ \Illuminate\Support\Js::from((string) old('catalog_id', '')) }},
            initialCatalogName: {{ \Illuminate\Support\Js::from((string) ($catalogs->firstWhere('id', old('catalog_id'))?->name ?? '')) }},
            initialName: {{ \Illuminate\Support\Js::from((string) old('name', '')) }},
            initialCategory: {{ \Illuminate\Support\Js::from((string) old('category_id', '')) }},
            defaultYear: {{ (int) old('acquisition_year', now()->year) }},
            defaultFunding: {{ \Illuminate\Support\Js::from((string) old('funding_source', 'BOS')) }},
            initialGroups: {{ \Illuminate\Support\Js::from($initialGroups) }}
        })">

        @csrf
        <input type="hidden" name="procurement_mode" value="ALOKASI">
        <input type="hidden" name="quantity" :value="totalQuantity">

        {{-- Bagian A — Katalog --}}
        <div class="flex items-center gap-2 mb-4">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">A</span>
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Data Katalog</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @if ($canSelectExistingCatalog)
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
                            <span class="mt-1 block">Barang baru diajukan sebagai katalog baru dengan nama &amp; kategori yang sah, bukan dibuat otomatis dari teks pencarian.</span>
                        </li>
                    </ul>
                </div>
                <input type="hidden" name="catalog_id" :value="catalogId || ''">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Merek/model diisi per kelompok spesifikasi. Jangan membuat katalog baru hanya karena merek atau tipe berbeda.
                </p>
                <div x-show="catalogId" x-cloak
                    class="mt-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                    <span class="font-semibold" x-text="selectedCatalog?.name"></span>
                    — kategori otomatis: <span class="font-semibold" x-text="selectedCatalog?.category_name || '—'"></span>.
                </div>
            </div>
            @else
            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                Pengajuan Operator selalu berupa barang baru dan dikirim ke Editor/Validator. Penambahan stok pada katalog yang sudah tercatat tidak dapat diajukan dari formulir ini.
            </div>
            @endif

            <div>
                <x-input-label for="name" value="Nama katalog barang *" />
                <input id="name" name="name" type="text" x-model="name" :disabled="!!catalogId"
                    placeholder="mis. Keyboard"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-800" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                    x-text="catalogId ? 'Mengikuti katalog terpilih (tidak dapat diubah).' : 'Wajib untuk katalog baru.'"></p>
            </div>

            <div>
                <x-input-label for="category_id" value="Kategori barang *" />
                <select id="category_id" name="category_id" x-model.number="categoryId" :disabled="!!catalogId"
                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-800">
                    <option value="">— pilih kategori —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="unit" value="Satuan *" />
                <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', 'unit')"
                    placeholder="unit, pak, lembar..." required />
            </div>

            <div>
                <x-input-label for="acquisition_year" value="Tahun perolehan default *" />
                <x-text-input id="acquisition_year" name="acquisition_year" type="number" min="1990"
                    max="{{ now()->year + 1 }}" class="mt-1 block w-full" :value="old('acquisition_year', now()->year)" required />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dipakai bila kelompok spesifikasi tidak mengisi tahun sendiri.</p>
            </div>

            <div>
                <x-input-label for="funding_source" value="Sumber dana default *" />
                <x-text-input id="funding_source" name="funding_source" type="text" class="mt-1 block w-full"
                    :value="old('funding_source', 'BOS')" placeholder="BOS, komite, hibah..." required />
            </div>

            <div>
                <x-input-label for="specification" value="Spesifikasi umum (opsional)" />
                <x-text-input id="specification" name="specification" type="text" class="mt-1 block w-full" :value="old('specification')" />
            </div>

            <div>
                <x-input-label for="photo" value="Foto Barang (opsional)" />
                <input id="photo" name="photo" type="file" accept="image/*"
                    class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-blue-700 hover:file:bg-blue-100 dark:text-slate-300 dark:file:bg-blue-950 dark:file:text-blue-300" />
            </div>

            <label class="flex items-center gap-2 md:col-span-2">
                <input type="checkbox" name="is_high_value" value="1" @checked(old('is_high_value'))
                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Barang <strong>bernilai tinggi</strong> — pengajuan oleh Operator wajib persetujuan Admin.</span>
            </label>
        </div>

        {{-- Bagian B — Kelompok spesifikasi & alokasi ruangan --}}
        <div class="mt-8 border-t border-slate-100 pt-6 dark:border-slate-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">B</span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Kelompok Spesifikasi &amp; Alokasi Ruangan</h3>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Total pengadaan <strong x-text="totalQuantity"></strong> unit •
                    <span :class="allGroupsBalanced ? 'text-green-700' : 'text-red-600'"
                          x-text="allGroupsBalanced ? 'semua kelompok seimbang' : 'alokasi belum seimbang'"></span>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <template x-for="(group, groupIndex) in groups" :key="group.key">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                Kelompok Spesifikasi <span x-text="groupIndex + 1"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-semibold"
                                      :class="groupRemaining(group) === 0 ? 'text-green-700' : 'text-red-600'">
                                    Terpakai <span x-text="groupAllocated(group)"></span> / <span x-text="group.quantity || 0"></span>
                                    • Sisa <span x-text="groupRemaining(group)"></span>
                                </span>
                                <button type="button" @click="removeGroup(groupIndex)" :disabled="groups.length === 1"
                                    class="text-[11px] font-semibold text-red-600 hover:underline disabled:text-gray-300 disabled:no-underline">Hapus kelompok</button>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
                            <div class="sm:col-span-3">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">Merek</label>
                                <input type="text" x-model="group.brand" :name="'spec_groups[' + groupIndex + '][brand]'"
                                    placeholder="mis. Logitech / Tanpa Merek"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">Tipe / model</label>
                                <input type="text" x-model="group.model_type" :name="'spec_groups[' + groupIndex + '][model_type]'"
                                    placeholder="mis. K120"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">Tahun</label>
                                <input type="number" min="1990" max="{{ now()->year + 1 }}" x-model.number="group.acquisition_year"
                                    :name="'spec_groups[' + groupIndex + '][acquisition_year]'"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">Sumber dana</label>
                                <input type="text" x-model="group.funding_source" :name="'spec_groups[' + groupIndex + '][funding_source]'"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">Jumlah *</label>
                                <input type="number" min="1" required x-model.number="group.quantity"
                                    :name="'spec_groups[' + groupIndex + '][quantity]'"
                                    class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <template x-for="(row, rowIndex) in group.allocations" :key="row.key">
                                <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12">
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Gedung</label>
                                            <select x-model.number="row.building_id" @change="row.room_id = ''"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                <option value="">— gedung —</option>
                                                <template x-for="building in buildings" :key="building.id">
                                                    <option :value="building.id" x-text="building.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Ruangan *</label>
                                            <select x-model.number="row.room_id" required
                                                :name="'spec_groups[' + groupIndex + '][allocations][' + rowIndex + '][room_id]'"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                <option value="">— ruangan —</option>
                                                <template x-for="room in roomsFor(row)" :key="room.id">
                                                    <option :value="room.id" x-text="(room.code ? room.code + ' — ' : '') + (room.name || '')"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Jumlah *</label>
                                            <input type="number" min="1" required x-model.number="row.quantity"
                                                :name="'spec_groups[' + groupIndex + '][allocations][' + rowIndex + '][quantity]'"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Kondisi</label>
                                            <select x-model="row.condition"
                                                :name="'spec_groups[' + groupIndex + '][allocations][' + rowIndex + '][condition]'"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                <template x-for="condition in conditions" :key="condition.value">
                                                    <option :value="condition.value" x-text="condition.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Status</label>
                                            <select x-model="row.status"
                                                :name="'spec_groups[' + groupIndex + '][allocations][' + rowIndex + '][status]'"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                <template x-for="status in statuses" :key="status.value">
                                                    <option :value="status.value" x-text="status.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-[11px] font-semibold text-gray-500">Keterangan</label>
                                            <input type="text" maxlength="500" x-model="row.note"
                                                :name="'spec_groups[' + groupIndex + '][allocations][' + rowIndex + '][note]'"
                                                placeholder="mis. kemasan penyok"
                                                class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                        </div>
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-500">
                                        <span>
                                            Lantai <strong x-text="rowMeta(row).floor ?? '—'"></strong>
                                            • Kode lokasi <span class="font-mono" x-text="rowMeta(row).code ?? '—'"></span>
                                            • Pratinjau kode <span class="font-mono font-bold text-slate-800 dark:text-slate-100" x-text="preview(row)"></span>
                                        </span>
                                        <button type="button" @click="removeAllocation(group, rowIndex)" :disabled="group.allocations.length === 1"
                                            class="font-semibold text-red-600 hover:underline disabled:text-gray-300 disabled:no-underline">Hapus baris</button>
                                    </div>
                                </div>
                            </template>

                            <button type="button" @click="addAllocation(group)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-blue-700 transition hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300">
                                <span class="material-icons-round text-sm">add</span> Tambah ruangan
                            </button>
                        </div>

                        <p class="mt-2 text-[11px]" :class="groupRemaining(group) === 0 ? 'text-green-700' : 'text-red-600'">
                            <span x-show="groupRemaining(group) > 0">Sisa <span x-text="groupRemaining(group)"></span> unit kelompok ini belum dialokasikan.</span>
                            <span x-show="groupRemaining(group) === 0">Seluruh unit kelompok ini sudah dialokasikan.</span>
                            <span x-show="groupRemaining(group) < 0" class="font-bold">Alokasi melebihi jumlah kelompok.</span>
                        </p>
                    </section>
                </template>
            </div>

            <div class="mt-4">
                <button type="button" @click="addGroup()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-base">add</span> Tambah kelompok spesifikasi
                </button>
            </div>

            <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                Ruangan yang sama boleh muncul beberapa kali dalam satu kelompok selama kondisi atau keterangannya berbeda (boleh berbeda salah satu, boleh keduanya).
                Setiap baris juga memiliki status operasional sendiri (aktif, hilang, dipinjam, dan lainnya).
                Total alokasi setiap kelompok harus sama dengan jumlah kelompok tersebut, dan total seluruh kelompok menjadi jumlah pengadaan.
                Kode inventaris diterbitkan otomatis oleh backend.
            </p>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500"
                x-bind:disabled="!canSubmit">
                Ajukan Pengadaan
            </x-primary-button>
            <a href="{{ route('catalogs.index') }}"
                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">Batal</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function procurement(config) {
                const defaultYear = Number(config.defaultYear) || new Date().getFullYear();
                const defaultFunding = config.defaultFunding || 'BOS';
                const rooms = config.rooms || [];

                const makeRow = (row) => {
                    const roomId = Number(row?.room_id) || '';
                    const room = rooms.find((item) => Number(item.id) === roomId);

                    return {
                        key: Math.random().toString(36).slice(2),
                        building_id: room ? Number(room.building_id) : '',
                        room_id: roomId,
                        quantity: Number(row?.quantity) || 1,
                        condition: row?.condition || 'BAIK',
                        status: row?.status || 'AKTIF',
                        note: row?.note || '',
                    };
                };

                const makeGroup = (group) => ({
                    key: Math.random().toString(36).slice(2),
                    brand: group?.brand || '',
                    model_type: group?.model_type || '',
                    acquisition_year: Number(group?.acquisition_year) || defaultYear,
                    funding_source: group?.funding_source || defaultFunding,
                    quantity: Number(group?.quantity) || 1,
                    allocations: ((group?.allocations && group.allocations.length) ? group.allocations : [{}]).map(makeRow),
                });

                const initial = (config.initialGroups && config.initialGroups.length)
                    ? config.initialGroups
                    : [{}];

                return {
                    rooms,
                    catalogMap: config.catalogs || {},
                    catalogOptions: config.catalogOptions || [],
                    catalogId: Number(config.initialCatalog) || '',
                    catalogQuery: config.initialCatalogName || '',
                    catalogOpen: false,
                    catalogHighlight: 0,
                    conditions: config.conditions || [],
                    statuses: config.statuses || [],
                    name: config.initialName || '',
                    categoryId: Number(config.initialCategory) || '',
                    groups: initial.map(makeGroup),

                    get buildings() {
                        const seen = new Map();
                        this.rooms.forEach((room) => {
                            if (room.building_id && !seen.has(room.building_id)) {
                                seen.set(room.building_id, {
                                    id: room.building_id,
                                    name: room.building_name || ('Gedung ' + room.building),
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

                    /** Katalog terpilih mengunci nama & kategori; merek/model tetap milik kelompok. */
                    applyCatalog() {
                        if (!this.catalogId || !this.selectedCatalog) {
                            this.name = '';
                            this.categoryId = '';
                            return;
                        }

                        this.name = this.selectedCatalog.name;
                        this.categoryId = this.selectedCatalog.category_id;
                    },

                    get totalQuantity() {
                        return this.groups.reduce((sum, group) => sum + (Number(group.quantity) || 0), 0);
                    },

                    groupAllocated(group) {
                        return group.allocations.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);
                    },

                    groupRemaining(group) {
                        return (Number(group.quantity) || 0) - this.groupAllocated(group);
                    },

                    get allGroupsBalanced() {
                        return this.groups.length > 0 && this.groups.every((group) =>
                            (Number(group.quantity) || 0) > 0 && this.groupRemaining(group) === 0
                        );
                    },

                    get canSubmit() {
                        return this.allGroupsBalanced;
                    },

                    addGroup() {
                        this.groups.push(makeGroup({ quantity: 1 }));
                    },

                    removeGroup(index) {
                        if (this.groups.length > 1) this.groups.splice(index, 1);
                    },

                    addAllocation(group) {
                        const remaining = this.groupRemaining(group);

                        group.allocations.push(makeRow({ quantity: remaining > 0 ? remaining : 1 }));
                    },

                    removeAllocation(group, index) {
                        if (group.allocations.length > 1) group.allocations.splice(index, 1);
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
                };
            }
        </script>
    @endpush
</x-app-layout>

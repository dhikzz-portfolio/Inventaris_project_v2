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
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengadaan Barang Multi-Lokasi</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Satu pengadaan, jumlah unit dibagi ke beberapa gedung/ruangan</p>
            </div>
            <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali ke katalog</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
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
              initialTotal: {{ (int) old('quantity', 1) }},
              initialRows: {{ \Illuminate\Support\Js::from($initialAllocations) }}
          })">
        @csrf

        {{-- Bagian A — Katalog & jumlah pengadaan --}}
        <div class="flex items-center gap-2 mb-4">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">A</span>
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Data Pengadaan &amp; Katalog</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <x-input-label for="catalog_id" value="Katalog barang yang sudah ada (opsional)" />
                <select id="catalog_id" name="catalog_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— buat katalog baru —</option>
                    @foreach ($catalogs as $catalogOption)
                        <option value="{{ $catalogOption->id }}" @selected(old('catalog_id') == $catalogOption->id)>{{ $catalogOption->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Pilih katalog bila hanya menambah stok merek/tipe baru. Jangan buat katalog baru hanya karena merek atau tipe/model berbeda.
                </p>
            </div>

            <div>
                <x-input-label for="name" value="Nama katalog barang" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="mis. Kursi Umum" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Wajib bila tidak memilih katalog yang sudah ada.</p>
            </div>

            <div>
                <x-input-label for="category_id" value="Kategori barang" />
                <select id="category_id" name="category_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— pilih kategori —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Wajib untuk katalog baru.</p>
            </div>

            <div>
                <x-input-label for="brand" value="Merek / brand" />
                <input id="brand" name="brand" type="text" list="brand-options" value="{{ old('brand') }}"
                       placeholder="Futura, Chitose, atau Tanpa Merek"
                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <datalist id="brand-options">
                    @foreach ($brands as $brandOption)
                        <option value="{{ $brandOption }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kosongkan bila tidak bermerek; sistem menyimpan “Tanpa Merek”.</p>
            </div>

            <div>
                <x-input-label for="model_type" value="Tipe / model" />
                <x-text-input id="model_type" name="model_type" type="text" class="mt-1 block w-full"
                              :value="old('model_type')" placeholder="mis. Kursi Plastik Tipe A" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kosongkan bila tidak ditentukan; sistem menyimpan “Tidak Ditentukan”.</p>
            </div>

            <div>
                <x-input-label for="quantity" value="Jumlah Pengadaan *" />
                <input id="quantity" name="quantity" type="number" min="1" required
                       x-model.number="quantity"
                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total unit yang harus dialokasikan tepat habis pada Bagian B.</p>
            </div>

            <div>
                <x-input-label for="unit" value="Satuan *" />
                <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', 'unit')" placeholder="unit, pak, lembar..." required />
            </div>

            <div>
                <x-input-label for="acquisition_year" value="Tahun Perolehan *" />
                <x-text-input id="acquisition_year" name="acquisition_year" type="number" min="1990" max="{{ now()->year + 1 }}"
                              class="mt-1 block w-full" :value="old('acquisition_year', now()->year)" required />
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
                <span class="text-sm text-slate-700 dark:text-slate-300">Barang <strong>bernilai tinggi</strong> — pengajuan oleh Laboran/K3 wajib persetujuan Wakasek.</span>
            </label>
        </div>

        {{-- Bagian B — Distribusi lokasi --}}
        <div class="mt-8 border-t border-slate-100 pt-6 dark:border-slate-700">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">B</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Distribusi Lokasi</h3>
                <span class="ms-auto text-xs text-slate-500 dark:text-slate-400">
                    Terpakai <strong x-text="allocated"></strong> / <strong x-text="quantity || 0"></strong>
                    • Sisa <strong :class="remaining === 0 ? 'text-green-700' : 'text-red-600'" x-text="remaining"></strong>
                </span>
            </div>

            <div class="space-y-3">
                <template x-for="(row, index) in rows" :key="row.key">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/60">
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
                                <select x-model.number="row.room_id"
                                        :name="'allocations[' + index + '][room_id]'"
                                        required
                                        class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="">— pilih ruangan —</option>
                                    <template x-for="room in roomsFor(row)" :key="room.id">
                                        <option :value="room.id" x-text="room.label"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Jumlah *</label>
                                <input type="number" min="1" required x-model.number="row.quantity"
                                       :max="maxForRow(row)"
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
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-100" x-text="preview(row)"></span>
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
                    <span x-show="remaining > 0">Sisa <span x-text="remaining"></span> unit belum dialokasikan — simpan dinonaktifkan.</span>
                    <span x-show="remaining === 0">Seluruh unit sudah dialokasikan.</span>
                    <span x-show="remaining < 0" class="font-bold">Alokasi melebihi jumlah pengadaan.</span>
                </p>
            </div>

            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                Satu ruangan hanya boleh muncul sekali. Kode inventaris diterbitkan otomatis mengikuti gedung, lantai, ruangan, dan nomor unit berikutnya.
            </p>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500"
                              x-bind:disabled="remaining !== 0 || (quantity || 0) < 1">
                Ajukan Pengadaan
            </x-primary-button>
            <a href="{{ route('catalogs.index') }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">Batal</a>
        </div>
    </form>

    @push('scripts')
    <script>
        function procurement(config) {
            return {
                quantity: Number(config.initialTotal) || 1,
                rooms: config.rooms || [],
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
                            seen.set(room.building_id, { id: room.building_id, name: room.building_name || ('Gedung ' + room.building) });
                        }
                    });
                    return [...seen.values()];
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

                maxForRow(row) {
                    const max = 99;
                    return max;
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

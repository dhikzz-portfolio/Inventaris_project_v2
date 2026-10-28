<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Ubah Katalog Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Perbarui informasi barang. Unit fisik dikelola secara terpisah.</p>
            </div>
            <a href="{{ route('catalogs.show', $item) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali ke detail katalog</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <ul class="list-disc ms-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('items.update', $item) }}" enctype="multipart/form-data"
          class="max-w-4xl rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="name" value="Nama Barang *" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $item->name)" required />
            </div>

            <div>
                <x-input-label for="category_id" value="Kategori *" />
                <select id="category_id" name="category_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $item->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="room_id" value="Ruangan *" />
                <select id="room_id" name="room_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" required>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id', $item->room_id) == $room->id)>
                            {{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Memindahkan ruangan akan memperbarui ID internal dan tercatat di riwayat perpindahan.</p>
            </div>

            <div>
                <x-input-label for="location_code" value="Kode Lokasi/Instansi *" />
                <x-text-input id="location_code" name="location_code" type="text" class="mt-1 block w-full" :value="old('location_code', $item->location_code)" required />
            </div>

            <div>
                <x-input-label for="acquisition_year" value="Tahun Perolehan *" />
                <x-text-input id="acquisition_year" name="acquisition_year" type="number" class="mt-1 block w-full" :value="old('acquisition_year', $item->acquisition_year)" required />
            </div>

            <div>
                <x-input-label for="funding_source" value="Sumber Dana *" />
                <x-text-input id="funding_source" name="funding_source" type="text" class="mt-1 block w-full" :value="old('funding_source', $item->funding_source)" required />
            </div>

            <div>
                <x-input-label for="unit" value="Satuan *" />
                <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', $item->unit)" required />
            </div>

            <div>
                <x-input-label for="photo" value="Ganti Foto (opsional)" />
                <input id="photo" name="photo" type="file" accept="image/*"
                       class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:file:bg-blue-950/50 dark:file:text-blue-300" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="specification" value="Spesifikasi" />
                <textarea id="specification" name="specification" rows="3"
                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('specification', $item->specification) }}</textarea>
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-sky-100 bg-sky-50/60 p-4 md:col-span-2 dark:border-slate-700 dark:bg-slate-800/50">
                <input type="checkbox" name="is_high_value" value="1" @checked(old('is_high_value', $item->is_high_value))
                       class="mt-0.5 h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-slate-700 dark:text-slate-200">Barang bernilai tinggi memerlukan persetujuan berjenjang.</span>
            </label>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Perubahan</x-primary-button>
            <a href="{{ route('catalogs.show', $item) }}" class="text-sm text-gray-500 hover:text-gray-800">Batal</a>
        </div>
    </form>
</x-app-layout>

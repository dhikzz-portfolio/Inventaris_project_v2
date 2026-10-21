<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengadaan Barang Baru</h2>
                <p class="text-sm text-gray-500">Nomor register & ID dibuat otomatis; menunggu approval Wakasek Sarpras</p>
            </div>
            <a href="{{ route('items.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali ke katalog</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ms-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('items.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 max-w-4xl">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <x-input-label for="name" value="Nama Barang *" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
            </div>

            <div>
                <x-input-label for="category_id" value="Kategori *" />
                <select id="category_id" name="category_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm" required>
                    <option value="">— pilih kategori —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="room_id" value="Ruangan *" />
                <select id="room_id" name="room_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm" required>
                    <option value="">— pilih ruangan —</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                            {{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="location_code" value="Kode Lokasi/Instansi (Standar Aset Baris 1) *" />
                <x-text-input id="location_code" name="location_code" type="text" class="mt-1 block w-full"
                              :value="old('location_code', \App\Models\InstitutionSetting::current()->location_code)" required />
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

            <div>
                <x-input-label for="quantity" value="Jumlah *" />
                <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity', 1)" required />
            </div>

            <div>
                <x-input-label for="unit" value="Satuan *" />
                <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', 'unit')" placeholder="unit, pak, lembar..." required />
            </div>

            <div>
                <x-input-label for="photo" value="Foto Barang (opsional)" />
                <input id="photo" name="photo" type="file" accept="image/*"
                       class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="specification" value="Spesifikasi" />
                <textarea id="specification" name="specification" rows="3"
                          class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm">{{ old('specification') }}</textarea>
            </div>

            <label class="flex items-center gap-2 md:col-span-2">
                <input type="checkbox" name="is_high_value" value="1" @checked(old('is_high_value'))
                       class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                <span class="text-sm text-gray-700">Barang <strong>bernilai tinggi</strong> (laptop, kamera, dll) — wajib approval berjenjang Laboran → Wakasek + TTD digital</span>
            </label>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-primary-button class="bg-amber-600 hover:bg-amber-700 focus:ring-amber-500">Ajukan Pengadaan</x-primary-button>
            <a href="{{ route('items.index') }}" class="text-sm text-gray-500 hover:text-gray-800">Batal</a>
        </div>
    </form>
</x-app-layout>

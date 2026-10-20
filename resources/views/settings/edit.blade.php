<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Instansi</h2>
            <p class="text-sm text-gray-500">Logo &amp; identitas dipakai pada label stiker, BAST, dan laporan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data"
              class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label value="Nama Instansi / Sekolah *" />
                    <x-text-input name="institution_name" type="text" class="mt-1 block w-full" :value="old('institution_name', $setting->institution_name)" required />
                    <p class="text-[11px] text-gray-400 mt-1">Tampil sebagai footer label stiker & kop dokumen.</p>
                </div>

                <div>
                    <x-input-label value="Kode Lokasi/Instansi (Standar Aset Baris 1) *" />
                    <x-text-input name="location_code" type="text" class="mt-1 block w-full font-mono" :value="old('location_code', $setting->location_code)" required />
                    <p class="text-[11px] text-gray-400 mt-1">Contoh: 12.24.13.08.01.07.01.01 — menjadi default pengadaan barang.</p>
                </div>

                <div>
                    <x-input-label value="Alamat" />
                    <x-text-input name="address" type="text" class="mt-1 block w-full" :value="old('address', $setting->address)" />
                </div>

                <div>
                    <x-input-label value="Nama Kepala Sekolah (untuk dokumen)" />
                    <x-text-input name="headmaster_name" type="text" class="mt-1 block w-full" :value="old('headmaster_name', $setting->headmaster_name)" />
                </div>

                <div>
                    <x-input-label value="Logo Instansi (PNG transparan direkomendasikan)" />
                    <input type="file" name="logo" accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                </div>

                <x-primary-button class="bg-amber-600 hover:bg-amber-700 focus:ring-amber-500">Simpan Pengaturan</x-primary-button>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 text-sm mb-4">Pratinjau Label Stiker</h3>
            <div class="border-2 border-slate-800 rounded-lg p-4 max-w-md bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 flex items-center justify-center border border-gray-200 rounded bg-gray-50 overflow-hidden shrink-0">
                        @if ($setting->logo_path)
                            <img src="{{ asset('storage/'.$setting->logo_path) }}" alt="Logo" class="max-w-full max-h-full object-contain">
                        @else
                            <span class="material-icons-round text-3xl text-gray-400">image</span>
                        @endif
                    </div>
                    <div class="flex-1 leading-tight">
                        <div class="font-mono text-sm font-bold">{{ $setting->location_code }}</div>
                        <div class="font-mono text-xs font-bold mt-1">01.01.11.04.01.0001</div>
                    </div>
                    <div class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center text-[9px] text-gray-400 text-center">QR<br>CODE</div>
                </div>
                <div class="mt-3 border-t border-black pt-1 text-[10px] font-bold uppercase text-center">
                    {{ $setting->institution_name }}
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-3">
                QR berisi ID Internal barang (contoh: MEJA-G1-01-01) — dipakai untuk scan peminjaman & stock opname dari HP laboran.
            </p>
        </div>
    </div>
</x-app-layout>

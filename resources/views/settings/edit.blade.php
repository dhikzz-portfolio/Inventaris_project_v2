<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Pengaturan Sekolah</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Atur identitas yang tampil pada label dan dokumen sekolah.</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data"
              class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label value="Nama Instansi / Sekolah *" />
                    <x-text-input name="institution_name" type="text" class="mt-1 block w-full" :value="old('institution_name', $setting->institution_name)" required />
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Tampil pada bagian bawah label dan kepala dokumen.</p>
                </div>

                <div>
                    <x-input-label value="Kode Lokasi/Instansi (Standar Aset Baris 1) *" />
                    <x-text-input name="location_code" type="text" class="mt-1 block w-full font-mono" :value="old('location_code', $setting->location_code)" required />
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Contoh: 12.24.13.08.01.07.01.01. Kode ini akan menjadi isian awal saat menambah barang.</p>
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
                           class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:file:bg-blue-950/50 dark:file:text-blue-300" />
                </div>

                <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Simpan Pengaturan</x-primary-button>
            </div>
        </form>

        <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-slate-100">Pratinjau Label Stiker</h3>
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
            <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                QR berisi ID Internal barang (contoh: g1-01-01) — dipakai untuk scan peminjaman & stock opname dari HP laboran.
            </p>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Impor Laporan Unit</h2>
                <p class="text-sm text-gray-500">Pemulihan/migrasi data unit dari arsip CSV/XLSX — dengan pratinjau sebelum perubahan</p>
            </div>
            <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Laporan</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 self-start">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">upload_file</span> Unggah Berkas
            </h3>

            <form method="POST" action="{{ route('reports.import.preview') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <x-input-label value="Berkas CSV / XLSX *" />
                    <input type="file" name="file" accept=".csv,.txt,.xlsx" required
                           class="mt-1 block w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    <p class="mt-1 text-[11px] text-gray-400">Maksimal 2 MB. Baris pertama harus berisi header.</p>
                </div>

                <div>
                    <x-input-label value="Mode impor *" />
                    <label class="mt-1 flex items-start gap-2 rounded-lg border border-gray-200 p-2.5 text-xs">
                        <input type="radio" name="mode" value="update" checked class="mt-0.5 text-amber-600 focus:ring-amber-500">
                        <span><strong>Perbarui data yang cocok</strong><br><span class="text-gray-500">Hanya unit dengan kode inventaris yang sudah ada. Tidak membuat unit baru.</span></span>
                    </label>
                    <label class="mt-1 flex items-start gap-2 rounded-lg border border-gray-200 p-2.5 text-xs">
                        <input type="radio" name="mode" value="create" class="mt-0.5 text-amber-600 focus:ring-amber-500">
                        <span><strong>Pulihkan / tambah unit</strong><br><span class="text-gray-500">Membuat unit baru untuk kode yang belum ada. Katalog, ruangan, dan kode harus cocok dengan master data.</span></span>
                    </label>
                </div>

                <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold py-2.5">
                    Pratinjau Berkas
                </button>
            </form>

            <div class="mt-4 rounded-lg bg-blue-50 border border-blue-200 p-3 text-[11px] text-blue-900">
                <p class="font-semibold mb-1">Format header yang didukung</p>
                <p class="font-mono break-words">kode_inventaris, nama_barang, gedung, lantai, no_ruangan, kondisi, status, tahun, sumber_dana, no_register</p>
                <p class="mt-1.5">Kolom <strong>Kode Inventaris</strong>, <strong>Nama Barang</strong>, <strong>Gedung</strong>, <strong>Lantai</strong>, <strong>No. Ruangan</strong>, <strong>Kondisi</strong>, dan <strong>Status</strong> dari ekspor Excel juga dikenali.</p>
            </div>

            <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900">
                Impor ini <strong>bukan pengganti backup database</strong>. Untuk migrasi server, tetap lakukan backup database + file pendukung (lihat <span class="font-mono">docs/DEPLOYMENT.md</span>).
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            @if ($preview === null)
                <div class="py-16 text-center text-sm text-gray-400">
                    Belum ada berkas dipratinjau. Unggah CSV/XLSX untuk melihat ringkasan dan baris bermasalah.
                </div>
            @else
                <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Pratinjau: {{ $filename }}</h3>
                        <p class="text-xs text-gray-500">Mode: {{ $preview['mode'] === 'create' ? 'Pulihkan / tambah unit' : 'Perbarui data yang cocok' }}</p>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] font-bold">
                        <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ $preview['summary']['total'] }} baris</span>
                        <span class="px-2 py-1 rounded-full bg-green-100 text-green-800">{{ $preview['summary']['valid'] }} valid</span>
                        <span class="px-2 py-1 rounded-full bg-red-100 text-red-800">{{ $preview['summary']['invalid'] }} bermasalah</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 mb-3 text-xs text-gray-600">
                    <span>Akan dibuat: <strong>{{ $preview['summary']['create'] }}</strong></span>
                    <span>Akan diperbarui: <strong>{{ $preview['summary']['update'] }}</strong></span>
                </div>

                <div class="overflow-x-auto max-h-[420px] overflow-y-auto rounded-lg border border-gray-100">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 uppercase text-gray-500 sticky top-0">
                            <tr>
                                <th class="text-left px-3 py-2">Baris</th>
                                <th class="text-left px-3 py-2">Kode</th>
                                <th class="text-left px-3 py-2">Barang</th>
                                <th class="text-left px-3 py-2">Ruangan</th>
                                <th class="text-left px-3 py-2">Kondisi / Status</th>
                                <th class="text-left px-3 py-2">Aksi</th>
                                <th class="text-left px-3 py-2">Masalah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($preview['rows'] as $row)
                                <tr class="{{ $row['errors'] ? 'bg-red-50/50' : '' }}">
                                    <td class="px-3 py-2 text-gray-400">{{ $row['line'] }}</td>
                                    <td class="px-3 py-2 font-mono font-semibold">{{ $row['code'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $row['name'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['room_label'] }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['condition'] ?: '—' }} / {{ $row['status'] ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="font-bold {{ $row['action'] === 'BUAT' ? 'text-blue-700' : 'text-amber-700' }}">{{ $row['action'] }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-red-600">
                                        @foreach ($row['errors'] as $error)
                                            <div>• {{ $error }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center gap-3 flex-wrap border-t border-gray-100 pt-4">
                    <form method="POST" action="{{ route('reports.import.confirm') }}"
                          onsubmit="return confirm('Terapkan impor {{ $preview['summary']['valid'] }} baris? Perubahan dibatalkan seluruhnya bila ada kegagalan.')">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button @disabled(! $preview['can_import'])
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-bold">
                            <span class="material-icons-round text-lg">publish</span> Konfirmasi Impor
                        </button>
                    </form>
                    <p class="text-xs {{ $preview['can_import'] ? 'text-green-700' : 'text-red-600' }}">
                        {{ $preview['can_import'] ? 'Semua baris valid dan siap diimpor.' : 'Perbaiki seluruh baris bermasalah lalu unggah ulang.' }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">            <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Impor Data Unit</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tambahkan atau perbarui data unit dari file Excel/CSV — diperiksa dulu sebelum disimpan</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.import.template') }}"
                   class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <span class="material-icons-round text-lg" aria-hidden="true">download</span> Unduh Contoh File
                </a>
                <a href="{{ route('reports.index') }}"
                   class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg">arrow_back</span> Kembali ke Laporan
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif
    @unless ($hasRooms ?? true)
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <span class="material-icons-round text-base">warning</span>
            <span>Belum ada ruangan dengan identitas lengkap (gedung, lantai, no. ruangan). Tambahkan ruangan di <strong>Master Data</strong> sebelum menambah unit lewat impor.</span>
        </div>
    @endunless

    <div class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-start gap-2.5">
                <span class="material-icons-round shrink-0 text-blue-600">upload_file</span>
                <div>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">1. Pilih berkas</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Gunakan template XLSX (sheet Data Unit + Petunjuk) atau CSV yang datanya rapi per baris.</p>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="material-icons-round shrink-0 text-blue-600">fact_check</span>
                <div>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">2. Periksa hasil</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sistem menunjukkan baris yang aman dan yang perlu dibetulkan.</p>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <span class="material-icons-round shrink-0 text-blue-600">save</span>
                <div>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">3. Simpan</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tekan tombol impor. Tidak ada data yang dihapus.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="self-start rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">upload_file</span> Unggah Berkas
            </h3>

            <form method="POST" action="{{ route('reports.import.preview') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="File data *" />
                    <input type="file" name="file" accept=".csv,.txt,.xlsx" required
                           class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Format CSV atau XLSX, ukuran maksimal 2 MB. Baris pertama harus berisi nama kolom.</p>
                </div>

                <div>
                    <x-input-label for="mode-update" value="Pilih tindakan *" />
                    <div class="mt-1 space-y-2">
                        <label for="mode-update"
                               class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 p-3 text-xs transition hover:border-blue-300 hover:bg-blue-50/40 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/70 has-[:checked]:ring-2 has-[:checked]:ring-blue-200 dark:border-slate-700 dark:hover:border-blue-800 dark:hover:bg-blue-950/30 dark:has-[:checked]:border-blue-500 dark:has-[:checked]:bg-blue-950/40 dark:has-[:checked]:ring-blue-900">
                            <input id="mode-update" type="radio" name="mode" value="update" checked class="mt-0.5 h-4 w-4 border-slate-300 bg-white text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900">
                            <span>
                                <strong class="block text-sm text-slate-800 dark:text-slate-100">Perbarui data yang sudah ada</strong>
                                <span class="text-slate-500 dark:text-slate-400">Hanya mengubah unit yang kodenya sudah terdaftar. Tidak membuat unit baru.</span>
                            </span>
                        </label>
                        <label for="mode-create"
                               class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 p-3 text-xs transition hover:border-blue-300 hover:bg-blue-50/40 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/70 has-[:checked]:ring-2 has-[:checked]:ring-blue-200 dark:border-slate-700 dark:hover:border-blue-800 dark:hover:bg-blue-950/30 dark:has-[:checked]:border-blue-500 dark:has-[:checked]:bg-blue-950/40 dark:has-[:checked]:ring-blue-900">
                            <input id="mode-create" type="radio" name="mode" value="create" class="mt-0.5 h-4 w-4 border-slate-300 bg-white text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900">
                            <span>
                                <strong class="block text-sm text-slate-800 dark:text-slate-100">Tambahkan unit baru</strong>
                                <span class="text-slate-500 dark:text-slate-400">Dipakai untuk menambahkan banyak unit. Ruangan harus sudah ada; katalog baru dibuat otomatis bila belum terdaftar.</span>
                            </span>
                        </label>
                    </div>
                    <p class="mt-2 flex items-start gap-1.5 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <span class="material-icons-round text-sm">info</span>
                        <span>Pilihan tindakan dipakai saat menekan <strong>Periksa Berkas</strong>. Pada mode tambah, kosongkan kolom kode inventaris agar sistem membuat kodenya otomatis dari lokasi dan katalog.</span>
                    </p>
                </div>

                <button class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="material-icons-round text-lg">search</span> Periksa Berkas
                </button>
            </form>

            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/40">
                <summary class="cursor-pointer px-3 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200">Kolom apa saja yang dibutuhkan?</summary>
                <div class="space-y-2 border-t border-slate-200 px-3 py-3 text-[11px] text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    <p><strong class="text-slate-800 dark:text-slate-100">Wajib untuk semua baris:</strong> kondisi dan status.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Kode inventaris opsional saat menambah unit baru.</strong> Kosongkan sel agar sistem membuat kode otomatis; isi bila unit sudah memiliki kode. Pada mode perbarui, kode tetap wajib karena dipakai untuk mencocokkan unit.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Wajib bila menambah unit baru:</strong> nama barang, gedung, lantai, no. ruangan. Nama barang yang belum terdaftar akan dibuatkan katalog baru secara otomatis.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Opsional:</strong> tahun, sumber dana, no. register.</p>
                    <p class="text-amber-700 dark:text-amber-300"><strong>Baris contoh template memakai nama "(contoh)" dan akan ditolak.</strong> Ganti dengan data sebenarnya atau hapus barisnya. Daftar ruangan dan katalog yang tersedia ada di sheet <em>Petunjuk</em> pada file contoh.</p>
                    <p class="pt-1">Judul kolom dari ekspor Excel (Kode Inventaris, Nama Barang, Gedung, Lantai, No. Ruangan, Kondisi, Status) juga dikenali.</p>
                    <p class="break-words rounded-lg bg-white px-2 py-1.5 font-mono text-[10px] text-slate-500 dark:bg-slate-950 dark:text-slate-400">kode_inventaris, nama_barang, gedung, lantai, no_ruangan, kondisi, status, tahun, sumber_dana, no_register</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Format nilai:</strong> kondisi <em>Baik</em>, <em>Rusak Ringan</em>, atau <em>Rusak Berat</em>; status <em>Aktif</em>, <em>Sedang Dipinjam</em>, atau <em>Hilang</em>; tahun berupa angka 4 digit (contoh 2024); gedung berupa huruf kode gedung; lantai dan no. ruangan berupa angka.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Impor balik file ekspor:</strong> file Excel laporan bulanan dapat diimpor kembali. Sistem memakai sheet <em>Unit</em> (sheet pertama) dan mengabaikan sheet Peminjaman, Kerusakan, Mutasi, Penghapusan, Pengadaan, serta Laporan Kerusakan.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Relasi master data:</strong> ruangan harus cocok dengan gedung/lantai/no. ruangan. Nama barang dicocokkan ke katalog; bila belum ada, katalog baru dibuat otomatis. Kode otomatis mengikuti pola lokasi, misalnya <span class="font-mono">G1-02-012-01</span> untuk Gedung G, lantai 1, ruang 02, kode jenis barang 012, unit 01.</p>
                    <p><strong class="text-slate-800 dark:text-slate-100">Hasil validasi:</strong> setiap baris ditandai siap, perlu diperbaiki, atau ditolak; kode duplikat dan relasi yang tidak ditemukan diblokir. Impor hanya dijalankan bila seluruh baris valid, dalam satu transaksi — bila terjadi kegagalan seluruh perubahan dibatalkan.</p>
                </div>
            </details>

            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <p class="flex items-start gap-1.5">
                    <span class="material-icons-round text-sm">warning</span>
                    <span>Impor ini <strong>bukan pengganti backup database</strong>. Untuk pindah server, tetap cadangkan database dan file pendukungnya.</span>
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:col-span-2">
            @if ($preview === null)
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="material-icons-round text-5xl text-slate-300 dark:text-slate-600">fact_check</span>
                    <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Belum ada berkas yang diperiksa</p>
                    <p class="mt-1 max-w-sm text-xs text-slate-500 dark:text-slate-400">Unggah file CSV/XLSX di sebelah kiri. Setelah itu hasil pemeriksaan akan muncul di sini sebelum data disimpan.</p>
                </div>
            @else
                @php
                    $modeLabel = $preview['mode'] === 'create' ? 'Tambahkan unit baru' : 'Perbarui data yang sudah ada';
                    $actionLabels = ['BUAT' => 'Tambah', 'PERBARUI' => 'Perbarui', 'DITOLAK' => 'Ditolak'];
                    $actionStyles = [
                        'BUAT' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-200',
                        'PERBARUI' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
                        'DITOLAK' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-200',
                    ];
                    $readyRows = collect($preview['rows'])->filter(fn (array $analysisRow) => $analysisRow['errors'] === []);
                    $readyCreate = $readyRows->where('action', 'BUAT')->count();
                    $readyUpdate = $readyRows->where('action', 'PERBARUI')->count();
                @endphp

                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Hasil Pemeriksaan</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-mono">{{ $filename }}</span> • {{ $modeLabel }}
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <span class="material-icons-round text-sm">description</span> {{ $preview['summary']['total'] }} baris
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total baris</p>
                        <p class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $preview['summary']['total'] }}</p>
                    </div>
                    <div class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950/40">
                        <p class="text-xs font-semibold text-green-700 dark:text-green-300">Siap diimpor</p>
                        <p class="mt-1 text-2xl font-bold text-green-800 dark:text-green-200">{{ $preview['summary']['valid'] }}</p>
                    </div>
                    <div class="rounded-xl border p-3 {{ $preview['summary']['invalid'] > 0 ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40' : 'border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-800/40' }}">
                        <p class="text-xs font-semibold {{ $preview['summary']['invalid'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-slate-400' }}">Perlu diperbaiki</p>
                        <p class="mt-1 text-2xl font-bold {{ $preview['summary']['invalid'] > 0 ? 'text-red-800 dark:text-red-200' : 'text-slate-800 dark:text-slate-100' }}">{{ $preview['summary']['invalid'] }}</p>
                    </div>
                </div>

                @if ($readyCreate > 0 || $readyUpdate > 0)
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                        @if ($readyCreate > 0)
                            <span class="inline-flex items-center gap-1">
                                <span class="material-icons-round text-sm text-blue-600">add_circle</span>
                                {{ $readyCreate }} unit baru siap ditambahkan
                            </span>
                        @endif
                        @if ($readyUpdate > 0)
                            <span class="inline-flex items-center gap-1">
                                <span class="material-icons-round text-sm text-amber-600">sync</span>
                                {{ $readyUpdate }} unit siap diperbarui
                            </span>
                        @endif
                    </div>
                @endif

                <div role="{{ $preview['can_import'] ? 'status' : 'alert' }}"
                     class="mt-4 flex items-start gap-2 rounded-xl border p-3 text-xs {{ $preview['can_import'] ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }}">
                    <span class="material-icons-round text-base">{{ $preview['can_import'] ? 'check_circle' : 'error' }}</span>
                    <span>
                        @if ($preview['can_import'])
                            Semua baris sudah benar. Berkas siap diimpor.
                        @else
                            Masih ada {{ $preview['summary']['invalid'] }} baris yang perlu diperbaiki. Betulkan file lalu unggah ulang — perhatikan kolom <strong>Catatan</strong> di bawah.
                        @endif
                    </span>
                </div>

                <div class="mt-4 max-h-[420px] overflow-x-auto overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-slate-50 uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left">Baris</th>
                                <th class="px-3 py-2 text-left">Kode</th>
                                <th class="px-3 py-2 text-left">Barang</th>
                                <th class="px-3 py-2 text-left">Ruangan</th>
                                <th class="px-3 py-2 text-left">Kondisi</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Tindakan</th>
                                <th class="px-3 py-2 text-left">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($preview['rows'] as $row)
                                @php
                                    $isBlocked = $row['errors'] !== [];
                                    $badgeLabel = $isBlocked ? 'Tidak diproses' : ($actionLabels[$row['action']] ?? $row['action']);
                                    $badgeStyle = $isBlocked ? $actionStyles['DITOLAK'] : ($actionStyles[$row['action']] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300');
                                @endphp
                                <tr class="{{ $isBlocked ? 'bg-red-50/60 dark:bg-red-950/20' : 'bg-white dark:bg-slate-900' }}">
                                    <td class="px-3 py-2 text-slate-400 dark:text-slate-500">{{ $row['line'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $row['code'] ?: '—' }}
                                        @if ($row['auto_code'] ?? false)
                                            <span class="ms-1 rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-blue-700 dark:bg-blue-950/60 dark:text-blue-200">otomatis</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row['name'] ?: '—' }}
                                        @if ($row['auto_catalog'] ?? false)
                                            <span class="ms-1 rounded bg-green-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-green-700 dark:bg-green-950/60 dark:text-green-200">katalog baru</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['room_label'] }}</td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['condition'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['status'] ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-bold {{ $badgeStyle }}">{{ $badgeLabel }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @foreach ($row['errors'] as $error)
                                            <div class="text-red-600 dark:text-red-300">• {{ $error }}</div>
                                        @endforeach
                                        @foreach ($row['warnings'] ?? [] as $warning)
                                            <div class="text-amber-700 dark:text-amber-300">• {{ $warning }}</div>
                                        @endforeach
                                        @if ($row['errors'] === [] && ($row['warnings'] ?? []) === [])
                                            <span class="inline-flex items-center gap-1 font-semibold text-green-700 dark:text-green-300">
                                                <span class="material-icons-round text-sm">check</span> Siap
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <form method="POST" action="{{ route('reports.import.confirm') }}"
                          onsubmit="return confirm('Impor {{ $preview['summary']['valid'] }} baris sekarang? Seluruh perubahan dibatalkan otomatis bila ada yang gagal.')">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button @disabled(! $preview['can_import'])
                                class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-700">
                            <span class="material-icons-round text-lg">publish</span> Impor Sekarang
                        </button>
                    </form>
                    <p class="max-w-sm text-xs {{ $preview['can_import'] ? 'text-green-700 dark:text-green-300' : 'text-red-600 dark:text-red-300' }}">
                        {{ $preview['can_import'] ? 'Data yang tidak ada di berkas tidak akan dihapus.' : 'Tombol impor aktif setelah semua baris diperbaiki.' }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

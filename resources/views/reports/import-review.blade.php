<x-app-layout>
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

    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-xl font-bold leading-tight text-slate-900 dark:text-white">
                    <span class="material-icons-round text-blue-600" aria-hidden="true">fact_check</span> Pemeriksaan Berkas Impor
                </h2>
                <p class="mt-1 break-words text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $filename }}</span>
                    • {{ $modeLabel }} • {{ $preview['summary']['total'] }} baris
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.import.form') }}"
                   class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg" aria-hidden="true">upload</span> Unggah Berkas Lain
                </a>
                <a href="{{ route('reports.index') }}"
                   class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg" aria-hidden="true">arrow_back</span> Laporan
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

    <div class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-xl border border-sky-100 bg-white px-4 py-3 text-xs text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
        <span><strong class="text-slate-800 dark:text-slate-100">Total baris:</strong> {{ $preview['summary']['total'] }}</span>
        <span><strong class="text-green-700 dark:text-green-300">Siap diimpor:</strong> {{ $preview['summary']['valid'] }}</span>
        <span><strong class="{{ $preview['summary']['invalid'] > 0 ? 'text-red-600 dark:text-red-300' : 'text-slate-800 dark:text-slate-100' }}">Perlu diperbaiki:</strong> {{ $preview['summary']['invalid'] }}</span>
        <span class="inline-flex items-center gap-1">
            <span class="material-icons-round text-sm text-blue-600 dark:text-blue-300" aria-hidden="true">add_circle</span> {{ $readyCreate }} baru
        </span>
        <span class="inline-flex items-center gap-1">
            <span class="material-icons-round text-sm text-amber-600 dark:text-amber-300" aria-hidden="true">sync</span> {{ $readyUpdate }} diperbarui
        </span>
    </div>

    <div role="{{ $preview['can_import'] ? 'status' : 'alert' }}"
         class="mb-4 flex items-start gap-2 rounded-xl border px-4 py-2.5 text-xs {{ $preview['can_import'] ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }}">
        <span class="material-icons-round text-lg">{{ $preview['can_import'] ? 'check_circle' : 'error' }}</span>
        <span>
            @if ($preview['can_import'])
                Semua baris sudah benar. Berkas siap diimpor.
            @else
                Masih ada {{ $preview['summary']['invalid'] }} baris yang perlu diperbaiki. Betulkan file lalu unggah ulang — perhatikan kolom <strong>Catatan</strong> pada tabel di bawah.
            @endif
        </span>
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <div class="flex flex-wrap items-center gap-2">
                <p class="me-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Rincian Baris Berkas</p>
                <a href="{{ route('reports.import.review') }}"
                   @class([
                       'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition',
                       'bg-blue-600 text-white shadow-sm' => $filter === 'all',
                       'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $filter !== 'all',
                   ])>Semua
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px]',
                        'bg-white/20 text-white' => $filter === 'all',
                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300' => $filter !== 'all',
                    ])>{{ $preview['summary']['total'] }}</span>
                </a>
                <a href="{{ route('reports.import.review', ['filter' => 'problems']) }}"
                   @class([
                       'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition',
                       'bg-red-600 text-white shadow-sm' => $filter === 'problems',
                       'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $filter !== 'problems',
                   ])>Hanya bermasalah
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px]',
                        'bg-white/20 text-white' => $filter === 'problems',
                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300' => $filter !== 'problems',
                    ])>{{ $preview['summary']['invalid'] }}</span>
                </a>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Menampilkan {{ count($rows) }} dari {{ $preview['summary']['total'] }} baris
            </p>
        </div>

        <div class="max-h-[65vh] overflow-auto overscroll-contain">
            <table class="w-full min-w-[1240px] text-xs">
                <thead class="sticky top-0 z-10 bg-slate-50 uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left">Baris</th>
                        <th scope="col" class="px-3 py-2 text-left">Kode</th>
                        <th scope="col" class="px-3 py-2 text-left">Barang</th>
                        <th scope="col" class="px-3 py-2 text-left">Merek</th>
                        <th scope="col" class="px-3 py-2 text-left">Tipe</th>
                        <th scope="col" class="px-3 py-2 text-left">Kategori</th>
                        <th scope="col" class="px-3 py-2 text-left">Ruangan</th>
                        <th scope="col" class="px-3 py-2 text-left">Kondisi</th>
                        <th scope="col" class="px-3 py-2 text-left">Status</th>
                        <th scope="col" class="px-3 py-2 text-left">Tahun</th>
                        <th scope="col" class="px-3 py-2 text-left">Sumber Dana</th>
                        <th scope="col" class="px-3 py-2 text-left">Keterangan</th>
                        <th scope="col" class="px-3 py-2 text-left">Tindakan</th>
                        <th scope="col" class="px-3 py-2 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($rows as $row)
                        @php
                            $isBlocked = $row['errors'] !== [];
                            $badgeLabel = $isBlocked ? 'Tidak diproses' : ($actionLabels[$row['action']] ?? $row['action']);
                            $badgeStyle = $isBlocked ? $actionStyles['DITOLAK'] : ($actionStyles[$row['action']] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300');
                        @endphp
                        <tr class="{{ $isBlocked ? 'bg-red-50/60 hover:bg-red-50 dark:bg-red-950/20 dark:hover:bg-red-950/30' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50' }}">
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
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row['brand'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row['model_type'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row['category_name'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['room_label'] }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['condition'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['status'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['acquisition_year'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['funding_source'] ?: '—' }}</td>
                            <td class="max-w-[16rem] break-words px-3 py-2 text-slate-500 dark:text-slate-400">{{ $row['notes'] ?: '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-bold {{ $badgeStyle }}">{{ $badgeLabel }}</span>
                            </td>
                            <td class="px-3 py-2">
                                @if ($row['errors'] !== [])
                                    <ul class="max-w-[20rem] space-y-0.5 text-[11px] leading-snug text-red-600 dark:text-red-300">
                                        @foreach ($row['errors'] as $error)
                                            <li class="flex gap-1.5 break-words"><span aria-hidden="true">•</span><span>{{ $error }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (($row['warnings'] ?? []) !== [])
                                    <ul class="max-w-[20rem] space-y-0.5 text-[11px] leading-snug text-amber-700 dark:text-amber-300">
                                        @foreach ($row['warnings'] as $warning)
                                            <li class="flex gap-1.5 break-words"><span aria-hidden="true">•</span><span>{{ $warning }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($row['errors'] === [] && ($row['warnings'] ?? []) === [])
                                    <span class="inline-flex items-center gap-1 font-semibold text-green-700 dark:text-green-300">
                                        <span class="material-icons-round text-sm">check</span> Siap
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                Tidak ada baris bermasalah — semua baris siap diimpor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-4 dark:border-slate-800">
            <form method="POST" action="{{ route('reports.import.confirm') }}"
                  onsubmit="return confirm('Impor {{ $preview['summary']['valid'] }} baris sekarang? Seluruh perubahan dibatalkan otomatis bila ada yang gagal.')">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button @disabled(! $preview['can_import'])
                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-700">
                    <span class="material-icons-round text-lg">publish</span> Impor Sekarang
                </button>
            </form>
            <p class="max-w-md text-xs {{ $preview['can_import'] ? 'text-green-700 dark:text-green-300' : 'text-red-600 dark:text-red-300' }}">
                {{ $preview['can_import'] ? 'Data yang tidak ada di berkas tidak akan dihapus.' : 'Tombol impor aktif setelah semua baris diperbaiki.' }}
            </p>
        </div>
    </div>

    <p class="mt-3 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        Pratinjau ini belum mengubah data apa pun. Angka pada kolom <strong>Baris</strong> mengikuti urutan baris di berkas;
        baris yang bertanda merah tidak akan diproses sampai berkas diperbaiki dan diunggah ulang.
    </p>
</x-app-layout>

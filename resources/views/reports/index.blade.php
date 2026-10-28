<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Laporan Bulanan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ekspor PDF/Excel per bulan, difilter per kategori, ruangan, atau jurusan</p>
            </div>
            <a href="{{ route('reports.import.form') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                <span class="material-icons-round text-lg">upload_file</span> Impor Laporan
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900" x-data="reportForm()">
        <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
            <span class="material-icons-round text-blue-600">tune</span> Parameter Laporan
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-input-label for="report-period" value="Periode (Bulan)" />
                <input type="month" x-model="period" min="2020-01" value="{{ $currentPeriod }}"
                       id="report-period" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <x-input-label for="report-scope" value="Cakupan" />
                <select id="report-scope" x-model="scope" x-on:change="scopeId = ''" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="SEMUA">{{ auth()->user()->isSuperAdmin() ? 'Seluruh sekolah' : 'Seluruh laboratorium' }}</option>
                    <option value="KATEGORI">Per Kategori</option>
                    <option value="RUANGAN">Per Ruangan</option>
                    <option value="JURUSAN">Per Jurusan</option>
                </select>
            </div>
            <div x-show="scope !== 'SEMUA'" x-cloak>
                <label class="block font-medium text-sm text-gray-700" x-text="scope === 'KATEGORI' ? 'Kategori' : (scope === 'RUANGAN' ? 'Ruangan' : 'Jurusan')"></label>
                <select x-model="scopeId" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">— pilih —</option>
                    <template x-if="scope === 'KATEGORI'">
                        <optgroup label="Kategori">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                    <template x-if="scope === 'RUANGAN'">
                        <optgroup label="Ruangan">
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                    <template x-if="scope === 'JURUSAN'">
                        <optgroup label="Jurusan">
                            @foreach ($majors as $major)
                                <option value="{{ $major->id }}">{{ $major->name }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                </select>
            </div>
            <div class="flex items-end gap-2 flex-wrap">
                <a :href="exportUrl('pdf')" target="_blank"
                   class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-red-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <span class="material-icons-round text-lg">picture_as_pdf</span> Pratinjau PDF
                </a>
                <button type="button" @click="window.open(exportUrl('pdf'), '_blank').print()"
                   class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <span class="material-icons-round text-lg">print</span> Cetak
                </button>
                <a :href="exportUrl('excel')" target="_blank"
                   class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-green-600 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <span class="material-icons-round text-lg">table_view</span> Excel
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('reports.generate') }}" class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-end dark:border-slate-700">
            @csrf
            <input type="hidden" name="period" :value="period">
            <input type="hidden" name="scope" :value="scope">
            <input type="hidden" name="scope_id" :value="scope === 'SEMUA' ? '' : scopeId">
            <p class="flex-1 text-xs text-slate-500 dark:text-slate-400">Simpan snapshot laporan (PDF + Excel) ke arsip — laporan bulanan juga dibuat otomatis oleh scheduler setiap tanggal 1.</p>
            <button class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-auto sm:py-2.5">Simpan ke Arsip</button>
        </form>

        <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
            Ringkasan laporan: jumlah barang &amp; unit, kondisi (baik/rusak/hilang), pengajuan-pinjam, kerusakan, mutasi, penghapusan, terlambat.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-800 dark:border-slate-800 dark:text-slate-100">
            Arsip Laporan Tersimpan
            @unless (auth()->user()->isSuperAdmin())
                <span class="text-[11px] font-normal text-slate-500 dark:text-slate-400">— hanya laporan yang Anda buat; laporan otomatis seluruh sekolah hanya dapat diunduh Super Admin.</span>
            @endunless
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="text-left px-5 py-2.5">Periode</th>
                        <th class="text-left px-5 py-2.5">Cakupan</th>
                        <th class="text-left px-5 py-2.5">Dibuat Oleh</th>
                        <th class="text-left px-5 py-2.5">Waktu Generate</th>
                        <th class="text-center px-5 py-2.5">Unduh</th>
                        <th class="text-center px-5 py-2.5">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($reports as $report)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-5 py-2.5 font-medium text-slate-800 dark:text-slate-100">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $report->period)->translatedFormat('F Y') }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $report->scope }}{{ $report->scope_id ? ' #'.$report->scope_id : '' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $report->generator?->name ?: 'Scheduler' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $report->generated_at->format('d M Y H:i') }}</td>
                            <td class="px-5 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($report->pdf_path)
                                        <a href="{{ route('files.show', ['path' => $report->pdf_path]) }}" target="_blank" class="text-red-600 hover:underline text-xs font-semibold">PDF</a>
                                    @endif
                                    @if ($report->excel_path)
                                        <a href="{{ route('files.show', ['path' => $report->excel_path]) }}" download class="text-green-700 hover:underline text-xs font-semibold">Excel</a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-center">
                                @if (auth()->user()->isSuperAdmin() || (int) $report->generated_by === (int) auth()->id())
                                    <form method="POST" action="{{ route('reports.destroy', $report) }}"
                                          onsubmit="return confirm('Hapus arsip laporan periode {{ $report->period }} ({{ $report->scope }})?\n\nFile PDF/Excel dihapus dan arsip disembunyikan. Data inventaris sumber TIDAK ikut terhapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:underline">Hapus</button>
                                    </form>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400 text-sm">Belum ada laporan tersimpan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">{{ $reports->links() }}</div>
    </div>

    @push('scripts')
    <script>
        function reportForm() {
            return {
                period: '{{ $currentPeriod }}',
                scope: 'SEMUA',
                scopeId: '',
                exportUrl(type) {
                    const params = new URLSearchParams({ scope: this.scope });
                    if (this.scope !== 'SEMUA' && this.scopeId) params.set('scope_id', this.scopeId);
                    return `{{ url('laporan/export') }}/` + type + '/' + this.period + '?' + params.toString();
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

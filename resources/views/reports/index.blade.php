<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Bulanan</h2>
            <p class="text-sm text-gray-500">Ekspor PDF/Excel per bulan, difilter per kategori, ruangan, atau jurusan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-6" x-data="reportForm()">
        <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
            <span class="material-icons-round text-amber-600">tune</span> Parameter Laporan
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-input-label value="Periode (Bulan)" />
                <input type="month" x-model="period" min="2020-01" value="{{ $currentPeriod }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
            </div>
            <div>
                <x-input-label value="Cakupan" />
                <select x-model="scope" x-on:change="scopeId = ''" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="SEMUA">Seluruh sekolah</option>
                    <option value="KATEGORI">Per Kategori</option>
                    <option value="RUANGAN">Per Ruangan</option>
                    <option value="JURUSAN">Per Jurusan</option>
                </select>
            </div>
            <div x-show="scope !== 'SEMUA'" x-cloak>
                <label class="block font-medium text-sm text-gray-700" x-text="scope === 'KATEGORI' ? 'Kategori' : (scope === 'RUANGAN' ? 'Ruangan' : 'Jurusan')"></label>
                <select x-model="scopeId" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
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
            <div class="flex items-end gap-2">
                <a :href="exportUrl('pdf')" target="_blank"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                    <span class="material-icons-round text-lg">picture_as_pdf</span> PDF
                </a>
                <a :href="exportUrl('excel')" target="_blank"
                   class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-bold">
                    <span class="material-icons-round text-lg">table_view</span> Excel
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('reports.generate') }}" class="mt-4 flex flex-col sm:flex-row sm:items-end gap-3 border-t border-gray-100 pt-4">
            @csrf
            <input type="hidden" name="period" :value="period">
            <input type="hidden" name="scope" :value="scope">
            <input type="hidden" name="scope_id" :value="scope === 'SEMUA' ? '' : scopeId">
            <p class="text-xs text-gray-500 flex-1">Simpan snapshot laporan (PDF + Excel) ke arsip — laporan bulanan juga dibuat otomatis oleh scheduler setiap tanggal 1.</p>
            <button class="w-full sm:w-auto px-4 py-3 sm:py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Simpan ke Arsip</button>
        </form>

        <p class="text-[11px] text-gray-400 mt-3">
            Ringkasan laporan: jumlah barang &amp; unit, kondisi (baik/rusak/hilang), pengajuan-pinjam, kerusakan, mutasi, penghapusan, terlambat.
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">Arsip Laporan Tersimpan</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-2.5">Periode</th>
                        <th class="text-left px-5 py-2.5">Cakupan</th>
                        <th class="text-left px-5 py-2.5">Dibuat Oleh</th>
                        <th class="text-left px-5 py-2.5">Waktu Generate</th>
                        <th class="text-center px-5 py-2.5">Unduh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $report->period)->translatedFormat('F Y') }}</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $report->scope }}{{ $report->scope_id ? ' #'.$report->scope_id : '' }}</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $report->generator?->name ?: 'Scheduler' }}</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $report->generated_at->format('d M Y H:i') }}</td>
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
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400 text-sm">Belum ada laporan tersimpan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $reports->links() }}</div>
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

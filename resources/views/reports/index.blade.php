<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Laporan Bulanan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ekspor PDF/Excel per bulan, difilter per kategori, ruangan, atau jurusan</p>
            </div>
            @if (auth()->user()->managesInventory())
                <a href="{{ route('reports.import.form') }}"
                   class="inline-flex min-h-10 items-center gap-1.5 self-start rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 sm:self-auto">
                    <span class="material-icons-round text-lg" aria-hidden="true">upload</span> Impor Laporan
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" x-data="reportForm()">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-5">
            <span class="material-icons-round text-lg text-brand-600" aria-hidden="true">tune</span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Parameter laporan</h3>
        </div>

        <div class="grid grid-cols-1 gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5 lg:grid-cols-3">
            <div>
                <x-input-label for="report-period" value="Periode (bulan)" />
                <input type="month" x-model="period" min="2020-01" value="{{ $currentPeriod }}"
                       id="report-period" class="mt-1 block min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <x-input-label for="report-scope" value="Cakupan" />
                <select id="report-scope" x-model="scope" x-on:change="scopeId = ''" class="mt-1 block min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <option value="SEMUA">{{ auth()->user()->isSuperAdmin() ? 'Seluruh sekolah' : 'Seluruh laboratorium' }}</option>
                    <option value="KATEGORI">Per kategori</option>
                    <option value="RUANGAN">Per ruangan</option>
                    <option value="JURUSAN">Per jurusan</option>
                </select>
            </div>
            <div>
                <x-input-label for="report-condition" value="Kondisi unit" />
                <select id="report-condition" x-model="condition" class="mt-1 block min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua kondisi</option>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="scope !== 'SEMUA'" x-cloak>
                <label for="report-scope-id" class="block text-sm font-medium text-slate-700 dark:text-slate-300" x-text="scope === 'KATEGORI' ? 'Kategori' : (scope === 'RUANGAN' ? 'Ruangan' : 'Jurusan')"></label>
                <select id="report-scope-id" x-model="scopeId" class="mt-1 block min-h-10 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
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
        </div>

        <div class="border-t border-slate-100 bg-slate-50/70 px-4 py-4 dark:border-slate-800 dark:bg-slate-800/30 sm:px-5">
            <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                <span class="material-icons-round mt-0.5 shrink-0 text-sm text-slate-400" aria-hidden="true">info</span>
                <p>Laporan memuat jumlah barang dan unit, kondisi, peminjaman, kerusakan, mutasi, penghapusan, pengadaan, serta keterlambatan sesuai cakupan pengguna. Laporan bulanan juga dibuat otomatis setiap tanggal 1.</p>
            </div>
            <div class="mt-4 flex flex-col gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-700 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <a :href="exportUrl('pdf')" target="_blank" rel="noopener"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base text-red-600" aria-hidden="true">picture_as_pdf</span> Pratinjau PDF
                    </a>
                    <button type="button" @click="printReport()"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base text-slate-500" aria-hidden="true">print</span> Cetak
                    </button>
                    <a :href="previewExcelUrl()" target="_blank" rel="noopener"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base text-emerald-700" aria-hidden="true">grid_on</span> Pratinjau Excel
                    </a>
                    <a :href="exportUrl('excel')" target="_blank" rel="noopener"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base text-green-600" aria-hidden="true">table_view</span> Excel
                    </a>
                </div>
                @if (auth()->user()->managesInventory())
                    <form method="POST" action="{{ route('reports.generate') }}" x-data="{ submitting: false }" @submit="submitting = true" class="sm:ms-auto">
                        @csrf
                        <input type="hidden" name="period" :value="period">
                        <input type="hidden" name="scope" :value="scope">
                        <input type="hidden" name="scope_id" :value="scope === 'SEMUA' ? '' : scopeId">
                        <input type="hidden" name="condition" :value="condition">
                        <button :disabled="submitting" class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus:ring-offset-slate-900 sm:w-auto">
                            <span class="material-icons-round text-lg" aria-hidden="true">archive</span>
                            <span x-text="submitting ? 'Menyimpan...' : 'Simpan ke arsip'"></span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-1 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">Arsip laporan tersimpan</p>
            @unless (auth()->user()->isSuperAdmin())
                <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">Hanya laporan yang Anda buat; laporan otomatis seluruh sekolah hanya dapat diunduh Super Admin.</p>
            @endunless
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 text-left">Periode</th>
                        <th class="px-5 py-3 text-left">Cakupan</th>
                        <th class="px-5 py-3 text-left">Dibuat oleh</th>
                        <th class="px-5 py-3 text-left">Waktu generate</th>
                        <th class="px-5 py-3 text-center">Unduh</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($reports as $report)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="whitespace-nowrap px-5 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $report->period)->translatedFormat('F Y') }}</td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold uppercase text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $report->scope }}{{ $report->scope_id ? ' #'.$report->scope_id : '' }}</span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $report->generator?->name ?: 'Scheduler' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $report->generated_at->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($report->pdf_path)
                                        <a href="{{ route('files.show', ['path' => $report->pdf_path]) }}" target="_blank" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">PDF</a>
                                    @endif
                                    @if ($report->excel_path)
                                        <a href="{{ route('files.show', ['path' => $report->excel_path]) }}" download class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Excel</a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if (auth()->user()->isSuperAdmin() || (int) $report->generated_by === (int) auth()->id())
                                    <form method="POST" action="{{ route('reports.destroy', $report) }}" x-data="{ submitting: false }"
                                          @submit="submitting = true"
                                          onsubmit="return confirm('Hapus arsip laporan periode {{ $report->period }} ({{ $report->scope }})?\n\nFile PDF/Excel dihapus dan arsip disembunyikan. Data inventaris sumber TIDAK ikut terhapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <button :disabled="submitting"
                                                class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-wait disabled:opacity-60 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">
                                            <span class="material-icons-round text-sm" aria-hidden="true">delete</span>
                                            <span x-text="submitting ? 'Menghapus...' : 'Hapus'"></span>
                                        </button>
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
        <div class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
            @forelse ($reports as $report)
                <article class="space-y-3 p-4 transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $report->period)->translatedFormat('F Y') }}</p>
                            <span class="mt-1 inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold uppercase text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $report->scope }}{{ $report->scope_id ? ' #'.$report->scope_id : '' }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($report->pdf_path)
                                <a href="{{ route('files.show', ['path' => $report->pdf_path]) }}" target="_blank"
                                   class="inline-flex min-h-8 items-center rounded-lg bg-red-50 px-2.5 text-[11px] font-bold text-red-700 transition hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">PDF</a>
                            @endif
                            @if ($report->excel_path)
                                <a href="{{ route('files.show', ['path' => $report->excel_path]) }}" download
                                   class="inline-flex min-h-8 items-center rounded-lg bg-green-50 px-2.5 text-[11px] font-bold text-green-700 transition hover:bg-green-100 dark:bg-green-950/40 dark:text-green-300 dark:hover:bg-green-950/60">Excel</a>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <p>{{ $report->generator?->name ?: 'Scheduler' }} • {{ $report->generated_at->format('d M Y H:i') }}</p>
                        @if (auth()->user()->isSuperAdmin() || (int) $report->generated_by === (int) auth()->id())
                            <form method="POST" action="{{ route('reports.destroy', $report) }}" x-data="{ submitting: false }"
                                  @submit="submitting = true"
                                  onsubmit="return confirm('Hapus arsip laporan periode {{ $report->period }} ({{ $report->scope }})?\n\nFile PDF/Excel dihapus dan arsip disembunyikan. Data inventaris sumber TIDAK ikut terhapus.')">
                                @csrf
                                @method('DELETE')
                                <button :disabled="submitting"
                                        class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-wait disabled:opacity-60 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">
                                    <span class="material-icons-round text-sm" aria-hidden="true">delete</span>
                                    <span x-text="submitting ? 'Menghapus...' : 'Hapus'"></span>
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">Belum ada laporan tersimpan.</p>
            @endforelse
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
                condition: '',
                exportUrl(type) {
                    const params = this.filterParams();
                    return `{{ url('laporan/export') }}/` + type + '/' + this.period + '?' + params.toString();
                },
                previewExcelUrl() {
                    return `{{ url('laporan/pratinjau-excel') }}/` + this.period + '?' + this.filterParams().toString();
                },
                filterParams() {
                    const params = new URLSearchParams({ scope: this.scope });
                    if (this.scope !== 'SEMUA' && this.scopeId) params.set('scope_id', this.scopeId);
                    if (this.condition) params.set('condition', this.condition);
                    return params;
                },
                printReport() {
                    const win = window.open(this.exportUrl('pdf'), '_blank', 'noopener');

                    if (!win) {
                        alert('Izinkan pop-up untuk mencetak laporan.');
                        return;
                    }

                    let printed = false;
                    const trigger = () => {
                        if (printed) return;
                        printed = true;
                        try { win.focus(); win.print(); } catch (error) { /* pengguna dapat mencetak manual */ }
                    };

                    win.addEventListener('load', trigger, { once: true });
                    setTimeout(trigger, 1500);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Detail Laporan Kerusakan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Riwayat penanganan tidak dapat dihapus dari antarmuka biasa.</p>
            </div>
            <a href="{{ route('damage-reports.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $report->classification->badgeClass() }}">{{ $report->classification->label() }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $report->status->badgeClass() }}">{{ $report->status->label() }}</span>
                </div>
                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-slate-100">{{ $report->unit?->catalog?->name }}</h3>
                <div class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $report->unit?->inventory_code }}</div>

                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Lokasi</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->unit?->room?->code }} — {{ $report->unit?->room?->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Spesifikasi</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->unit?->specification?->displayName() ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kondisi sebelum laporan</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->previous_condition->label() }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kondisi saat dilaporkan</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->reported_condition->label() }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Pelapor</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->reporter?->name }} • {{ $report->created_at->format('d M Y H:i') }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Penangan terakhir</dt><dd class="text-slate-800 dark:text-slate-100">{{ $report->handler?->name ?? '—' }} {{ $report->handled_at ? '• '.$report->handled_at->format('d M Y H:i') : '' }}</dd></div>
                </dl>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                    {{ $report->description }}
                </div>

                @if ($report->photos->isNotEmpty() || $report->photo_path)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($report->photos as $photo)
                            <a href="{{ route('files.show', ['path' => $photo->path]) }}" target="_blank" rel="noopener" title="Buka foto bukti">
                                <img src="{{ route('files.show', ['path' => $photo->path]) }}"
                                     alt="Dokumentasi kerusakan {{ $report->unit?->inventory_code }} tanggal {{ $report->created_at?->format('d/m/Y') }}"
                                     class="h-24 w-24 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                            </a>
                        @endforeach
                        @if ($report->photos->isEmpty())
                            <a href="{{ route('files.show', ['path' => $report->photo_path]) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">
                                <span class="material-icons-round text-base">photo</span> Lihat dokumentasi foto
                            </a>
                        @endif
                    </div>
                @endif

                @if ($report->resolution_note)
                    <div class="mt-3 text-xs text-slate-600 dark:text-slate-300"><strong>Catatan penanganan:</strong> {{ $report->resolution_note }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-100">Riwayat Penanganan</h3>
                <ol class="space-y-3">
                    @foreach ($logs as $log)
                        <li class="border-l-2 border-blue-200 pl-3 dark:border-blue-900">
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $log->action }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $log->user?->name ?? 'Sistem' }} • {{ $log->created_at->format('d M Y H:i') }}
                                @if ($log->status_after) • status: {{ $log->status_after }} @endif
                            </div>
                            @if ($log->note)
                                <div class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ $log->note }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Perbarui Status Penanganan</h3>
                @if ($canUpdate && count($statuses) > 0)
                    <form method="POST" action="{{ route('damage-reports.update', $report) }}" class="mt-3 space-y-3" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label for="status" value="Status baru" />
                            <select id="status" name="status" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="note" value="Catatan penanganan" />
                            <textarea id="note" name="note" rows="3" maxlength="1000"
                                      class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"></textarea>
                        </div>
                        <button :disabled="submitting" class="inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-lg bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <span x-text="submitting ? 'Menyimpan…' : 'Simpan status'"></span>
                        </button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ count($statuses) === 0 ? 'Laporan sudah selesai ditangani.' : 'Anda tidak memiliki kewenangan memperbarui laporan ini.' }}
                    </p>
                @endif
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                <strong>Catatan kebijakan:</strong> persetujuan perbaikan rusak ringan dan konfirmasi Ketua Laboran untuk
                rusak berat masih menunggu keputusan (docs/PENDING_DECISIONS.md). Fitur ini hanya mencatat dan memantau.
            </div>
        </div>
    </div>
</x-app-layout>

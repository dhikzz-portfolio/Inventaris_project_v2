<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <nav aria-label="Breadcrumb" class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ route('catalogs.index') }}" class="font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Katalog</a>
                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
                    <a href="{{ route('catalogs.show', $unit->catalog) }}" class="max-w-[14rem] truncate font-medium text-slate-500 transition hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">{{ $unit->catalog?->name }}</a>
                </nav>
                <h2 class="truncate text-xl font-bold leading-tight text-slate-900 dark:text-white">{{ $unit->catalog?->name ?: 'Detail Unit' }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $unit->inventory_code }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $unit->locationLabel() }}</span>
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <span class="inline-flex min-h-8 items-center rounded-full px-3 text-[11px] font-bold {{ $unit->condition->isDamaged() ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' }}">
                    {{ $unit->condition->label() }}
                </span>
                <span class="inline-flex min-h-8 items-center rounded-full px-3 text-[11px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                @if (auth()->user()->isAdmin() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS)
                    <a href="{{ route('units.edit', $unit) }}"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-brand-700 dark:hover:bg-slate-800 dark:hover:text-brand-300">
                        <span class="material-icons-round text-base" aria-hidden="true">edit</span> Edit Data
                    </a>
                @endif
                @if (auth()->user()->canApproveLoans())
                    <a href="{{ route('units.label', $unit) }}" target="_blank" rel="noopener"
                       class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-base" aria-hidden="true">print</span> Cetak Label
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span><span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span><span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">badge</span>
                    </span>
                    {{ auth()->user()->isMember() ? 'Informasi Unit' : 'Identitas & Perolehan' }}
                </h3>
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kode inventaris</dt><dd class="mt-0.5 break-all font-mono text-sm font-bold text-slate-900 dark:text-slate-100">{{ $unit->inventory_code }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Katalog</dt><dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">{{ $unit->catalog?->name ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Merek</dt><dd class="mt-0.5 break-words font-semibold text-slate-800 dark:text-slate-100">{{ $unit->specification?->brandLabel() ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tipe / model</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->specification?->modelLabel() ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kategori</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->catalog?->category?->name ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Gedung / Lantai / Ruangan</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->locationLabel() }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jurusan</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->room?->major?->name ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nomor register</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->register_no ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tanggal perolehan</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-slate-100">{{ $unit->acquisition_date?->format('d/m/Y') ?: '—' }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tahun perolehan</dt><dd class="mt-0.5 font-medium text-slate-800 dark:text-slate-100">{{ $unit->acquisition_year ?: '—' }}</dd></div>
                    @unless (auth()->user()->isMember())
                        <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nilai perolehan</dt><dd class="mt-0.5 font-semibold text-slate-800 dark:text-slate-100">{{ $unit->acquisition_value ? 'Rp '.number_format((float) $unit->acquisition_value, 0, ',', '.') : '—' }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Sumber dana / perolehan</dt><dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->funding_source ?: '—' }}{{ $unit->acquisition_source ? ' • '.$unit->acquisition_source : '' }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-2.5 sm:col-span-2 dark:bg-slate-800/60"><dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Catatan</dt><dd class="mt-0.5 whitespace-pre-line break-words font-medium text-slate-800 dark:text-slate-100">{{ $unit->notes ?: '—' }}</dd></div>
                    @endunless
                </dl>
            </section>

            @if ($canViewDamageEvidence)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                                <span class="material-icons-round" aria-hidden="true">photo_camera</span>
                            </span>
                            Riwayat Kerusakan dan Dokumentasi
                        </h3>
                        <a href="{{ route('damage-reports.create', ['unit' => $unit->id]) }}"
                           class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-950/60">
                            <span class="material-icons-round text-base" aria-hidden="true">add_a_photo</span> Buat laporan kerusakan
                        </a>
                    </div>

                    @if ($unit->damageReports->isEmpty() && $returnDamageRows->isEmpty())
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                            <span class="material-icons-round text-xl text-slate-400 dark:text-slate-500" aria-hidden="true">photo_library</span>
                            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada dokumentasi kerusakan</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Laporan dan foto bukti untuk unit ini akan tampil di sini.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($unit->damageReports as $report)
                                <article class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $report->classification->badgeClass() }}">{{ $report->classification->label() }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $report->status->badgeClass() }}">{{ $report->status->label() }}</span>
                                        <span class="ms-auto text-[11px] text-slate-500 dark:text-slate-400">{{ $report->created_at?->format('d M Y H:i') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ $report->description }}</p>
                                    <div class="mt-2 grid gap-1 text-[11px] text-slate-500 dark:text-slate-400 sm:grid-cols-2">
                                        <p>Pelapor: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $report->reporter?->name ?? '—' }}</span></p>
                                        <p>Penangan: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $report->handler?->name ?? 'Belum ditangani' }}</span>{{ $report->handled_at ? ' • '.$report->handled_at->format('d M Y H:i') : '' }}</p>
                                    </div>
                                    @if ($report->resolution_note)
                                        <p class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] text-slate-600 dark:bg-slate-800/70 dark:text-slate-300">
                                            <strong>Tindak lanjut:</strong> {{ $report->resolution_note }}
                                        </p>
                                    @endif
                                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ $report->isHeavy() ? 'Rekomendasi: ajukan perbaikan atau usulan pemutihan sebelum penghapusan.' : 'Rekomendasi: ajukan perbaikan melalui alur tindak lanjut kondisi barang.' }}
                                    </p>
                                    @if ($report->photos->isNotEmpty() || $report->photo_path)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            @foreach ($report->photos as $photo)
                                                <a href="{{ route('files.show', ['path' => $photo->path]) }}" target="_blank" rel="noopener" title="Buka foto bukti kerusakan">
                                                    <img src="{{ route('files.show', ['path' => $photo->path]) }}"
                                                         alt="Foto bukti kerusakan {{ $report->unit?->inventory_code }} tanggal {{ $report->created_at?->format('d/m/Y') }}"
                                                         class="h-20 w-20 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                                </a>
                                            @endforeach
                                            @if ($report->photos->isEmpty())
                                                <a href="{{ route('files.show', ['path' => $report->photo_path]) }}" target="_blank" rel="noopener" title="Buka foto bukti kerusakan">
                                                    <img src="{{ route('files.show', ['path' => $report->photo_path]) }}"
                                                         alt="Foto bukti kerusakan {{ $report->unit?->inventory_code }} tanggal {{ $report->created_at?->format('d/m/Y') }}"
                                                         class="h-20 w-20 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </article>
                            @endforeach

                            @foreach ($returnDamageRows as $row)
                                <article class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Bukti pengembalian</span>
                                        @if ($row->condition_in)
                                            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-300">{{ $row->condition_in->label() }}</span>
                                        @endif
                                        <span class="ms-auto text-[11px] text-slate-500 dark:text-slate-400">{{ ($row->returned_at ?? $row->confirmed_at ?? $row->assigned_at)?->format('d M Y H:i') }}</span>
                                    </div>
                                    <div class="mt-2 grid gap-1 text-[11px] text-slate-500 dark:text-slate-400 sm:grid-cols-2">
                                        <p>Transaksi: <span class="font-medium text-slate-700 dark:text-slate-200">#{{ $row->loanItem?->loan?->id }}</span></p>
                                        <p>Peminjam: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $row->loanItem?->loan?->borrowerDisplayName() }}</span></p>
                                        <p>Petugas validasi: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $row->confirmer?->name ?? '—' }}</span></p>
                                    </div>
                                    @if ($row->return_notes)
                                        <p class="mt-2 text-[11px] text-slate-600 dark:text-slate-300">{{ $row->return_notes }}</p>
                                    @endif
                                    @if ($row->damage_photo_path)
                                        <div class="mt-2 flex items-center gap-2">
                                            <a href="{{ route('files.show', ['path' => $row->damage_photo_path]) }}" target="_blank" rel="noopener">
                                                <img src="{{ route('files.show', ['path' => $row->damage_photo_path]) }}"
                                                     alt="Foto kerusakan unit {{ $unit->inventory_code }} dari pengembalian peminjaman #{{ $row->loanItem?->loan?->id }}"
                                                     class="h-20 w-20 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                            </a>
                                            <a href="{{ route('files.show', ['path' => $row->damage_photo_path]) }}" target="_blank" rel="noopener"
                                               class="inline-flex min-h-7 items-center justify-center rounded-md bg-brand-600 px-2 py-0.5 text-[9px] font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1 dark:focus:ring-offset-slate-900">Buka foto bukti</a>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @unless (auth()->user()->isMember())
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">history</span>
                    </span>
                    Riwayat Status Operasional
                </h3>
                @if ($unit->statusLogs->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada perubahan status</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Perubahan manual dan otomatis akan tercatat di bagian ini.</p>
                    </div>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($unit->statusLogs as $log)
                            <li class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                                <div class="flex flex-wrap items-center gap-2 text-slate-700 dark:text-slate-200">
                                    <span class="text-[11px] font-semibold">{{ $log->from_status?->label() ?? '—' }}</span>
                                    <span class="material-icons-round text-sm text-slate-400" aria-hidden="true">arrow_forward</span>
                                    <span class="text-[11px] font-bold text-brand-700 dark:text-brand-300">{{ $log->to_status->label() }}</span>
                                    <span class="ms-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $log->source }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="mt-1.5 text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                    oleh {{ $log->actor?->name ?? 'Sistem' }}@if ($log->reason) — {{ $log->reason }} @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">edit_note</span>
                    </span>
                    Riwayat Edit Data
                </h3>
                @if ($editHistory->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada riwayat edit</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Koreksi data dan perubahan kondisi akan tercatat di bagian ini.</p>
                    </div>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($editHistory as $entry)
                            <li class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="font-semibold leading-5 text-slate-800 dark:text-slate-100">{{ $entry['description'] }}</p>
                                    <span class="shrink-0 text-[11px] text-slate-500 dark:text-slate-400">{{ $entry['created_at'] }}</span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">oleh {{ $entry['actor'] }}</p>

                                @if ($entry['changes'] === [])
                                    <p class="mt-2 text-[11px] italic text-slate-500 dark:text-slate-400">Tidak ada perubahan nilai yang tercatat.</p>
                                @else
                                    <dl class="mt-2 space-y-1">
                                        @foreach ($entry['changes'] as $change)
                                            <div class="rounded-lg bg-slate-50 px-2.5 py-1.5 dark:bg-slate-800/70">
                                                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $change['label'] }}</dt>
                                                <dd class="mt-0.5 flex flex-wrap items-center gap-1.5 break-words text-[11px] leading-5 text-slate-600 dark:text-slate-300">
                                                    <span class="text-slate-500 line-through decoration-slate-400/70 dark:text-slate-400">{{ $change['before'] }}</span>
                                                    <span class="text-slate-400" aria-hidden="true">→</span>
                                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $change['after'] }}</span>
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Maksimal 50 perubahan terakhir ditampilkan di sini.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">swap_horiz</span>
                    </span>
                    Riwayat Perpindahan
                </h3>
                @if ($unit->movements->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada riwayat perpindahan</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Perubahan lokasi unit akan tercatat di bagian ini.</p>
                    </div>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($unit->movements as $movement)
                            <li class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                                <div class="flex flex-wrap items-center gap-2 text-slate-700 dark:text-slate-200">
                                    <span class="rounded-md bg-slate-100 px-2 py-1 font-mono font-semibold dark:bg-slate-800">{{ $movement->old_code }}</span>
                                    <span class="material-icons-round text-sm text-slate-400" aria-hidden="true">arrow_forward</span>
                                    <span class="rounded-md bg-brand-50 px-2 py-1 font-mono font-semibold text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">{{ $movement->new_code }}</span>
                                    <span class="ms-auto text-[11px] text-slate-500 dark:text-slate-400">{{ $movement->moved_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="mt-1.5 text-[11px] leading-5 text-slate-500 dark:text-slate-400">
                                    {{ $movement->fromRoom?->name }} → {{ $movement->toRoom?->name }}
                                    • oleh {{ $movement->mover?->name }}
                                    @if ($movement->note) — {{ $movement->note }} @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endunless

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">build</span>
                    </span>
                    Riwayat Pemeliharaan
                </h3>
                @if ($unit->maintenanceRecords->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada catatan pemeliharaan</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Riwayat perawatan unit akan ditampilkan di sini.</p>
                    </div>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach ($unit->maintenanceRecords as $record)
                            <li class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $record->type }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ $record->performed_at?->format('d/m/Y') }}</span>
                                </div>
                                <p class="mt-1.5 leading-5 text-slate-600 dark:text-slate-300">{{ $record->description }}</p>
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Pelaksana: {{ $record->performed_by ?: '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="space-y-4">
            @unless (auth()->user()->isMember())
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                        <span class="material-icons-round" aria-hidden="true">history</span>
                    </span>
                    Riwayat Kode
                </h3>
                @if ($unit->codes->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-center dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada riwayat kode</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kode aktif unit akan ditampilkan di sini.</p>
                    </div>
                @else
                    <ul class="space-y-2">
                        @foreach ($unit->codes as $code)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                                <span class="min-w-0 break-all font-mono text-xs font-semibold {{ $code->isActive() ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500 line-through dark:text-slate-400' }}">{{ $code->inventory_code }}</span>
                                <span class="ms-auto text-[11px] text-slate-500 dark:text-slate-400">{{ $code->issued_at?->format('d/m/Y') }}</span>
                                @if ($code->isActive())
                                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-bold text-green-800 dark:bg-green-950 dark:text-green-300">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Pensiun</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-3 rounded-xl bg-slate-50 p-2.5 text-xs leading-5 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Label terakhir dicetak</span>
                    <p class="mt-1">{{ $unit->last_label_printed_at?->format('d M Y H:i') ?: 'Belum pernah dicetak' }}</p>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Setelah mutasi, label wajib dicetak ulang.</p>
                </div>
            </section>
            @endunless

            @if (auth()->user()->canApproveLoans() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <span class="material-icons-round" aria-hidden="true">health_and_safety</span>
                        </span>
                        Kondisi &amp; Catatan Unit
                    </h3>
                    <p class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">Perubahan kondisi tercatat di audit log. Status <em>Dihapus</em> hanya melalui inventaris keluar.</p>
                    <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-2.5">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="unit-condition" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Kondisi</label>
                            <select id="unit-condition" name="condition" required class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($conditions as $conditionOption)
                                    <option value="{{ $conditionOption->value }}" @selected($unit->condition === $conditionOption)>{{ $conditionOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (auth()->user()->managesInventory())
                            <div>
                                <label for="unit-status" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Status</label>
                                <select id="unit-status" name="status" required class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    @foreach ($statuses as $statusOption)
                                        <option value="{{ $statusOption->value }}" @selected($unit->status === $statusOption)>{{ $statusOption->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="unit-status-reason" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Alasan perubahan status</label>
                                <input id="unit-status-reason" name="status_reason" type="text" maxlength="500"
                                       value="{{ old('status_reason') }}"
                                       placeholder="Wajib bila status diubah"
                                       class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @error('status_reason') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label for="unit-notes" class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">Catatan</label>
                            <textarea id="unit-notes" name="notes" rows="2" maxlength="1000"
                                      class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                      placeholder="Tambahkan catatan kondisi bila diperlukan">{{ old('notes', $unit->notes) }}</textarea>
                        </div>
                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            <span class="material-icons-round text-base" aria-hidden="true">save</span> Simpan Kondisi
                        </button>
                    </form>
                </section>
            @endif

            @if (auth()->user()->managesInventory() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS && $unit->status !== \App\Enums\UnitStatus::DIPINJAM && $destinationRooms->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-3 flex items-center gap-2.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                            <span class="material-icons-round" aria-hidden="true">swap_horiz</span>
                        </span>
                        Pindahkan Unit
                    </h3>
                    <p class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">Kode baru diterbitkan otomatis. Kode lama tetap tersimpan sebagai riwayat dan label wajib dicetak ulang.</p>
                    <form method="POST" action="{{ route('units.move', $unit) }}" class="space-y-2"
                          onsubmit="return confirm('Pindahkan unit ini? Kode inventaris akan berubah dan label harus dicetak ulang.')">
                        @csrf
                        <label for="destination-room" class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Ruangan tujuan</label>
                        <select id="destination-room" name="room_id" required class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option value="">— pilih ruangan tujuan —</option>
                            @foreach ($destinationRooms as $roomOption)
                                <option value="{{ $roomOption->id }}">
                                    {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <label for="movement-note" class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Catatan <span class="font-normal text-slate-500">(opsional)</span></label>
                        <input id="movement-note" type="text" name="note" maxlength="500" placeholder="Alasan perpindahan"
                               class="h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            <span class="material-icons-round text-base" aria-hidden="true">swap_horiz</span> Pindahkan Unit
                        </button>
                    </form>
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-slate-100">Kembali ke katalog</h3>
                <p class="mb-2 text-xs leading-5 text-slate-500 dark:text-slate-400">Lihat spesifikasi dan unit lain dalam katalog yang sama.</p>
                <a href="{{ route('catalogs.show', $unit->catalog) }}" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-brand-700 dark:hover:bg-slate-800 dark:hover:text-brand-300">
                    Lihat semua unit
                    <span class="material-icons-round text-base" aria-hidden="true">arrow_forward</span>
                </a>
            </section>
        </div>
    </div>
</x-app-layout>

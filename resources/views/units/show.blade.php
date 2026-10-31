<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('catalogs.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">Katalog</a>
                    <span class="text-slate-300">/</span>
                    <a href="{{ route('catalogs.show', $unit->catalog) }}" class="text-sm font-medium text-slate-500 hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">{{ $unit->catalog?->name }}</a>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    @if ($unit->specification)
                        <a href="{{ route('specifications.show', $unit->specification) }}" class="text-sm font-medium text-slate-500 hover:text-brand-700 dark:text-slate-400 dark:hover:text-brand-300">{{ $unit->specification->displayName() }}</a>
                        <span class="text-slate-300 dark:text-slate-600">/</span>
                    @endif
                    <h2 class="font-mono text-base font-semibold leading-none text-right text-slate-900 dark:text-white whitespace-nowrap">{{ $unit->inventory_code }}</h2>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $unit->locationLabel() }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $unit->condition->isDamaged() ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' }}">
                    {{ $unit->condition->label() }}
                </span>
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                @if (auth()->user()->canApproveLoans())
                    <a href="{{ route('units.label', $unit) }}" target="_blank"
                       class="inline-flex min-h-9 items-center gap-1 rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">
                        <span class="material-icons-round text-base">print</span> Cetak Label Unit
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">badge</span>
                    {{ auth()->user()->isPeminjam() ? 'Informasi Unit' : 'Identitas & Perolehan' }}
                </h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm text-slate-700 dark:text-slate-200 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kode inventaris</dt><dd class="font-mono font-semibold">{{ $unit->inventory_code }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Katalog</dt><dd class="font-medium">{{ $unit->catalog?->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Kategori</dt><dd>{{ $unit->catalog?->category?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Gedung / Lantai / Ruangan</dt><dd>{{ $unit->locationLabel() }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Jurusan</dt><dd>{{ $unit->room?->major?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Nomor register</dt><dd>{{ $unit->register_no ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Tanggal perolehan</dt><dd>{{ $unit->acquisition_date?->format('d/m/Y') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500 dark:text-slate-400">Tahun perolehan</dt><dd>{{ $unit->acquisition_year ?: '—' }}</dd></div>
                    @unless (auth()->user()->isPeminjam())
                        <div><dt class="text-xs text-slate-400">Nilai perolehan</dt><dd>{{ $unit->acquisition_value ? 'Rp '.number_format((float) $unit->acquisition_value, 0, ',', '.') : '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-400">Sumber dana / perolehan</dt><dd>{{ $unit->funding_source ?: '—' }}{{ $unit->acquisition_source ? ' • '.$unit->acquisition_source : '' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-slate-400">Catatan</dt><dd>{{ $unit->notes ?: '—' }}</dd></div>
                    @endunless
                </dl>
            </div>

            @unless (auth()->user()->isPeminjam())
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">swap_horiz</span> Riwayat Perpindahan
                </h3>
                @if ($unit->movements->isEmpty())
                    <p class="text-sm text-slate-400">Belum pernah dipindahkan.</p>
                @else
                    <ul class="space-y-3 text-xs">
                        @foreach ($unit->movements as $movement)
                            <li class="border-b border-dashed border-slate-200 pb-2 dark:border-slate-700">
                                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                    <span class="font-mono font-semibold">{{ $movement->old_code }}</span>
                                    <span class="material-icons-round text-sm text-slate-400">arrow_forward</span>
                                    <span class="font-mono font-semibold">{{ $movement->new_code }}</span>
                                    <span class="text-slate-400 ms-auto">{{ $movement->moved_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="mt-1 text-slate-500 dark:text-slate-400">
                                    {{ $movement->fromRoom?->name }} → {{ $movement->toRoom?->name }}
                                    • oleh {{ $movement->mover?->name }}
                                    @if ($movement->note) — {{ $movement->note }} @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @endunless

            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">build</span> Riwayat Pemeliharaan
                </h3>
                @if ($unit->maintenanceRecords->isEmpty())
                    <p class="rounded-xl bg-slate-50 px-3 py-4 text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Belum ada catatan pemeliharaan.</p>
                @else
                    <ul class="space-y-3 text-xs">
                        @foreach ($unit->maintenanceRecords as $record)
                            <li class="border-b border-dashed border-slate-200 pb-2 dark:border-slate-700">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $record->type }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ $record->performed_at?->format('d/m/Y') }}</span>
                                </div>
                                <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $record->description }}</p>
                                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ $record->performed_by ?: '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            @unless (auth()->user()->isPeminjam())
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">history</span> Riwayat Kode
                </h3>
                <ul class="space-y-2 text-xs">
                    @foreach ($unit->codes as $code)
                        <li class="flex items-center justify-between gap-2 border-b border-dashed border-slate-200 pb-2 dark:border-slate-700">
                            <span class="font-mono font-semibold {{ $code->isActive() ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500 line-through dark:text-slate-400' }}">{{ $code->inventory_code }}</span>
                            <span class="text-slate-400">{{ $code->issued_at?->format('d/m/Y') }}</span>
                            @if ($code->isActive())
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300">Aktif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Pensiun</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    Label terakhir dicetak: {{ $unit->last_label_printed_at?->format('d M Y H:i') ?: 'belum pernah' }}.
                    Setelah mutasi, label wajib dicetak ulang.
                </p>
            </div>
            @endunless

            @if (auth()->user()->canApproveLoans() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS)
                <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                    <h3 class="mb-2 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-brand-600">health_and_safety</span> Kondisi &amp; Catatan Unit
                    </h3>
                    <p class="mb-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">Perubahan kondisi tercatat di audit log. Status <em>Dihapus</em> hanya melalui inventaris keluar.</p>
                    <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-2">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="unit-condition" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-200">Kondisi</label>
                            <select id="unit-condition" name="condition" required class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                @foreach ($conditions as $conditionOption)
                                    <option value="{{ $conditionOption->value }}" @selected($unit->condition === $conditionOption)>{{ $conditionOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (auth()->user()->managesInventory())
                            <div>
                                <label for="unit-status" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-200">Status</label>
                                <select id="unit-status" name="status" required class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    @foreach ($statuses as $statusOption)
                                        <option value="{{ $statusOption->value }}" @selected($unit->status === $statusOption)>{{ $statusOption->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="unit-notes" class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-200">Catatan</label>
                            <textarea id="unit-notes" name="notes" rows="2" maxlength="1000"
                                      class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('notes', $unit->notes) }}</textarea>
                        </div>
                        <button class="min-h-10 w-full rounded-xl bg-brand-600 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Simpan Kondisi</button>
                    </form>
                </div>
            @endif

            @if (auth()->user()->managesInventory() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS && $unit->status !== \App\Enums\UnitStatus::DIPINJAM && $destinationRooms->isNotEmpty())
                <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                    <h3 class="mb-2 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-brand-600">swap_horiz</span> Pindahkan Unit
                    </h3>
                    <p class="mb-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">Kode baru diterbitkan otomatis. Kode lama tetap tersimpan sebagai riwayat dan label wajib dicetak ulang.</p>
                    <form method="POST" action="{{ route('units.move', $unit) }}" class="space-y-2"
                          onsubmit="return confirm('Pindahkan unit ini? Kode inventaris akan berubah dan label harus dicetak ulang.')">
                        @csrf
                        <select name="room_id" required class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option value="">— pilih ruangan tujuan —</option>
                            @foreach ($destinationRooms as $roomOption)
                                <option value="{{ $roomOption->id }}">
                                    {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="note" maxlength="500" placeholder="Alasan/catatan mutasi (opsional)"
                               class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button class="min-h-10 w-full rounded-xl bg-brand-600 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Pindahkan &amp; Terbitkan Kode Baru</button>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-2 text-base font-bold text-slate-900 dark:text-slate-100">Kembali ke katalog</h3>
                <a href="{{ route('catalogs.show', $unit->catalog) }}" class="inline-flex min-h-9 items-center text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">
                    Lihat seluruh unit {{ $unit->catalog?->name }} →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

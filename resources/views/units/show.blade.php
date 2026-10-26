<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('catalogs.index') }}" class="text-sm text-gray-500 hover:text-blue-700">Katalog</a>
                    <span class="text-gray-300">/</span>
                    <a href="{{ route('catalogs.show', $unit->catalog) }}" class="text-sm text-gray-500 hover:text-blue-700">{{ $unit->catalog?->name }}</a>
                    <span class="text-gray-300">/</span>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $unit->inventory_code }}</h2>
                </div>
                <p class="text-sm text-gray-500">{{ $unit->locationLabel() }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $unit->condition->label() }}
                </span>
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                @if (auth()->user()->canApproveLoans())
                    <a href="{{ route('units.label', $unit) }}" target="_blank"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                        <span class="material-icons-round text-base">print</span> Cetak Label Unit
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">badge</span> Identitas &amp; Perolehan
                </h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-xs text-gray-400">Kode inventaris</dt><dd class="font-mono font-semibold">{{ $unit->inventory_code }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Katalog</dt><dd class="font-medium">{{ $unit->catalog?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Kategori</dt><dd>{{ $unit->catalog?->category?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Gedung / Lantai / Ruangan</dt><dd>{{ $unit->locationLabel() }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Jurusan</dt><dd>{{ $unit->room?->major?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Nomor register</dt><dd>{{ $unit->register_no ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Tanggal perolehan</dt><dd>{{ $unit->acquisition_date?->format('d/m/Y') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Tahun perolehan</dt><dd>{{ $unit->acquisition_year ?: '—' }}</dd></div>
                    @unless (auth()->user()->isPeminjam())
                        <div><dt class="text-xs text-gray-400">Nilai perolehan</dt><dd>{{ $unit->acquisition_value ? 'Rp '.number_format((float) $unit->acquisition_value, 0, ',', '.') : '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Sumber dana / perolehan</dt><dd>{{ $unit->funding_source ?: '—' }}{{ $unit->acquisition_source ? ' • '.$unit->acquisition_source : '' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-400">Catatan</dt><dd>{{ $unit->notes ?: '—' }}</dd></div>
                    @endunless
                </dl>
            </div>

            @unless (auth()->user()->isPeminjam())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">swap_horiz</span> Riwayat Perpindahan
                </h3>
                @if ($unit->movements->isEmpty())
                    <p class="text-sm text-gray-400">Belum pernah dipindahkan.</p>
                @else
                    <ul class="space-y-3 text-xs">
                        @foreach ($unit->movements as $movement)
                            <li class="border-b border-dashed border-gray-100 pb-2">
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="font-mono font-semibold">{{ $movement->old_code }}</span>
                                    <span class="material-icons-round text-sm text-gray-400">arrow_forward</span>
                                    <span class="font-mono font-semibold">{{ $movement->new_code }}</span>
                                    <span class="text-gray-400 ms-auto">{{ $movement->moved_at?->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-gray-400 mt-0.5">
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

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">build</span> Riwayat Pemeliharaan
                </h3>
                @if ($unit->maintenanceRecords->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada catatan pemeliharaan.</p>
                @else
                    <ul class="space-y-3 text-xs">
                        @foreach ($unit->maintenanceRecords as $record)
                            <li class="border-b border-dashed border-gray-100 pb-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-700">{{ $record->type }}</span>
                                    <span class="text-gray-400">{{ $record->performed_at?->format('d/m/Y') }}</span>
                                </div>
                                <p class="text-gray-600 mt-0.5">{{ $record->description }}</p>
                                <p class="text-gray-400 mt-0.5">{{ $record->performed_by ?: '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            @unless (auth()->user()->isPeminjam())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-blue-600">history</span> Riwayat Kode
                </h3>
                <ul class="space-y-2 text-xs">
                    @foreach ($unit->codes as $code)
                        <li class="flex items-center justify-between gap-2 border-b border-dashed border-gray-100 pb-2">
                            <span class="font-mono font-semibold {{ $code->isActive() ? 'text-gray-800' : 'text-gray-400 line-through' }}">{{ $code->inventory_code }}</span>
                            <span class="text-gray-400">{{ $code->issued_at?->format('d/m/Y') }}</span>
                            @if ($code->isActive())
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-800">Aktif</span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Pensiun</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-[11px] text-gray-400">
                    Label terakhir dicetak: {{ $unit->last_label_printed_at?->format('d M Y H:i') ?: 'belum pernah' }}.
                    Setelah mutasi, label wajib dicetak ulang.
                </p>
            </div>
            @endunless

            @if (auth()->user()->canApproveLoans() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-2 flex items-center gap-1.5">
                        <span class="material-icons-round text-blue-600">health_and_safety</span> Kondisi &amp; Catatan Unit
                    </h3>
                    <p class="text-[11px] text-gray-500 mb-3">Perubahan kondisi tercatat di audit log. Status <em>Dihapus</em> hanya melalui inventaris keluar.</p>
                    <form method="POST" action="{{ route('units.update', $unit) }}" class="space-y-2">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="unit-condition" class="block text-xs font-medium text-gray-600 mb-1">Kondisi</label>
                            <select id="unit-condition" name="condition" required class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach ($conditions as $conditionOption)
                                    <option value="{{ $conditionOption->value }}" @selected($unit->condition === $conditionOption)>{{ $conditionOption->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (auth()->user()->managesInventory())
                            <div>
                                <label for="unit-status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                <select id="unit-status" name="status" required class="w-full rounded-lg border-gray-300 text-sm">
                                    @foreach ($statuses as $statusOption)
                                        <option value="{{ $statusOption->value }}" @selected($unit->status === $statusOption)>{{ $statusOption->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="unit-notes" class="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
                            <textarea id="unit-notes" name="notes" rows="2" maxlength="1000"
                                      class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes', $unit->notes) }}</textarea>
                        </div>
                        <button class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2">Simpan Kondisi</button>
                    </form>
                </div>
            @endif

            @if (auth()->user()->managesInventory() && $unit->status !== \App\Enums\UnitStatus::DIHAPUS && $unit->status !== \App\Enums\UnitStatus::DIPINJAM && $destinationRooms->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-2 flex items-center gap-1.5">
                        <span class="material-icons-round text-blue-600">swap_horiz</span> Pindahkan Unit
                    </h3>
                    <p class="text-[11px] text-gray-500 mb-3">Kode baru diterbitkan otomatis. Kode lama tetap tersimpan sebagai riwayat dan label wajib dicetak ulang.</p>
                    <form method="POST" action="{{ route('units.move', $unit) }}" class="space-y-2"
                          onsubmit="return confirm('Pindahkan unit ini? Kode inventaris akan berubah dan label harus dicetak ulang.')">
                        @csrf
                        <select name="room_id" required class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— pilih ruangan tujuan —</option>
                            @foreach ($destinationRooms as $roomOption)
                                <option value="{{ $roomOption->id }}">
                                    {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="note" maxlength="500" placeholder="Alasan/catatan mutasi (opsional)"
                               class="w-full rounded-lg border-gray-300 text-sm">
                        <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold py-2">Pindahkan &amp; Terbitkan Kode Baru</button>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-2">Kembali ke katalog</h3>
                <a href="{{ route('catalogs.show', $unit->catalog) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                    Lihat seluruh unit {{ $unit->catalog?->name }} →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

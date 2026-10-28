<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Peminjaman #{{ $loan->id }}</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                    @if ($loan->needs_high_value_approval)
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                    @endif
                    @if ($loan->isOverdue())
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-700">⚠ TERLAMBAT</span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $loan->purpose }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('loans.letter.preview', $loan) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                    <span class="material-icons-round text-lg">print</span> Pratinjau Surat
                </a>
                @if ($loan->bast_path)
                    <a href="{{ route('loans.bast', $loan) }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <span class="material-icons-round text-lg">description</span> Unduh BAST / Surat
                    </a>
                @endif
                @if ($loan->borrower_id === auth()->id())
                    <a href="{{ route('loans.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali</a>
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
            {{-- Rincian barang --}}
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-blue-600">format_list_bulleted</span> Rincian Barang
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2">Barang</th>
                                <th class="text-left py-2">Ruangan</th>
                                <th class="text-center py-2">Pinjam</th>
                                <th class="text-center py-2">Kembali</th>
                                <th class="text-center py-2">Kondisi Keluar</th>
                                <th class="text-center py-2">Kondisi Masuk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($loan->loanItems as $li)
                                @if ($li->hasUnitAssignments())
                                    <tr>
                                        <td class="py-2.5" colspan="6">
                                            <div class="text-[10px] uppercase text-gray-400 font-semibold mb-1">Unit fisik — {{ $li->item?->name }} ({{ $li->qty_borrowed }} {{ $li->item?->unit }})</div>
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs">
                                                @forelse ($li->unitAssignments()->with('unit.room')->get() as $assignment)
                                                    <div class="rounded-xl border p-2 {{ $assignment->isOpen() ? 'border-blue-200 bg-blue-50/40 dark:border-blue-900 dark:bg-blue-950/30' : 'border-slate-200 dark:border-slate-700' }}"
                                                         title="{{ $assignment->unit->locationLabel() }}">
                                                        <div class="font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $assignment->unit->inventory_code }}</div>
                                                        <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ $assignment->unit->room?->code ?? '-' }}</div>
                                                        <div class="text-[10px] {{ $assignment->unit->condition->isDamaged() ? 'text-red-600 font-semibold' : 'text-green-700' }}">
                                                            {{ $assignment->unit->condition->label() }}
                                                        </div>
                                                        <div class="text-[10px] {{ $assignment->isOpen() ? 'text-blue-700 font-semibold' : 'text-gray-500' }}">
                                                            {{ $assignment->isOpen() ? 'Dipinjam' : 'Kembali '.(optional($assignment->returned_at)->format('d/m H:i') ?: '') }}
                                                        </div>
                                                        @if ($assignment->condition_in)
                                                            <div class="text-[10px] {{ $assignment->condition_in->isDamaged() ? 'text-red-600' : 'text-gray-500' }}">
                                                                masuk: {{ $assignment->condition_in->label() }}
                                                            </div>
                                                        @endif
                                                        @if ($assignment->isOpen() && $assignment->unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                                            <div class="text-[10px] text-blue-700 font-semibold">status unit: sedang dipinjam</div>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <div class="col-span-full text-[11px] italic text-slate-500 dark:text-slate-400">
                                                        Unit fisik ditugaskan, namun tidak ditemukan catatan penugasan.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5">
                                            <a href="{{ route('items.show', $li->item) }}" class="font-semibold text-slate-800 hover:text-blue-700 dark:text-slate-100 dark:hover:text-blue-300">{{ $li->item?->name }}</a>
                                            <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $li->item?->internal_id }}</div>
                                            @if ($li->item?->is_high_value)
                                                <span class="text-[10px] font-bold text-purple-600">NILAI TINGGI</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $li->item?->room?->code }}</td>
                                        <td class="py-2.5 text-center text-xs font-semibold">{{ $li->qty_borrowed }}</td>
                                        <td class="py-2.5 text-center text-xs">
                                            @if ($li->qty_returned === null)
                                                <span class="text-gray-300">—</span>
                                            @else
                                                <span class="{{ $li->hasShortage() ? 'text-red-600 font-bold' : 'text-gray-700' }}">{{ $li->qty_returned }}</span>
                                                @if ($li->hasShortage())
                                                    <span class="block text-[10px] text-red-600 font-bold">HILANG {{ $li->missingQuantity() }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-center text-xs text-gray-500">{{ $li->condition_out->label() }}</td>
                                        <td class="py-2.5 text-center text-xs">
                                            @if ($li->condition_in)
                                                <span class="{{ $li->condition_in->isDamaged() ? 'text-red-600 font-bold' : 'text-green-700' }}">{{ $li->condition_in->label() }}</span>
                                                @if ($li->damage_photo_path)
                                                    <a href="{{ route('files.show', ['path' => $li->damage_photo_path]) }}" target="_blank" class="block text-[10px] text-blue-700 underline">foto bukti</a>
                                                @endif
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="py-2.5">
                                            <a href="{{ route('items.show', $li->item) }}" class="font-medium text-gray-800 hover:text-blue-700">{{ $li->item?->name }}</a>
                                            <div class="text-[11px] font-mono text-gray-400">{{ $li->item?->internal_id }}</div>
                                            @if ($li->item?->is_high_value)
                                                <span class="text-[10px] font-bold text-purple-600">NILAI TINGGI</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-xs text-gray-500">{{ $li->item?->room?->code }}</td>
                                        <td class="py-2.5 text-center text-xs font-semibold">{{ $li->qty_borrowed }}</td>
                                        <td class="py-2.5 text-center text-xs">
                                            @if ($li->qty_returned === null)
                                                <span class="text-gray-300">—</span>
                                            @else
                                                <span class="{{ $li->hasShortage() ? 'text-red-600 font-bold' : 'text-gray-700' }}">{{ $li->qty_returned }}</span>
                                                @if ($li->hasShortage())
                                                    <span class="block text-[10px] text-red-600 font-bold">HILANG {{ $li->missingQuantity() }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-center text-xs text-gray-500">{{ $li->condition_out->label() }}</td>
                                        <td class="py-2.5 text-center text-xs">
                                            @if ($li->condition_in)
                                                <span class="{{ $li->condition_in->isDamaged() ? 'text-red-600 font-bold' : 'text-green-700' }}">{{ $li->condition_in->label() }}</span>
                                                @if ($li->damage_photo_path)
                                                    <a href="{{ route('files.show', ['path' => $li->damage_photo_path]) }}" target="_blank" class="block text-[10px] text-blue-700 underline">foto bukti</a>
                                                @endif
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                    </table>
                </div>

                @if ($hasShortage)
                    <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                        <div class="flex items-start gap-2">
                            <span class="material-icons-round">warning</span>
                            <div>
                                <strong>Selisih kuantitas terdeteksi.</strong> Status pinjaman tidak dapat diselesaikan selama ada barang yang kurang — selisih otomatis berflag <strong>Hilang</strong> dan riwayat peminjam terakhir tercatat.
                                @if (auth()->user()->isApproverLevel2() && $loan->status !== \App\Enums\LoanStatus::SELESAI)
                                    <form method="POST" action="{{ route('loans.close-shortage', $loan) }}" class="mt-3"
                                          onsubmit="return confirm('Tutup pinjaman ini? Barang tetap berstatus Hilang sampai ditemukan kembali.')">
                                        @csrf
                                        <button                                         class="min-h-9 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                            Tutup Pinjaman (barang tetap Hilang)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::APPROVED && $canManage)
                    <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <span>Disetujui. Serahkan barang kepada peminjam untuk memulai masa pinjam.</span>
                            <a href="{{ route('loans.handover-form', $loan) }}"
                               class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700">
                                <span class="material-icons-round text-sm">inventory</span> Serah Terima + Pilih Unit
                            </a>
                        </div>
                        @if ($loan->loanItems->isNotEmpty())
                            <div class="mt-3 text-xs leading-relaxed text-blue-800 dark:text-blue-200">
                                Pilih unit fisik yang diserahkan pada halaman serah terima. Unit yang dipinjam orang lain, tidak aktif, atau rusak tidak dapat dipilih.
                            </div>
                        @endif
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::DIPINJAM && $canManage)
                    <div class="mt-4 flex justify-end">
                        <a href="{{ route('loans.return-form', $loan) }}"
                           class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="material-icons-round text-lg">assignment_return</span> Proses Pengembalian
                        </a>
                    </div>
                @endif
            </div>

            {{-- Approval --}}
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                <h3 class="mb-4 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-blue-600">verified_user</span> Jejak Persetujuan (Berjenjang)
                </h3>

                @if ($loan->approvals->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada persetujuan.</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($loan->approvals->sortBy('level') as $approval)
                            @php
                                $approverRole = $approval->approver?->role
                                    ? \App\Enums\Role::from($approval->approver->role)->shortLabel()
                                    : 'Pihak berwenang';
                            @endphp
                            <li class="flex items-start gap-3 border-b border-dashed border-slate-200 pb-4 last:border-0 last:pb-0 dark:border-slate-700">
                                <span class="inline-flex items-center justify-center h-9 w-9 rounded-full
                                    {{ $approval->decision->value === 'APPROVED' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    <span class="material-icons-round text-xl">{{ $approval->decision->value === 'APPROVED' ? 'check_circle' : 'cancel' }}</span>
                                </span>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        Level {{ $approval->level }} — {{ $approverRole }}:
                                        {{ $approval->approver?->name }}
                                        <span class="text-[11px] font-bold {{ $approval->decision->value === 'APPROVED' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $approval->decision->label() }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $approval->decided_at->format('d M Y H:i') }}</div>
                                    @if ($approval->note)
                                        <div class="mt-1 text-xs italic text-slate-600 dark:text-slate-300">"{{ $approval->note }}"</div>
                                    @endif
                                </div>
                                @if ($approval->signature_path)
                                    <div class="text-center shrink-0">
                                        <img src="{{ route('files.show', ['path' => $approval->signature_path]) }}" alt="TTD" class="h-12 object-contain border border-gray-200 rounded bg-white px-1">
                                        <div class="text-[9px] text-gray-400">TTD Digital</div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Form persetujuan --}}
                @if ($canAct && $nextLevel !== null)
                    @php $actorRole = \App\Enums\Role::from(auth()->user()->role)->shortLabel(); @endphp
                    <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-700">
                        <div class="flex items-center gap-2 mb-3 text-sm text-gray-700">
                            <span class="material-icons-round text-blue-600">gesture</span>
                            Keputusan Anda: <strong>Persetujuan Level {{ $nextLevel }} ({{ $actorRole }})</strong>
                        </div>

                        <form method="POST" action="{{ route('loans.approve', $loan) }}" id="approve-form" class="space-y-4">
                            @csrf
                            <input type="hidden" name="signature" id="signature-input">
                            <div>
                                <x-input-label value="Catatan (opsional)" />
                                <textarea name="note" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                          placeholder="Syarat/catatan persetujuan..."></textarea>
                            </div>

                            <div x-data="signaturePad()" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 dark:border-slate-600 dark:bg-slate-800">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-600">
                                        Tanda Tangan Digital
                                        <span class="text-gray-400 font-normal">(opsional)</span>
                                    </span>
                                    <button type="button" @click="clear()" class="text-xs text-gray-500 hover:text-gray-800 underline">Hapus</button>
                                </div>
                                <canvas id="signature-pad" width="420" height="140"
                                        class="w-full touch-none rounded-lg border border-slate-200 bg-white dark:border-slate-600"></canvas>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    Tanda tangan digital bersifat opsional. Dokumen resmi dapat dipratinjau, dicetak, dan ditandatangani secara fisik/manual.
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-bold">
                                    <span class="material-icons-round text-lg">check_circle</span> Setujui
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('loans.reject', $loan) }}" id="reject-form" class="mt-3 space-y-2">
                            @csrf
                            <div>
                                <x-input-label value="Alasan Penolakan *" />
                                <input type="text" name="note" required
                                       class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 text-sm"
                                       placeholder="Barang dipakai kegiatan lain, dsb.">
                                <x-input-error :messages="$errors->get('note')" class="mt-1" />
                            </div>
                            <button class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                                <span class="material-icons-round text-lg">cancel</span> Tolak Peminjaman
                            </button>
                        </form>

                        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            @if (auth()->user()->isApproverLevel2())
                                Persetujuan Anda sebagai Wakasek Sarpras langsung menutup rangkaian persetujuan — tidak dituntut persetujuan Admin tambahan.
                            @elseif ($loan->requiresLevel2() && $nextLevel === 1)
                                Setelah Level 1, request lanjut ke Wakasek Sarpras (Level 2) untuk barang bernilai tinggi.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-5">
            <div class="space-y-3 rounded-2xl border border-sky-100 bg-white p-5 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-blue-600">info</span> Ringkasan
                </h3>
                @unless (auth()->user()->isPeminjam())
                    <div class="text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">Peminjam:</span> <strong>{{ $loan->borrower?->name }}</strong></div>
                    <div class="text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">Email:</span> {{ $loan->borrower?->email }}</div>
                    <div class="text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">Jurusan:</span> {{ $loan->borrower?->major?->name ?: '-' }}</div>
                @endunless
                <div class="text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">{{ auth()->user()->isPeminjam() ? 'Mulai pinjam:' : 'Pinjam:' }}</span> <strong>{{ $loan->start_date->format('d M Y H:i') }}</strong></div>
                <div class="text-slate-700 dark:text-slate-200"><span class="text-slate-500 dark:text-slate-400">{{ auth()->user()->isPeminjam() ? 'Batas pengembalian:' : 'Kembali:' }}</span> <strong class="{{ $loan->isOverdue() ? 'text-red-600 dark:text-red-400' : '' }}">{{ $loan->due_date->format('d M Y H:i') }}</strong></div>
                @if ($loan->event)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Kegiatan:</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $loan->event->name }}</div>
                        <div class="text-xs text-slate-600 dark:text-slate-300">PJ: {{ $loan->event->pic_name }}</div>
                        <div class="text-xs text-slate-600 dark:text-slate-300">{{ $loan->event->start_date->format('d/m/Y') }} — {{ $loan->event->end_date->format('d/m/Y') }}</div>
                        <a href="{{ route('events.show', $loan->event) }}" class="inline-flex min-h-8 items-center text-xs font-semibold text-blue-700 hover:underline dark:text-blue-300">Lihat rekap kegiatan →</a>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DITOLAK && $loan->reject_reason)
                    <div class="border-t border-dashed border-slate-200 pt-3 text-red-600 dark:border-slate-700 dark:text-red-400">
                        <div class="text-xs font-bold">DITOLAK:</div>
                        <div class="text-xs">{{ $loan->reject_reason }}</div>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DIBATALKAN)
                    <div class="border-t border-dashed border-slate-200 pt-3 text-slate-600 dark:border-slate-700 dark:text-slate-300">
                        <div class="text-xs font-bold">DIBATALKAN:</div>
                        <div class="text-xs">{{ $loan->cancel_reason ?: 'Tanpa alasan tertulis' }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">oleh {{ $loan->canceller?->name }} • {{ $loan->cancelled_at?->format('d M Y H:i') }}</div>
                    </div>
                @endif
                @if ($loan->handed_over_at)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400">Diserahkan:</span> <span class="text-slate-700 dark:text-slate-200">{{ $loan->handed_over_at->format('d M Y H:i') }}</span>
                        <div class="text-xs text-slate-500 dark:text-slate-400">oleh {{ $loan->handoverOfficer?->name }}</div>
                    </div>
                @endif

                @if ($canCancel)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <form method="POST" action="{{ route('loans.cancel', $loan) }}"
                              onsubmit="return confirm('Batalkan pengajuan peminjaman ini? Jadwal akan dibebaskan.')"
                              class="space-y-2">
                            @csrf
                            <input type="text" name="cancel_reason" maxlength="500" placeholder="Alasan pembatalan (opsional)"
                                   class="w-full rounded-xl border-slate-300 bg-white text-xs text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <button class="inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-red-50 hover:text-red-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                <span class="material-icons-round text-sm">cancel</span> Batalkan Pengajuan
                            </button>
                        </form>
                    </div>
                @endif
                @if ($loan->returned_at)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400">Dikembalikan:</span> <span class="text-slate-700 dark:text-slate-200">{{ $loan->returned_at->format('d M Y H:i') }}</span>
                        <div class="text-xs text-slate-500 dark:text-slate-400">oleh {{ $loan->returner?->name }}</div>
                    </div>
                @endif
            </div>

            @if (in_array($loan->status, [\App\Enums\LoanStatus::PENDING, \App\Enums\LoanStatus::APPROVED, \App\Enums\LoanStatus::DIPINJAM, \App\Enums\LoanStatus::SELESAI], true))
                <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-3 flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-blue-600">description</span> Dokumen Resmi
                    </h3>

                    <div class="mb-3 flex items-center justify-between gap-2 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Status dokumen</span>
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->document_status->badgeClass() }}">
                            {{ $loan->document_status->label() }}
                        </span>
                    </div>

                    <a href="{{ route('loans.bast', $loan) }}" target="_blank"
                       class="mb-2 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        {{ $loan->event ? 'Surat Peminjaman / BAST (PDF)' : 'Buat Surat Peminjaman (PDF)' }}
                    </a>
                    <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Dokumen dapat dipratinjau dan dicetak, lalu ditandatangani secara fisik oleh Peminjam, Laboran, dan Wakasek.
                        Status dokumen terpisah dari status persetujuan transaksi.
                    </p>

                    @if ($canManageDocuments)
                        <div class="mt-3 space-y-2 border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Catatan petugas</p>
                            <div class="flex flex-wrap gap-1.5">
                                @unless ($loan->document_status === \App\Enums\DocumentStatus::DICETAK)
                                    <form method="POST" action="{{ route('loans.document.printed', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                            Tandai sudah dicetak
                                        </button>
                                    </form>
                                @endunless
                                @unless ($loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-physical', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                            Sudah ditandatangani (fisik)
                                        </button>
                                    </form>
                                @endunless
                                @if ($loan->approvals->contains(fn ($a) => $a->signature_path) && ! $loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-digital', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                            Sudah ditandatangani (digital)
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Mencatat tanda tangan tidak mengubah status persetujuan transaksi.
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function signaturePad() {
            return {
                pad: null,
                clear() {
                    if (this.pad) this.pad.clear();
                    document.getElementById('signature-input').value = '';
                },
                init() {
                    const canvas = document.getElementById('signature-pad');
                    const ctx = canvas.getContext('2d');
                    let drawing = false;
                    const pos = (e) => {
                        const r = canvas.getBoundingClientRect();
                        const p = e.touches ? e.touches[0] : e;
                        return { x: (p.clientX - r.left) * (canvas.width / r.width), y: (p.clientY - r.top) * (canvas.height / r.height) };
                    };
                    const start = (e) => { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
                    const move = (e) => {
                        if (!drawing) return;
                        const p = pos(e);
                        ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1e293b';
                        ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault();
                    };
                    const end = () => {
                        if (!drawing) return;
                        drawing = false;
                        document.getElementById('signature-input').value = canvas.toDataURL('image/png');
                    };
                    canvas.addEventListener('mousedown', start);
                    canvas.addEventListener('mousemove', move);
                    window.addEventListener('mouseup', end);
                    canvas.addEventListener('touchstart', start, { passive: false });
                    canvas.addEventListener('touchmove', move, { passive: false });
                    canvas.addEventListener('touchend', end);
                }
            };
        }
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('signature-pad');
            if (el) {
                // init Alpine component scope manually
                const parent = el.closest('[x-data]');
                if (parent && parent._x_dataStack) {
                    // Alpine auto-inits; nothing extra needed
                }
            }
        });
    </script>
    @endpush
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Peminjaman #{{ $loan->id }}</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                    @if ($loan->needs_high_value_approval)
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-purple-100 text-purple-800">NILAI TINGGI</span>
                    @endif
                    @if ($loan->isOverdue())
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-700">⚠ TERLAMBAT</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500">{{ $loan->purpose }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if ($loan->bast_path)
                    <a href="{{ route('loans.bast', $loan) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                        <span class="material-icons-round text-lg">description</span> Unduh BAST / Surat
                    </a>
                @endif
                @if ($loan->borrower_id === auth()->id())
                    <a href="{{ route('loans.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali</a>
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
            {{-- Rincian barang --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">format_list_bulleted</span> Rincian Barang
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-400 border-b border-gray-100">
                            <tr>
                                <th class="text-left py-2">Barang</th>
                                <th class="text-left py-2">Ruangan</th>
                                <th class="text-center py-2">Pinjam</th>
                                <th class="text-center py-2">Kembali</th>
                                <th class="text-center py-2">Kondisi Keluar</th>
                                <th class="text-center py-2">Kondisi Masuk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($loan->loanItems as $li)
                                @if ($li->hasUnitAssignments())
                                    <tr>
                                        <td class="py-2.5" colspan="6">
                                            <div class="text-[10px] uppercase text-gray-400 font-semibold mb-1">Unit fisik — {{ $li->item?->name }} ({{ $li->qty_borrowed }} {{ $li->item?->unit }})</div>
                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs">
                                                @forelse ($li->unitAssignments()->with('unit.room')->get() as $assignment)
                                                    <div class="rounded-lg border {{ $assignment->isOpen() ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200' }}"
                                                         title="{{ $assignment->unit->locationLabel() }}">
                                                        <div class="font-mono font-semibold text-gray-800">{{ $assignment->unit->inventory_code }}</div>
                                                        <div class="text-[10px] text-gray-500">{{ $assignment->unit->room?->code ?? '-' }}</div>
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
                                                    <div class="col-span-full text-[11px] text-gray-400 italic">
                                                        Unit fisik ditugaskan, namun tidak ditemukan catatan penugasan.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5">
                                            <a href="{{ route('items.show', $li->item) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $li->item?->name }}</a>
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
                                                    <a href="{{ route('files.show', ['path' => $li->damage_photo_path]) }}" target="_blank" class="block text-[10px] text-amber-700 underline">foto bukti</a>
                                                @endif
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="py-2.5">
                                            <a href="{{ route('items.show', $li->item) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $li->item?->name }}</a>
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
                                                    <a href="{{ route('files.show', ['path' => $li->damage_photo_path]) }}" target="_blank" class="block text-[10px] text-amber-700 underline">foto bukti</a>
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
                    <div class="mt-4 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                        <div class="flex items-start gap-2">
                            <span class="material-icons-round">warning</span>
                            <div>
                                <strong>Selisih kuantitas terdeteksi.</strong> Status pinjaman tidak dapat diselesaikan selama ada barang yang kurang — selisih otomatis berflag <strong>Hilang</strong> dan riwayat peminjam terakhir tercatat.
                                @if (auth()->user()->isApproverLevel2() && $loan->status !== \App\Enums\LoanStatus::SELESAI)
                                    <form method="POST" action="{{ route('loans.close-shortage', $loan) }}" class="mt-3"
                                          onsubmit="return confirm('Tutup pinjaman ini? Barang tetap berstatus Hilang sampai ditemukan kembali.')">
                                        @csrf
                                        <button class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold">
                                            Tutup Pinjaman (barang tetap Hilang)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::APPROVED && $canManage)
                    <div class="mt-4 rounded-lg bg-indigo-50 border border-indigo-200 p-4 text-sm text-indigo-800">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <span>Disetujui. Serahkan barang kepada peminjam untuk memulai masa pinjam.</span>
                            <a href="{{ route('loans.handover-form', $loan) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">
                                <span class="material-icons-round text-sm">inventory</span> Serah Terima + Pilih Unit
                            </a>
                        </div>
                        @if ($loan->loanItems->isNotEmpty())
                            <div class="mt-3 text-[11px] text-indigo-700">
                                Pilih unit fisik yang diserahkan pada halaman serah terima. Unit yang dipinjam orang lain, tidak aktif, atau rusak tidak dapat dipilih.
                            </div>
                        @endif
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::DIPINJAM && $canManage)
                    <div class="mt-4 flex justify-end">
                        <a href="{{ route('loans.return-form', $loan) }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">
                            <span class="material-icons-round text-lg">assignment_return</span> Proses Pengembalian
                        </a>
                    </div>
                @endif
            </div>

            {{-- Approval --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">verified_user</span> Jejak Persetujuan (Berjenjang)
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
                            <li class="flex items-start gap-3 border-b border-dashed border-gray-100 pb-4 last:border-0 last:pb-0">
                                <span class="inline-flex items-center justify-center h-9 w-9 rounded-full
                                    {{ $approval->decision->value === 'APPROVED' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    <span class="material-icons-round text-xl">{{ $approval->decision->value === 'APPROVED' ? 'check_circle' : 'cancel' }}</span>
                                </span>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-gray-800">
                                        Level {{ $approval->level }} — {{ $approverRole }}:
                                        {{ $approval->approver?->name }}
                                        <span class="text-[11px] font-bold {{ $approval->decision->value === 'APPROVED' ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $approval->decision->label() }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-400">{{ $approval->decided_at->format('d M Y H:i') }}</div>
                                    @if ($approval->note)
                                        <div class="text-xs text-gray-600 mt-1 italic">"{{ $approval->note }}"</div>
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
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <div class="flex items-center gap-2 mb-3 text-sm text-gray-700">
                            <span class="material-icons-round text-amber-600">gesture</span>
                            Keputusan Anda: <strong>Persetujuan Level {{ $nextLevel }} ({{ $actorRole }})</strong>
                        </div>

                        <form method="POST" action="{{ route('loans.approve', $loan) }}" id="approve-form" class="space-y-4">
                            @csrf
                            <input type="hidden" name="signature" id="signature-input">
                            <div>
                                <x-input-label value="Catatan (opsional)" />
                                <textarea name="note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm"
                                          placeholder="Syarat/catatan persetujuan..."></textarea>
                            </div>

                            <div x-data="signaturePad()" class="border border-dashed border-gray-300 rounded-lg p-3 bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-600">
                                        Tanda Tangan Digital
                                        <span class="text-gray-400 font-normal">(opsional)</span>
                                    </span>
                                    <button type="button" @click="clear()" class="text-xs text-gray-500 hover:text-gray-800 underline">Hapus</button>
                                </div>
                                <canvas id="signature-pad" width="420" height="140"
                                        class="w-full bg-white rounded border border-gray-200 touch-none"></canvas>
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

                        <p class="text-[11px] text-gray-400 mt-3">
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
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-sm space-y-3">
                <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">info</span> Ringkasan
                </h3>
                <div><span class="text-gray-400">Peminjam:</span> <strong>{{ $loan->borrower?->name }}</strong></div>
                <div><span class="text-gray-400">Username:</span> {{ $loan->borrower?->username }}</div>
                <div><span class="text-gray-400">Jurusan:</span> {{ $loan->borrower?->major?->name ?: '-' }}</div>
                <div><span class="text-gray-400">Pinjam:</span> <strong>{{ $loan->start_date->format('d M Y H:i') }}</strong></div>
                <div><span class="text-gray-400">Kembali:</span> <strong class="{{ $loan->isOverdue() ? 'text-red-600' : '' }}">{{ $loan->due_date->format('d M Y H:i') }}</strong></div>
                @if ($loan->event)
                    <div class="pt-3 border-t border-dashed border-gray-100">
                        <div class="text-gray-400 text-xs">Kegiatan:</div>
                        <div class="font-semibold text-gray-800">{{ $loan->event->name }}</div>
                        <div class="text-xs text-gray-500">PJ: {{ $loan->event->pic_name }}</div>
                        <div class="text-xs text-gray-500">{{ $loan->event->start_date->format('d/m/Y') }} — {{ $loan->event->end_date->format('d/m/Y') }}</div>
                        <a href="{{ route('events.show', $loan->event) }}" class="text-xs text-amber-700 hover:underline">Lihat rekap kegiatan →</a>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DITOLAK && $loan->reject_reason)
                    <div class="pt-3 border-t border-dashed border-gray-100 text-red-600">
                        <div class="text-xs font-bold">DITOLAK:</div>
                        <div class="text-xs">{{ $loan->reject_reason }}</div>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DIBATALKAN)
                    <div class="pt-3 border-t border-dashed border-gray-100 text-gray-600">
                        <div class="text-xs font-bold">DIBATALKAN:</div>
                        <div class="text-xs">{{ $loan->cancel_reason ?: 'Tanpa alasan tertulis' }}</div>
                        <div class="text-[11px] text-gray-400">oleh {{ $loan->canceller?->name }} • {{ $loan->cancelled_at?->format('d M Y H:i') }}</div>
                    </div>
                @endif
                @if ($loan->handed_over_at)
                    <div class="pt-3 border-t border-dashed border-gray-100">
                        <span class="text-gray-400">Diserahkan:</span> {{ $loan->handed_over_at->format('d M Y H:i') }}
                        <div class="text-xs text-gray-400">oleh {{ $loan->handoverOfficer?->name }}</div>
                    </div>
                @endif

                @if ($canCancel)
                    <div class="pt-3 border-t border-dashed border-gray-100">
                        <form method="POST" action="{{ route('loans.cancel', $loan) }}"
                              onsubmit="return confirm('Batalkan pengajuan peminjaman ini? Jadwal akan dibebaskan.')"
                              class="space-y-2">
                            @csrf
                            <input type="text" name="cancel_reason" maxlength="500" placeholder="Alasan pembatalan (opsional)"
                                   class="w-full rounded-lg border-gray-300 text-xs focus:ring-red-500 focus:border-red-500">
                            <button class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-gray-100 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-semibold">
                                <span class="material-icons-round text-sm">cancel</span> Batalkan Pengajuan
                            </button>
                        </form>
                    </div>
                @endif
                @if ($loan->returned_at)
                    <div class="pt-3 border-t border-dashed border-gray-100">
                        <span class="text-gray-400">Dikembalikan:</span> {{ $loan->returned_at->format('d M Y H:i') }}
                        <div class="text-xs text-gray-400">oleh {{ $loan->returner?->name }}</div>
                    </div>
                @endif
            </div>

            @if (in_array($loan->status, [\App\Enums\LoanStatus::PENDING, \App\Enums\LoanStatus::APPROVED, \App\Enums\LoanStatus::DIPINJAM, \App\Enums\LoanStatus::SELESAI], true))
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                        <span class="material-icons-round text-amber-600">description</span> Dokumen Resmi
                    </h3>

                    <div class="flex items-center justify-between gap-2 mb-3 text-xs">
                        <span class="text-gray-400">Status dokumen</span>
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->document_status->badgeClass() }}">
                            {{ $loan->document_status->label() }}
                        </span>
                    </div>

                    <a href="{{ route('loans.bast', $loan) }}" target="_blank"
                       class="block w-full text-center px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold mb-2">
                        {{ $loan->event ? 'Surat Peminjaman / BAST (PDF)' : 'Buat Surat Peminjaman (PDF)' }}
                    </a>
                    <p class="text-[11px] text-gray-400">
                        Dokumen dapat dipratinjau dan dicetak, lalu ditandatangani secara fisik oleh Peminjam, Laboran, dan Wakasek.
                        Status dokumen terpisah dari status persetujuan transaksi.
                    </p>

                    @if ($canManageDocuments)
                        <div class="mt-3 pt-3 border-t border-dashed border-gray-100 space-y-1.5">
                            <p class="text-[11px] font-semibold text-gray-500">Catatan petugas</p>
                            <div class="flex flex-wrap gap-1.5">
                                @unless ($loan->document_status === \App\Enums\DocumentStatus::DICETAK)
                                    <form method="POST" action="{{ route('loans.document.printed', $loan) }}">
                                        @csrf
                                        <button class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-[11px] font-semibold">
                                            Tandai sudah dicetak
                                        </button>
                                    </form>
                                @endunless
                                @unless ($loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-physical', $loan) }}">
                                        @csrf
                                        <button class="px-2.5 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-[11px] font-semibold">
                                            Sudah ditandatangani (fisik)
                                        </button>
                                    </form>
                                @endunless
                                @if ($loan->approvals->contains(fn ($a) => $a->signature_path) && ! $loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-digital', $loan) }}">
                                        @csrf
                                        <button class="px-2.5 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-[11px] font-semibold">
                                            Sudah ditandatangani (digital)
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="text-[10px] text-gray-400">
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

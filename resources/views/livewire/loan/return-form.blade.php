<div class="space-y-5">
    @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
            Peminjaman masih berstatus <strong>Disetujui (belum diambil/diproses)</strong> — pengembalian tetap dapat divalidasi di sini.
        </div>
    @endif

    {{-- Alert ruangan tertukar --}}
    @if ($mismatches !== [])
        <div class="flex items-start gap-2 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-200"
             wire:key="mismatch-alert">
            <span class="material-icons-round">warning</span>
            <div>
                <strong>Barang tertukar / ruangan berbeda!</strong>
                Ada barang yang tujuannya bukan ruangan asalnya. Sistem tetap memproses, namun tercatat sebagai
                <strong>mutasi lokasi otomatis</strong> (riwayat + pembaruan ID Internal).
                Pastikan ini memang disengaja.
            </div>
        </div>
    @endif

    @if ($loan->loanItems->isNotEmpty() && ! $hasUnitAssignmentsInRows)
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
            Peminjaman ini belum memiliki penugasan unit fisik (data legacy). Pengembalian dicatat per baris katalog.
        </div>
    @endif

    <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between mb-4">
            <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-blue-600">fact_check</span> Validasi Pengembalian per Kelompok Barang
            </h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">Peminjam: <strong>{{ $loan->borrower?->name }}</strong> • Kembali {{ $loan->due_date->format('d/m/Y H:i') }}</span>
        </div>

        <div class="space-y-4">
            @foreach ($groupedRows as $gIndex => $group)
                <details class="rounded-xl border border-slate-200 dark:border-slate-700" open>
                    <summary class="flex cursor-pointer items-center justify-between rounded-t-xl bg-slate-50 px-4 py-3 dark:bg-slate-800">
                        <div>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $group['item_name'] }}</span>
                            <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">— {{ $group['total_borrowed'] }} unit dipinjam</span>
                        </div>
                        <div class="text-xs text-slate-600 dark:text-slate-300">
                            Sudah kembali: <strong>{{ $group['already_returned'] + $group['returned'] }}</strong> / {{ $group['total_borrowed'] }} |
                            Belum: <strong>{{ max(0, $group['total_borrowed'] - ($group['already_returned'] + $group['returned'])) }}</strong>
                        </div>
                    </summary>
                    <div class="p-4 space-y-3">
                        @foreach ($group['rows'] as $rowKey => $row)
                            @php($i = $loop->parent->index * 100 + $loop->index)
                            @php($isMismatch = in_array($i, $mismatches, true))
                            <div @class([
                                'rounded-xl border p-3',
                                'border-orange-300 bg-orange-50/40 dark:border-orange-900 dark:bg-orange-950/20' => $isMismatch,
                                'border-slate-200 dark:border-slate-700' => ! $isMismatch,
                            ]) wire:key="row-{{ $i }}">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        @if (! empty($row['inventory_code']))
                                            <div class="text-sm font-mono font-semibold text-gray-800">{{ $row['inventory_code'] }}</div>
                                            <div class="text-[11px] text-gray-500">{{ $row['unit_location'] ?? $row['room_code'] }}</div>
                                        @else
                                            <div class="text-sm text-gray-800">{{ $row['item_name'] }}</div>
                                            <div class="text-[11px] font-mono text-gray-400">{{ $row['internal_id'] }} • Ruang asal: {{ $row['room_code'] }}</div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Status: <strong>{{ ($row['remaining'] ?? 0) == 0 ? 'Sudah dikembalikan' : 'Belum dikembalikan' }}</strong>
                                        <span class="block text-[10px] text-gray-400">Kondisi keluar: {{ \App\Enums\Condition::from($row['condition_out'])->label() }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                    <div>
                                        <x-input-label value="Jumlah Kembali *" />
                                        <input type="number" min="0" max="{{ $row['remaining'] }}" wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.qty_returned"
                                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    </div>

                                    <div>
                                        <x-input-label value="Kondisi Akhir *" />
                                        <select wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.condition_in"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                            @foreach ($conditions as $c)
                                                <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                            @endforeach
                                        </select>
                                        @if (($row['condition_in'] ?? 'BAIK') !== 'BAIK')
                                            <div class="mt-2">
                                                <input type="file" wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.damage_photo" accept="image/*"
                                                       class="mt-1 block w-full text-xs text-slate-600 file:me-2 file:rounded-lg file:border-0 file:bg-red-50 file:px-2 file:py-1.5 file:text-red-700 dark:text-slate-300">
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <x-input-label value="Dikembalikan ke Ruangan *" />
                                        <select wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.returned_room_id"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 @if($isMismatch) border-orange-400 dark:border-orange-500 @endif">
                                            @foreach ($rooms as $room)
                                                <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>

    {{-- Ringkasan & konfirmasi --}}
    <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="text-sm">
                @if ($totalShortfall > 0)
                    <span class="text-red-600 font-semibold">⚠ Total selisih: {{ $totalShortfall }} barang — flag Hilang akan dicatat.</span>
                @else
                    <span class="text-green-700 font-semibold">✓ Semua barang kembali lengkap — pinjaman akan berstatus Selesai.</span>
                @endif
            </div>
        </div>

        @if ($mismatches !== [])
            <label class="mt-3 flex items-start gap-2 text-sm text-gray-700 bg-orange-50 border border-orange-200 rounded-lg p-3">
                <input type="checkbox" wire:model.live="confirmMismatch" class="mt-0.5 rounded border-orange-400 text-orange-600 focus:ring-orange-500">
                <span>Saya memahami ada barang dikembalikan ke ruangan berbeda dan setuju tercatat sebagai <strong>mutasi otomatis</strong>.</span>
            </label>
            <x-input-error :messages="$errors->get('confirmMismatch')" class="mt-1" />
        @endif

        <div class="mt-4 flex flex-col sm:flex-row gap-3">
            <button wire:click="submit" wire:loading.attr="disabled"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-bold">
                <span wire:loading.remove wire:target="submit" class="material-icons-round text-lg">task_alt</span>
                <span wire:loading wire:target="submit" class="material-icons-round text-lg animate-spin">progress_activity</span>
                Proses Pengembalian
            </button>
            <a href="{{ route('loans.show', $loan) }}" class="w-full sm:w-auto text-center px-5 py-3 rounded-lg bg-white border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                Batal
            </a>
        </div>
        <p class="text-[11px] text-gray-400 mt-2">
            Jika kondisi akhir rusak: foto bukti wajib, status barang diperbarui (Rusak Ringan/Berat), dan riwayat peminjam terakhir tercatat.
        </p>
    </div>
</div>

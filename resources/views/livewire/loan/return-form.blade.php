<div class="space-y-5">
    @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
            Peminjaman masih berstatus <strong>Disetujui (belum diambil/diproses)</strong> — pengembalian tetap dapat divalidasi di sini.
        </div>
    @endif

    @if ($mismatches !== [])
        <div class="flex items-start gap-2 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-200" wire:key="mismatch-alert">
            <span class="material-icons-round">warning</span>
            <div>
                <strong>Barang tertukar / ruangan berbeda!</strong>
                Ada unit yang tujuannya bukan ruangan asalnya. Sistem tetap memproses setelah Anda menyetujui, namun tercatat sebagai
                <strong>mutasi lokasi otomatis</strong> (riwayat + pembaruan ID Internal).
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">fact_check</span>
                    Validasi Pengembalian per Unit
                </h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Peminjam: <strong>{{ $loan->borrower?->name }}</strong> • Kembali
                    {{ $loan->due_date->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ $progress['confirmed'] }} dari {{ $progress['total'] }} unit sudah dikonfirmasi
                </div>
                <div class="text-xs {{ $progress['pending'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-green-700 dark:text-green-300' }}">
                    {{ $progress['pending'] > 0 ? $progress['pending'].' unit belum dikonfirmasi' : 'Seluruh unit terkonfirmasi' }}
                </div>
            </div>
        </div>

        <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar"
            aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full transition-all {{ $progress['complete'] ? 'bg-green-500' : 'bg-brand-600' }}"
                style="width: {{ $progress['percent'] }}%"></div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-200">
                {{ $selectedCount }} unit dipilih
            </span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ $progress['pending'] }} belum dikonfirmasi
            </span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Menampilkan {{ $visibleUnitCount }} dari {{ $totalUnitCount }} unit
            </span>
            @if ($selectedCount > 0)
                <button type="button" wire:click="clearSelection"
                    class="rounded-full border border-slate-300 px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    Bersihkan pilihan
                </button>
            @endif
        </div>

        {{-- Pencarian, filter, dan scan untuk menemukan unit --}}
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label for="return-search" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Cari unit</label>
                    <input id="return-search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Barang, spesifikasi, kode, ruangan, catatan…"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div>
                    <label for="return-filter-validation" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Status validasi</label>
                    <select id="return-filter-validation" wire:model.live="filterValidation"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua status</option>
                        @foreach ($returnStatuses as $returnStatus)
                            <option value="{{ $returnStatus->value }}">{{ $returnStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="return-filter-condition" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Kondisi unit</label>
                    <select id="return-filter-condition" wire:model.live="filterCondition"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua kondisi</option>
                        @foreach ($conditions as $conditionOption)
                            <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="return-filter-state" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Pengembalian</label>
                    <select id="return-filter-state" wire:model.live="filterReturnState"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua unit</option>
                        <option value="belum">Belum dikonfirmasi</option>
                        <option value="sudah">Sudah dikonfirmasi</option>
                    </select>
                </div>
                <div>
                    <label for="return-filter-room" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Ruangan asal</label>
                    <select id="return-filter-room" wire:model.live="filterRoom"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua ruangan</option>
                        @foreach ($filterRooms as $roomOption)
                            <option value="{{ $roomOption['id'] }}">{{ $roomOption['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="resetFilters"
                    class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    Hapus filter
                </button>
                <button type="button" x-on:click="toggleScan()"
                    class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-sm">qr_code_scanner</span> Pindai QR untuk Menemukan Unit
                </button>
            </div>

            <div x-show="scanOpen" x-cloak class="mt-3 rounded-lg border border-sky-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <div class="relative flex min-h-[160px] items-center justify-center overflow-hidden rounded-lg bg-slate-900">
                            <div id="return-qr-reader" class="w-full h-full min-h-[160px]"></div>
                            <span x-show="!running" class="absolute px-3 text-center text-xs text-slate-400 pointer-events-none">Kamera belum aktif</span>
                        </div>
                        <div class="mt-2 flex justify-center gap-2">
                            <button type="button" x-show="!running" x-on:click="startCamera()"
                                class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-brand-700">
                                <span class="material-icons-round text-sm">photo_camera</span> Aktifkan Kamera
                            </button>
                            <button type="button" x-show="running" x-on:click="stopCamera()"
                                class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-red-600 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-red-700">
                                <span class="material-icons-round text-sm">photo_camera_back</span> Matikan Kamera
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="return-scan-code" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Masukkan kode inventaris secara manual</label>
                        <div class="flex gap-2">
                            <input id="return-scan-code" type="text" wire:model="scanCode"
                                wire:keydown.enter.prevent="scanUnitCode"
                                placeholder="Contoh: G1-02-12" autocomplete="off"
                                class="min-w-0 flex-1 rounded-lg border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <button type="button" wire:click="scanUnitCode"
                                class="inline-flex min-h-9 items-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Cari</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($scanMessage !== '')
                <p role="status" class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ $scanMessage }}</p>
            @endif
            @if ($scanError !== '')
                <p role="alert" class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $scanError }}</p>
            @endif
        </div>

        @if ($progress['pending'] > 0 && $pendingLabels->isNotEmpty())
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                <strong>Unit yang perlu ditemukan:</strong>
                {{ $pendingLabels->implode(', ') }}@if ($progress['pending'] > $pendingLabels->count()) dan {{ $progress['pending'] - $pendingLabels->count() }} lainnya @endif
            </div>
        @endif
    </div>

    @if ($visibleUnitCount === 0)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
            Tidak ada unit yang cocok dengan pencarian/filter.
            <button type="button" wire:click="resetFilters" class="font-semibold text-brand-700 underline dark:text-brand-300">Hapus filter</button>
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
<<<<<<< HEAD
                <details class="border border-gray-200 rounded-lg">
                    <summary class="cursor-pointer bg-gray-50 px-4 py-3 flex items-center justify-between">
=======
                <details class="rounded-xl border border-slate-200 dark:border-slate-700" open>
                    <summary class="flex cursor-pointer items-center justify-between rounded-t-xl bg-slate-50 px-4 py-3 dark:bg-slate-800">
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                        <div>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $group['item_name'] }}</span>
                            <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">— {{ $group['total_borrowed'] }} unit dipinjam</span>
                        </div>
<<<<<<< HEAD
                        <div class="text-xs text-gray-600">
                            Total: <strong>{{ $group['total_borrowed'] }}</strong> |
                            Sudah kembali: <strong>{{ $group['already_returned'] + $group['pending_returns'] }}</strong> |
                            Belum: <strong>{{ $group['remaining'] }}</strong> |
                            Status: <strong>{{ $group['status'] }}</strong>
=======
                        <div class="text-xs text-slate-600 dark:text-slate-300">
                            Sudah kembali: <strong>{{ $group['already_returned'] + $group['returned'] }}</strong> / {{ $group['total_borrowed'] }} |
                            Belum: <strong>{{ max(0, $group['total_borrowed'] - ($group['already_returned'] + $group['returned'])) }}</strong>
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                        </div>
                    </div>
                @endif

                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($group['rows'] as $row)
                        @php($index = $row['row_index'])
                        @php($isMismatch = in_array($index, $mismatches, true))
                        @php($unitStatus = $row['returned']
                            ? \App\Enums\ReturnStatus::SUDAH_DIKONFIRMASI
                            : (in_array($index, $selected)
                                ? \App\Enums\ReturnStatus::MENUNGGU_KONFIRMASI
                                : \App\Enums\ReturnStatus::BELUM_DIPERIKSA))
                        <div class="p-4 sm:p-5" id="return-unit-{{ $index }}" wire:key="unit-row-{{ $row['assignment_id'] ?? 'legacy-'.$index }}">
                            <div @class([
                                'rounded-xl border p-3 sm:p-4',
                                'border-orange-300 bg-orange-50/40 dark:border-orange-900 dark:bg-orange-950/20' => $isMismatch,
                                'border-slate-200 dark:border-slate-700' => ! $isMismatch,
                            ]) wire:key="row-{{ $i }}">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        @if (! empty($row['inventory_code']))
                                            <div class="text-sm text-gray-800">{{ $row['item_name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['specification'] ?: 'Spesifikasi tidak tersedia' }}</div>
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

                                    @if (! $row['returned'])
                                        <button type="button" wire:click="confirmOne({{ $index }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white hover:bg-brand-700 disabled:opacity-50">
                                            <span class="material-icons-round text-base">task_alt</span>
                                            Konfirmasi unit ini
                                        </button>
                                    @endif
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
<<<<<<< HEAD
                                        @if (! empty($row['assignment_id']))
                                            <x-input-label value="Pengembalian unit" />
                                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                                <input type="checkbox" value="1" @checked((int) $row['qty_returned'] === 1)
                                                       wire:model.live="rows.{{ $i }}.qty_returned"
                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                Tandai unit sudah dikembalikan
                                            </label>
                                        @else
                                            <x-input-label value="Jumlah Kembali *" />
                                            <input type="number" min="0" max="{{ $row['remaining'] }}" wire:model.live="rows.{{ $i }}.qty_returned"
                                                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                        @endif
=======
                                        <x-input-label value="Jumlah Kembali *" />
                                        <input type="number" min="0" max="{{ $row['remaining'] }}" wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.qty_returned"
                                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                                    </div>

                                    <div>
                                        <x-input-label value="Kondisi Akhir *" />
<<<<<<< HEAD
                                        <select wire:model.live="rows.{{ $i }}.condition_in"
                                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
=======
                                        <select wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.condition_in"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                                            @foreach ($conditions as $c)
                                                <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                            @endforeach
                                        </select>

                                        @if (($row['condition_in'] ?? 'BAIK') !== 'BAIK' && ! $row['returned'])
                                            <div class="mt-2">
<<<<<<< HEAD
                                                <input type="file" wire:model.live="rows.{{ $i }}.damage_photo" accept="image/*"
                                                       class="mt-1 block w-full text-xs text-gray-600 file:me-2 file:py-1.5 file:px-2 file:rounded file:border-0 file:bg-red-50 file:text-red-700">
=======
                                                <input type="file" wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.damage_photo" accept="image/*"
                                                       class="mt-1 block w-full text-xs text-slate-600 file:me-2 file:rounded-lg file:border-0 file:bg-red-50 file:px-2 file:py-1.5 file:text-red-700 dark:text-slate-300">
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <x-input-label value="Dikembalikan ke Ruangan *" />
<<<<<<< HEAD
                                        <select wire:model.live="rows.{{ $i }}.returned_room_id"
                                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 @if($isMismatch) border-orange-400 @endif">
=======
                                        <select wire:model.live="rows.{{ array_search($row, $this->rows, true) }}.returned_room_id"
                                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 @if($isMismatch) border-orange-400 dark:border-orange-500 @endif">
>>>>>>> 79bceedb7571f03e85dfed6f27b690e563b1f5ae
                                            @foreach ($rooms as $room)
                                                <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <x-input-label value="Catatan kondisi / kerusakan (opsional)" />
                                    <textarea rows="2" wire:model.live="rows.{{ $index }}.return_notes" @disabled($row['returned'])
                                        placeholder="Contoh: retak pada rangka, kabel terkelupas, atau catatan pemeriksaan lain"
                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"></textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    {{-- Ringkasan & konfirmasi --}}
    <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="text-sm">
                @if ($totalOutstanding > 0)
                    <span class="text-blue-700 font-semibold">{{ $totalPendingReturn }} unit dipilih untuk dikembalikan; {{ $totalOutstanding }} unit masih belum diselesaikan.</span>
                    @if ($totalShortfall > 0)
                        <span class="block text-red-600 font-semibold mt-1">⚠ Selisih katalog legacy: {{ $totalShortfall }} barang — flag Hilang akan dicatat.</span>
                    @endif
                @else
                    <span class="text-green-700 font-semibold">✓ Semua unit telah dikembalikan — pinjaman akan berstatus Selesai.</span>
                @endif
            </div>
        </div>

        @if ($mismatches !== [])
            <label class="mt-3 flex items-start gap-2 text-sm text-gray-700 bg-orange-50 border border-orange-200 rounded-lg p-3">
                <input type="checkbox" wire:model.live="confirmMismatch" class="mt-0.5 rounded border-orange-400 text-orange-600 focus:ring-orange-500">
                <span>Saya memahami ada barang dikembalikan ke ruangan berbeda dan setuju tercatat sebagai <strong>mutasi otomatis</strong>.</span>
            </label>
            <x-input-error :messages="$errors->get('confirmMismatch')" class="mb-3" />
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

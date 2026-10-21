<div class="space-y-5">
    @if ($loan->status === \App\Enums\LoanStatus::APPROVED)
        <div class="rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800 px-4 py-3 text-sm">
            Peminjaman masih berstatus <strong>Disetujui (belum diambil/diproses)</strong> — pengembalian tetap dapat divalidasi di sini.
        </div>
    @endif

    {{-- Alert ruangan tertukar --}}
    @if ($mismatches !== [])
        <div class="rounded-lg bg-orange-50 border border-orange-300 text-orange-800 px-4 py-3 text-sm flex items-start gap-2"
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

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                <span class="material-icons-round text-amber-600">fact_check</span> Validasi Per Barang
            </h3>
            <span class="text-xs text-gray-400">Peminjam: <strong>{{ $loan->borrower?->name }}</strong> • Kembali {{ $loan->due_date->format('d/m/Y H:i') }}</span>
        </div>

        <div class="space-y-6">
            @foreach ($rows as $i => $row)
                @php($isMismatch = in_array($i, $mismatches, true))
                <div @class([
                    'border rounded-lg p-4',
                    'border-orange-300 bg-orange-50/40' => $isMismatch,
                    'border-gray-200' => ! $isMismatch,
                ]) wire:key="row-{{ $i }}">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-semibold text-gray-800 text-sm">{{ $row['item_name'] }}</div>
                            <div class="text-[11px] font-mono text-gray-400">{{ $row['internal_id'] }} • Ruang asal: {{ $row['room_code'] }}</div>
                        </div>
                        <div class="text-xs text-gray-500">
                            Dipinjam: <strong>{{ $row['borrowed'] }} {{ $row['unit'] }}</strong>
                            @if ($row['already_returned'] > 0)
                                • terkembalikan sebelumnya: {{ $row['already_returned'] }}
                            @endif
                            <span class="block text-[10px] text-gray-400">Kondisi keluar: {{ \App\Enums\Condition::from($row['condition_out'])->label() }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <x-input-label value="Jumlah Kembali *" />
                            <input type="number" min="0" max="{{ $row['remaining'] }}" wire:model.live="rows.{{ $i }}.qty_returned"
                                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <x-input-error :messages="$errors->get('rows.'.$i.'.qty_returned')" class="mt-1" />
                            @php($short = max(0, (int) $row['remaining'] - (int) $row['qty_returned']))
                            @if ($short > 0)
                                <p class="text-[11px] text-red-600 font-semibold mt-1">
                                    ⚠ Selisih {{ $short }} {{ $row['unit'] }} —
                                    dicatat sebagai <strong>HILANG sementara</strong>; dapat dikembalikan menyusul atau ditutup oleh Wakasek
                                </p>
                            @elseif ($row['already_returned'] > 0)
                                <p class="text-[11px] text-gray-400 mt-1">Sisa unit akan kembali seluruhnya.</p>
                            @endif
                        </div>

                        <div>
                            <x-input-label value="Kondisi Akhir *" />
                            <select wire:model.live="rows.{{ $i }}.condition_in"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c->value }}">{{ $c->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('rows.'.$i.'.condition_in')" class="mt-1" />

                            @if ($row['condition_in'] !== 'BAIK')
                                <div class="mt-2">
                                    <x-input-label value="Foto Bukti Kerusakan *" />
                                    <input type="file" wire:model.live="rows.{{ $i }}.damage_photo" accept="image/*"
                                           class="mt-1 block w-full text-xs text-gray-600 file:me-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-red-50 file:text-red-700">
                                    <x-input-error :messages="$errors->get('rows.'.$i.'.damage_photo')" class="mt-1" />
                                    <div wire:loading wire:target="rows.{{ $i }}.damage_photo" class="text-[11px] text-amber-600 mt-1">Mengunggah...</div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <x-input-label value="Dikembalikan ke Ruangan *" />
                            <select wire:model.live="rows.{{ $i }}.returned_room_id"
                                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500 @if($isMismatch) border-orange-400 @endif">
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('rows.'.$i.'.returned_room_id')" class="mt-1" />
                            @if ($isMismatch)
                                <p class="text-[11px] text-orange-700 font-semibold mt-1">⚠ Berbeda dari ruangan asal — jadi mutasi</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Ringkasan & konfirmasi --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
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
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-sm font-bold">
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

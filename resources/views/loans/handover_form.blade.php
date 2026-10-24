<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Serah Terima + Pilih Unit</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>
                <p class="text-sm text-gray-500">Pinjaman #{{ $loan->id }} — {{ $loan->purpose }}</p>
            </div>
            <a href="{{ route('loans.show', $loan) }}" class="text-sm text-gray-500 hover:text-gray-800">← Kembali ke detail</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="max-w-4xl">
        <p class="text-sm text-gray-600 mb-4">
            Serah terima barang ke peminjam dan pilih unit inventaris fisik yang diserahkan.
            Setiap unit yang dipilih akan tercatat kode inventaris, kondisi saat keluar, dan status akan berubah menjadi <strong class="text-blue-700">Sedang Dipinjam</strong>.
        </p>

        <form method="POST" action="{{ route('loans.handover', $loan) }}" id="handover-form">
            @csrf

            <div class="space-y-6">
                @foreach ($availableUnits as $loanItemId => $bucket)
                    @php
                        $item = $bucket['item'];
                        $room = $bucket['room'];
                        $qtyBorrowed = $bucket['qty_borrowed'];
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                            <div>
                                <span class="font-semibold text-gray-800">{{ $item->name }}</span>
                                <span class="text-[11px] font-mono text-gray-400">{{ $item->internal_id }}</span>
                                @if ($item->is_high_value)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">NILAI TINGGI</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $qtyBorrowed }} {{ $item->unit }} • Ruang asal: {{ $room?->code ?? '-' }}
                            </div>
                        </div>

                        @if ($bucket['units']->isEmpty())
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                                Tidak ada unit yang dapat diserahkan untuk barang ini.
                                Bisa karena seluruh unit sedang dipinjam, tidak aktif, atau rusak.
                            </div>
                        @else
                            <div x-data="{ selected: [], max: {{ $qtyBorrowed }} }"
                                 class="rounded-lg border border-gray-200 divide-y divide-gray-100 max-h-[320px] overflow-y-auto">
                                @foreach ($bucket['units'] as $unit)
                                    <label class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer"
                                           :class="selected.length >= max && ! selected.includes('{{ $unit->id }}') ? 'opacity-40 cursor-not-allowed' : ''">
                                        <input type="checkbox"
                                               name="unit_assignments[{{ $loanItemId }}][unit_ids][]"
                                               value="{{ $unit->id }}"
                                               x-model="selected"
                                               :disabled="selected.length >= max && ! selected.includes('{{ $unit->id }}')"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-mono font-semibold text-gray-800 text-sm">{{ $unit->inventory_code }}</div>
                                            <div class="text-[11px] text-gray-500">{{ $unit->locationLabel() }}</div>
                                            <div class="text-[11px] text-gray-400">Unit No. {{ $unit->unit_no }} • {{ $unit->funding_source ? 'Dana: '.$unit->funding_source : '' }}</div>
                                        </div>
                                    </label>
                                @endforeach
                                <div class="p-3 bg-gray-50 text-[11px] text-gray-600 flex items-center justify-between gap-2">
                                    <span>
                                        Dipilih <strong x-text="selected.length"></strong> dari {{ $qtyBorrowed }} unit yang wajib diserahkan.
                                        Unit tambahan otomatis dinonaktifkan setelah batas terpenuhi.
                                    </span>
                                    <span x-show="selected.length === max" class="text-green-700 font-semibold whitespace-nowrap">✓ lengkap</span>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">
                                Pilih tepat {{ $qtyBorrowed }} unit untuk barang ini. Hapus centang untuk mengganti pilihan sebelum menyimpan.
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label value="Catatan serah terima (opsional)" />
                    <textarea name="note" rows="2"
                              class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-amber-500 focus:border-amber-500 text-sm"
                              placeholder="Kondisi saat serah terima, catatan khusus..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">
                        <span class="material-icons-round text-lg">inventory</span>
                        Serahkan Barang + Tandai Unit Dipinjam
                    </button>
                    <a href="{{ route('loans.show', $loan) }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                        Batal
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

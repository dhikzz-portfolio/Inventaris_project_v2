<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Serah Terima Barang</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pinjaman #{{ $loan->id }} — {{ $loan->purpose }}</p>
            </div>
            <a href="{{ route('loans.show', $loan) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali ke detail</a>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="max-w-4xl">
        <p class="mb-4 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm leading-relaxed text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100">
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
                    <section class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                            <div>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $item->name }}</span>
                                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $item->internal_id }}</span>
                                @if ($item->is_high_value)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">NILAI TINGGI</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $qtyBorrowed }} {{ $item->unit }} • Ruang asal: {{ $room?->code ?? '-' }}
                            </div>
                        </div>

                        @if ($bucket['units']->isEmpty())
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Tidak ada unit yang dapat diserahkan untuk barang ini.
                                Bisa karena seluruh unit sedang dipinjam, tidak aktif, atau rusak.
                            </div>
                        @else
                            <div x-data="{ selected: [], max: {{ $qtyBorrowed }} }"
                                 class="max-h-[320px] divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                @foreach ($bucket['units'] as $unit)
                                    <label class="flex cursor-pointer items-center gap-3 p-3 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                                           :class="selected.length >= max && ! selected.includes('{{ $unit->id }}') ? 'opacity-40 cursor-not-allowed' : ''">
                                        <input type="checkbox"
                                               name="unit_assignments[{{ $loanItemId }}][unit_ids][]"
                                               value="{{ $unit->id }}"
                                               x-model="selected"
                                               :disabled="selected.length >= max && ! selected.includes('{{ $unit->id }}')"
                                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-950">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</div>
                                            <div class="text-[11px] text-slate-600 dark:text-slate-300">{{ $unit->locationLabel() }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Unit No. {{ $unit->unit_no }} • {{ $unit->funding_source ? 'Dana: '.$unit->funding_source : '' }}</div>
                                        </div>
                                    </label>
                                @endforeach
                                <div class="flex items-center justify-between gap-2 bg-slate-50 p-3 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <span>
                                        Dipilih <strong x-text="selected.length"></strong> dari {{ $qtyBorrowed }} unit yang wajib diserahkan.
                                        Unit tambahan otomatis dinonaktifkan setelah batas terpenuhi.
                                    </span>
                                    <span x-show="selected.length === max" class="text-green-700 font-semibold whitespace-nowrap">✓ lengkap</span>
                                </div>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Pilih tepat {{ $qtyBorrowed }} unit untuk barang ini. Hapus centang untuk mengganti pilihan sebelum menyimpan.
                            </p>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label value="Catatan serah terima (opsional)" />
                    <textarea name="note" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Kondisi saat serah terima, catatan khusus..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                        <span class="material-icons-round text-lg">inventory</span>
                        Serahkan Barang + Tandai Unit Dipinjam
                    </button>
                    <a href="{{ route('loans.show', $loan) }}"
                       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Mutasi Unit Barang</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pilih satu atau beberapa unit fisik, lalu pindahkan bersama ke ruangan tujuan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    {{-- Filter: 6 kolom pada layar lebar, menumpuk rapi pada layar sempit --}}
    <form method="GET" action="{{ route('movements.index') }}" class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-base text-brand-600">filter_alt</span> Filter Unit
            </h3>
            <a href="{{ route('movements.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Reset filter</a>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Cari kode unit</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="G1-02-12"
                       class="w-full rounded-xl border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Jenis barang</label>
                <select name="catalog" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($catalogs as $catalog)
                        <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Gedung</label>
                <select name="building" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($buildings as $building)
                        <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Lantai</label>
                <select name="floor" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @for ($floor = 1; $floor <= 9; $floor++)
                        <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                <select name="room" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                <select name="condition" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="inline-flex min-h-9 items-center rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500">Terapkan filter</button>
        </div>
    </form>

    <form method="POST" action="{{ route('movements.store') }}" x-data="movementSelection()">
        @csrf

        <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
            {{-- Daftar unit: tinggi dibatasi + header tabel menempel --}}
            <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Unit ({{ $units->total() }})</h3>
                    <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><strong x-text="selected.length"></strong> unit dipilih</span>
                </div>
                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="w-10 px-3 py-2.5"></th>
                                <th class="px-3 py-2.5 text-left">Kode Inventaris</th>
                                <th class="px-3 py-2.5 text-left">Barang</th>
                                <th class="px-3 py-2.5 text-left">Lokasi Asal</th>
                                <th class="px-3 py-2.5 text-left">Kondisi</th>
                                <th class="px-3 py-2.5 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($units as $unit)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                    <td class="px-3 py-2.5">
                                        <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" x-model="selected"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                                    </td>
                                    <td class="px-3 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-medium text-slate-700 dark:text-slate-200">{{ $unit->catalog?->name }}</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $unit->catalog?->category?->name }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $unit->locationLabel() }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada unit yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
            </div>

            {{-- Panel tujuan: menempel saat halaman di-scroll --}}
            <div class="space-y-4 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm lg:sticky lg:top-20 dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">move_to_inbox</span> Tujuan Mutasi
                </h3>

                <div>
                    <x-input-label value="Ringkasan unit terpilih" />
                    <div class="mt-1 min-h-[42px] rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                        <span x-show="selected.length === 0">Belum ada unit dipilih.</span>
                        <span x-show="selected.length > 0"><strong x-text="selected.length"></strong> unit akan dipindahkan. Lokasi asal tiap unit tercantum pada tabel.</span>
                    </div>
                </div>

                <div>
                    <x-input-label for="to_room_id" value="Ruangan Tujuan *" />
                    <select id="to_room_id" name="to_room_id" required class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih ruangan tujuan —</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('to_room_id') == $room->id)>
                                {{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Unit yang sedang dipinjam, sudah dihapus, atau sudah berada di ruangan tujuan akan ditolak.</p>
                </div>

                <div>
                    <x-input-label for="note" value="Catatan mutasi" />
                    <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('note') }}</textarea>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Mutasi menerbitkan kode inventaris baru sesuai lokasi tujuan. Kode lama dipensiunkan dan tetap dapat ditelusuri. Cetak ulang label setelah mutasi.
                </div>

                <button type="submit" :disabled="selected.length === 0"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:disabled:bg-slate-700">
                    <span class="material-icons-round text-lg">swap_horiz</span> Simpan Mutasi
                </button>
            </div>
        </div>
    </form>

    <div class="mt-6 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h3 class="mb-3 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
            <span class="material-icons-round text-brand-600">route</span> Riwayat Mutasi Unit ({{ $movements->total() }})
        </h3>
        <div class="max-h-[45vh] overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2.5 text-left">Unit</th>
                        <th class="px-3 py-2.5 text-left">Kode Lama → Baru</th>
                        <th class="px-3 py-2.5 text-left">Asal → Tujuan</th>
                        <th class="px-3 py-2.5 text-left">Petugas</th>
                        <th class="px-3 py-2.5 text-left">Waktu</th>
                        <th class="px-3 py-2.5 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movements as $movement)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-3 py-2.5">
                                <a href="{{ route('units.show', $movement->unit) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $movement->unit?->catalog?->name }}</a>
                                <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $movement->unit?->inventory_code }}</div>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $movement->old_code }} → {{ $movement->new_code }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $movement->fromRoom?->code }} → {{ $movement->toRoom?->code }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->mover?->name }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->moved_at?->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-slate-400">Belum ada mutasi tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>

    @push('scripts')
    <script>
        function movementSelection() {
            return {
                selected: [],
            };
        }
    </script>
    @endpush
</x-app-layout>

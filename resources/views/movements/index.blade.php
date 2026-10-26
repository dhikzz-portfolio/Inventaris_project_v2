<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mutasi Unit Barang</h2>
            <p class="text-sm text-gray-500">Pilih satu atau beberapa unit fisik, lalu pindahkan bersama ke ruangan tujuan</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('movements.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
        <div class="grid grid-cols-2 md:grid-cols-7 gap-2">
            <div class="col-span-2">
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Cari kode unit</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="G1-02-12"
                       class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase focus:ring-amber-500 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Jenis barang</label>
                <select name="catalog" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach ($catalogs as $catalog)
                        <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Gedung</label>
                <select name="building" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach ($buildings as $building)
                        <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Lantai</label>
                <select name="floor" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @for ($floor = 1; $floor <= 9; $floor++)
                        <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Ruangan</label>
                <select name="room" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 mb-1">Kondisi</label>
                <select name="condition" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">Terapkan filter</button>
            <a href="{{ route('movements.index') }}" class="text-xs text-gray-500 hover:text-gray-800">Reset</a>
        </div>
    </form>

    <form method="POST" action="{{ route('movements.store') }}" x-data="movementSelection()">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 flex-wrap">
                    <h3 class="font-semibold text-gray-800 text-sm">Daftar Unit ({{ $units->total() }})</h3>
                    <span class="text-xs text-gray-500"><strong x-text="selected.length"></strong> unit dipilih</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="w-10 px-3 py-2"></th>
                                <th class="text-left px-3 py-2">Kode Inventaris</th>
                                <th class="text-left px-3 py-2">Barang</th>
                                <th class="text-left px-3 py-2">Lokasi Asal</th>
                                <th class="text-left px-3 py-2">Kondisi</th>
                                <th class="text-left px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($units as $unit)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" x-model="selected"
                                               class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    </td>
                                    <td class="px-3 py-2 font-mono font-semibold text-gray-800">{{ $unit->inventory_code }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-700">{{ $unit->catalog?->name }}</div>
                                        <div class="text-[10px] text-gray-400">{{ $unit->catalog?->category?->name }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $unit->locationLabel() }}</td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $unit->condition->label() }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-10 text-center text-gray-400 text-sm">Tidak ada unit yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $units->links() }}</div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4 self-start">
                <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-1.5">
                    <span class="material-icons-round text-amber-600">move_to_inbox</span> Tujuan Mutasi
                </h3>

                <div>
                    <x-input-label value="Ringkasan unit terpilih" />
                    <div class="mt-1 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 min-h-[42px]">
                        <span x-show="selected.length === 0">Belum ada unit dipilih.</span>
                        <span x-show="selected.length > 0"><strong x-text="selected.length"></strong> unit akan dipindahkan. Lokasi asal tiap unit tercantum pada tabel.</span>
                    </div>
                </div>

                <div>
                    <x-input-label for="to_room_id" value="Ruangan Tujuan *" />
                    <select id="to_room_id" name="to_room_id" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <option value="">— pilih ruangan tujuan —</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('to_room_id') == $room->id)>
                                {{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Unit yang sedang dipinjam, sudah dihapus, atau sudah berada di ruangan tujuan akan ditolak.</p>
                </div>

                <div>
                    <x-input-label for="note" value="Catatan mutasi" />
                    <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('note') }}</textarea>
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-[11px] text-amber-900">
                    Mutasi menerbitkan kode inventaris baru sesuai lokasi tujuan. Kode lama dipensiunkan dan tetap dapat ditelusuri. Cetak ulang label setelah mutasi.
                </div>

                <button type="submit" :disabled="selected.length === 0"
                        class="w-full inline-flex justify-center items-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-bold px-4 py-2.5">
                    <span class="material-icons-round text-lg">swap_horiz</span> Simpan Mutasi
                </button>
            </div>
        </div>
    </form>

    <div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
            <span class="material-icons-round text-amber-600">route</span> Riwayat Mutasi Unit ({{ $movements->total() }})
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-400 border-b border-gray-100">
                    <tr>
                        <th class="text-left py-2">Unit</th>
                        <th class="text-left py-2">Kode Lama → Baru</th>
                        <th class="text-left py-2">Asal → Tujuan</th>
                        <th class="text-left py-2">Petugas</th>
                        <th class="text-left py-2">Waktu</th>
                        <th class="text-left py-2">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        <tr class="hover:bg-gray-50">
                            <td class="py-2.5">
                                <a href="{{ route('units.show', $movement->unit) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $movement->unit?->catalog?->name }}</a>
                                <div class="text-[11px] font-mono text-gray-400">{{ $movement->unit?->inventory_code }}</div>
                            </td>
                            <td class="py-2.5 text-xs font-mono text-gray-600">{{ $movement->old_code }} → {{ $movement->new_code }}</td>
                            <td class="py-2.5 text-xs text-gray-600">{{ $movement->fromRoom?->code }} → {{ $movement->toRoom?->code }}</td>
                            <td class="py-2.5 text-xs text-gray-500">{{ $movement->mover?->name }}</td>
                            <td class="py-2.5 text-xs text-gray-500">{{ $movement->moved_at?->format('d M Y H:i') }}</td>
                            <td class="py-2.5 text-xs text-gray-400">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">Belum ada mutasi tercatat.</td></tr>
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

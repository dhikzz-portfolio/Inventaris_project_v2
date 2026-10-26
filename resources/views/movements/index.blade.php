<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pelacakan Lokasi Aset (Mutasi)</h2>
            <p class="text-sm text-gray-500">Perpindahan antar ruangan tercatat otomatis beserta pembaruan ID Internal</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-4 flex items-center gap-1.5">
                <span class="material-icons-round text-blue-600">move_to_inbox</span> Pindahkan Barang
            </h3>
            <form method="POST" action="{{ route('movements.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label value="Barang *" />
                    <select name="item_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                        <option value="">— pilih barang —</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                {{ $item->name }} — {{ $item->internal_id }} ({{ $item->room?->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Ruangan Tujuan *" />
                    <select name="to_room_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                        <option value="">— pilih ruangan tujuan —</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected(old('to_room_id') == $room->id)>
                                {{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Catatan" />
                    <textarea name="note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('note') }}</textarea>
                </div>
                <x-primary-button class="bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Catat Mutasi</x-primary-button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3 flex items-center gap-1.5">
                <span class="material-icons-round text-blue-600">route</span> Riwayat Mutasi ({{ $movements->total() }})
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-400 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-2">Barang</th>
                            <th class="text-left py-2">Asal → Tujuan</th>
                            <th class="text-left py-2">Petugas</th>
                            <th class="text-left py-2">Tanggal</th>
                            <th class="text-left py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movements as $m)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5">
                                    <a href="{{ route('items.show', $m->item) }}" class="font-medium text-gray-800 hover:text-blue-700">{{ $m->item?->name }}</a>
                                    <div class="text-[11px] font-mono text-gray-400">{{ $m->item?->internal_id }}</div>
                                </td>
                                <td class="py-2.5">
                                    <span class="inline-flex items-center gap-1 text-xs">
                                        <span class="font-semibold text-gray-700">{{ $m->fromRoom?->code }}</span>
                                        <span class="material-icons-round text-sm text-gray-400">arrow_forward</span>
                                        <span class="font-semibold text-gray-700">{{ $m->toRoom?->code }}</span>
                                    </span>
                                    @if ($m->quantity > 1)
                                        <div class="text-[11px] text-gray-400">{{ $m->quantity }} unit</div>
                                    @endif
                                </td>
                                <td class="py-2.5 text-xs text-gray-500">{{ $m->mover?->name }}</td>
                                <td class="py-2.5 text-xs text-gray-500">{{ $m->moved_at?->format('d M Y H:i') }}</td>
                                <td class="py-2.5 text-xs text-gray-400">{{ $m->note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">Belum ada mutasi tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $movements->links() }}</div>
        </div>
    </div>
</x-app-layout>

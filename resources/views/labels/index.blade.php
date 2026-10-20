<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cetak Label Inventaris (Stiker)</h2>
                <p class="text-sm text-gray-500">Desain memanjang: logo instansi • kode lokasi • kode klasifikasi + register • QR Code</p>
            </div>
            <a href="{{ route('scan') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                <span class="material-icons-round text-lg">qr_code_scanner</span> Scan QR
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
        <form method="GET" action="{{ route('labels.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select name="room" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="">Semua ruangan</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected(request('room') == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                @endforeach
            </select>
            <select name="category" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Terapkan Filter</button>
        </form>
    </div>

    <form method="POST" action="{{ route('labels.print') }}" target="_blank">
        @csrf
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" id="select-all-labels" onclick="toggleAllLabels(this)"
                           class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    Pilih semua ({{ $items->count() }} barang)
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs text-gray-500" id="label-count">0 label dipilih</span>
                    <button class="grow sm:grow-0 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 sm:py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">
                        <span class="material-icons-round text-lg">print</span> Cetak Massal (PDF)
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0">
                        <tr>
                            <th class="px-4 py-2.5 w-10"></th>
                            <th class="text-left px-4 py-2.5">Barang</th>
                            <th class="text-left px-4 py-2.5">ID Internal</th>
                            <th class="text-left px-4 py-2.5">Standar Aset (Baris 1)</th>
                            <th class="text-left px-4 py-2.5">Kode + Register</th>
                            <th class="text-left px-4 py-2.5">Ruangan</th>
                            <th class="text-center px-4 py-2.5">Cetak Terakhir</th>
                            <th class="text-center px-4 py-2.5">Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                           onchange="updateLabelCount()"
                                           @checked(in_array($item->id, old('item_ids', [])))>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('items.show', $item) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $item->name }}</a>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $item->internal_id }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $item->location_code }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $item->asset_code }}.{{ $item->register_no }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $item->room?->code }} — {{ $item->room?->name }}</td>
                                <td class="px-4 py-2 text-center text-xs text-gray-400">{{ $item->last_label_printed_at?->format('d/m/Y') ?: '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <a href="{{ route('items.label', $item) }}" target="_blank" title="Cetak label satuan / re-print"
                                       class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-amber-50 text-gray-500 hover:text-amber-700">
                                        <span class="material-icons-round text-lg">print</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Tidak ada barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-[11px] text-gray-400">
            Tombol cetak satuan juga tersedia di halaman detail barang (re-print untuk stiker rusak/pudar).
            @if ($errors->any()) <span class="text-red-600">{{ $errors->first() }}</span> @endif
        </p>
    </form>
    @push('scripts')
    <script>
        function updateLabelCount() {
            const checked = document.querySelectorAll('input[name="item_ids[]"]:checked').length;
            const el = document.getElementById('label-count');
            if (el) el.textContent = checked + ' label dipilih';
        }

        function toggleAllLabels(source) {
            document.querySelectorAll('input[name="item_ids[]"]').forEach((cb) => { cb.checked = source.checked; });
            updateLabelCount();
        }

        document.addEventListener('DOMContentLoaded', updateLabelCount);
    </script>
    @endpush
</x-app-layout>

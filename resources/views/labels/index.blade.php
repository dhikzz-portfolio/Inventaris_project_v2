<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cetak Label Unit Inventaris</h2>
                <p class="text-sm text-gray-500">Satu stiker untuk satu unit fisik dengan kode inventaris <span class="font-mono">G1-02-12</span>. Pilih unit per baris, atau cetak semua unit yang sesuai filter.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('catalogs.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">
                    <span class="material-icons-round text-lg">inventory_2</span> Katalog
                </a>
                <a href="{{ route('scan') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">
                    <span class="material-icons-round text-lg">qr_code_scanner</span> Scan QR
                </a>
            </div>
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
        <form method="GET" action="{{ route('labels.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari kode unit (G1-02-12)"
                   class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
            <select name="catalog" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="">Semua katalog</option>
                @foreach ($catalogs as $catalogId => $catalogName)
                    <option value="{{ $catalogId }}" @selected(request('catalog') == $catalogId)>{{ $catalogName }}</option>
                @endforeach
            </select>
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
            <select name="condition" class="rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="">Semua kondisi</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}" @selected(request('condition') == $conditionOption->value)>{{ $conditionOption->label() }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <select name="status" class="grow rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected(request('status') == $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2">Filter</button>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('labels.print') }}" target="_blank">
        @csrf
        @foreach ($filters as $filterKey => $filterValue)
            @if ($filterValue !== '')
                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
            @endif
        @endforeach

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" id="select-all-labels" onclick="toggleAllLabels(this)"
                           class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    Pilih semua di halaman ini
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" name="select_all" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                               title="Cetak semua unit yang sesuai filter, termasuk halaman lain">
                        Cetak semua yang sesuai filter ({{ $units->total() }} unit)
                    </label>
                    <span class="text-xs text-gray-500" id="label-count">0 unit dipilih</span>
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
                            <th class="text-left px-4 py-2.5">Kode Inventaris</th>
                            <th class="text-left px-4 py-2.5">Nama Barang</th>
                            <th class="text-left px-4 py-2.5">Lokasi</th>
                            <th class="text-left px-4 py-2.5">Kondisi</th>
                            <th class="text-left px-4 py-2.5">Status</th>
                            <th class="text-center px-4 py-2.5">Cetak Terakhir</th>
                            <th class="text-center px-4 py-2.5">Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($units as $unit)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                           onchange="updateLabelCount()"
                                           @checked(in_array($unit->id, old('unit_ids', [])))>
                                </td>
                                <td class="px-4 py-2 font-mono font-semibold text-gray-800">{{ $unit->inventory_code }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('units.show', $unit) }}" class="font-medium text-gray-800 hover:text-amber-700">{{ $unit->catalog?->name }}</a>
                                    <div class="text-[11px] text-gray-400">{{ $unit->catalog?->category?->name }}</div>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                    <div class="text-gray-400">{{ $unit->room?->name }}</div>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800') }}">
                                        {{ $unit->condition->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                </td>
                                <td class="px-4 py-2 text-center text-xs text-gray-400">{{ $unit->last_label_printed_at?->format('d/m/Y') ?: '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <a href="{{ route('units.label', $unit) }}" target="_blank" title="Cetak label satuan / re-print"
                                       class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-amber-50 text-gray-500 hover:text-amber-700">
                                        <span class="material-icons-round text-lg">print</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Tidak ada unit yang sesuai filter dalam cakupan akses Anda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $units->links() }}</div>
            @endif
        </div>

        <p class="mt-3 text-[11px] text-gray-400">
            Daftar ini dibatasi cakupan akses Anda. Centang "Cetak semua yang sesuai filter" untuk mencetak unit di seluruh halaman sekaligus.
            @if ($errors->any()) <span class="text-red-600">{{ $errors->first() }}</span> @endif
        </p>
    </form>
    @push('scripts')
    <script>
        function updateLabelCount() {
            const checked = document.querySelectorAll('input[name="unit_ids[]"]:checked').length;
            const el = document.getElementById('label-count');
            if (el) el.textContent = checked + ' unit dipilih';
        }

        function toggleAllLabels(source) {
            document.querySelectorAll('input[name="unit_ids[]"]').forEach((cb) => { cb.checked = source.checked; });
            updateLabelCount();
        }

        document.addEventListener('DOMContentLoaded', updateLabelCount);
    </script>
    @endpush
</x-app-layout>

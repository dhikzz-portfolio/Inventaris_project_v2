<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Cetak Label Barang</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih barang yang ingin dicetak labelnya atau cetak semua hasil filter.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('catalogs.index') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <span class="material-icons-round text-lg">inventory_2</span> Katalog
                </a>
                <a href="{{ route('scan') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg">qr_code_scanner</span> Scan QR
                </a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Filter --}}
    <div class="mb-3 rounded-2xl border border-sky-100 bg-white px-3 py-2.5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" action="{{ route('labels.index') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari kode unit (G1-02-12)"
                   class="min-h-9 rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            <select name="catalog" class="min-h-9 rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua katalog</option>
                @foreach ($catalogs as $catalogId => $catalogName)
                    <option value="{{ $catalogId }}" @selected(request('catalog') == $catalogId)>{{ $catalogName }}</option>
                @endforeach
            </select>
            <select name="room" class="min-h-9 rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua ruangan</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected(request('room') == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                @endforeach
            </select>
            <select name="category" class="min-h-9 rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="condition" class="min-h-9 rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <option value="">Semua kondisi</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}" @selected(request('condition') == $conditionOption->value)>{{ $conditionOption->label() }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2 xl:col-span-2">
                <select name="status" class="min-h-9 grow rounded-lg border-slate-300 bg-white py-1.5 text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected(request('status') == $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
                <button class="inline-flex min-h-9 shrink-0 items-center justify-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <span class="material-icons-round text-base" aria-hidden="true">filter_alt</span> Terapkan
                </button>
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

        <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <input type="checkbox" id="select-all-labels" onclick="toggleAllLabels(this)"
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950">
                    Pilih semua di halaman ini
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="select_all" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950"
                               title="Cetak semua unit yang sesuai filter, termasuk halaman lain">
                        Cetak semua yang sesuai filter ({{ $units->total() }} unit)
                    </label>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300" id="label-count">0 unit dipilih</span>
                    <button class="inline-flex min-h-10 grow items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:grow-0">
                        <span class="material-icons-round text-lg">print</span> Cetak Massal (PDF)
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2.5 w-10"></th>
                            <th class="text-right px-4 py-2.5">Kode Inventaris</th>
                            <th class="text-left px-4 py-2.5">Nama Barang</th>
                            <th class="text-left px-4 py-2.5">Lokasi</th>
                            <th class="text-left px-4 py-2.5">Kondisi</th>
                            <th class="text-left px-4 py-2.5">Status</th>
                            <th class="text-center px-4 py-2.5">Cetak Terakhir</th>
                            <th class="text-center px-4 py-2.5">Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($units as $unit)
                            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950"
                                           onchange="updateLabelCount()"
                                           @checked(in_array($unit->id, old('unit_ids', [])))>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('units.show', $unit) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $unit->catalog?->name }}</a>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $unit->catalog?->category?->name }}</div>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500 dark:text-slate-400">
                                    Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                    <div class="text-slate-400 dark:text-slate-500">{{ $unit->room?->name }}</div>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300') }}">
                                        {{ $unit->condition->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                </td>
                                <td class="px-4 py-2 text-center text-xs text-slate-400 dark:text-slate-500">{{ $unit->last_label_printed_at?->format('d/m/Y') ?: '—' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <a href="{{ route('units.label', $unit) }}" target="_blank" title="Cetak label satuan / re-print"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-brand-50 hover:text-brand-700 dark:text-slate-400 dark:hover:bg-brand-900/40 dark:hover:text-brand-300">
                                        <span class="material-icons-round text-lg">print</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">Tidak ada unit yang sesuai filter dalam cakupan akses Anda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
            @endif
        </div>

        <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
            Daftar ini dibatasi cakupan akses Anda. Centang "Cetak semua yang sesuai filter" untuk mencetak unit di seluruh halaman sekaligus.
            @if ($errors->any()) <span class="text-red-600 dark:text-red-400">{{ $errors->first() }}</span> @endif
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

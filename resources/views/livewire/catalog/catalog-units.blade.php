<div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="text-xs text-gray-500">Total unit</div>
            <div class="text-2xl font-bold text-gray-800">{{ $totalUnits }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="text-xs text-gray-500">Tersedia (aktif &amp; baik)</div>
            <div class="text-2xl font-bold text-green-700">{{ $availableUnits }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="text-xs text-gray-500">Katalog</div>
            <div class="text-sm font-semibold text-gray-800 mt-1">{{ $catalog->name }}</div>
            <div class="text-xs text-gray-400">{{ $catalog->category?->name }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="relative col-span-full lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">qr_code_2</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari kode inventaris (G1-02-12, G1-02, G1)"
                       class="w-full pl-10 pr-3 py-2.5 rounded-lg text-sm {{ $codeError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-amber-500 focus:border-amber-500' }}">
            </div>

            <select wire:model.live="building" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Gedung</option>
                @foreach ($buildings as $letter)
                    <option value="{{ $letter }}">Gedung {{ $letter }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Lantai</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor }}">Lantai {{ $floor }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">
                        {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="condition" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Kondisi</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">Semua Status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>

            <input type="number" wire:model.live.debounce.400ms="year" min="1990" max="{{ now()->year + 1 }}"
                   placeholder="Tahun perolehan"
                   class="w-full rounded-lg border-gray-300 text-sm">
        </div>

        @if ($codeError)
            <p class="mt-2 text-xs text-red-600 flex items-center gap-1">
                <span class="material-icons-round text-sm">error_outline</span> {{ $codeError }}
            </p>
        @endif

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
            <span>{{ $units->total() }} unit sesuai filter</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex items-center gap-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-1">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                </button>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Kode Inventaris</th>
                        <th class="text-left px-4 py-3">Lokasi</th>
                        <th class="text-left px-4 py-3">Kondisi</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Perolehan</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" wire:loading.class="opacity-60">
                    @forelse ($units as $unit)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono font-semibold text-gray-800">{{ $unit->inventory_code }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                <div class="text-gray-400">{{ $unit->room?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800') }}">
                                    {{ $unit->condition->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $unit->acquisition_year ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('units.show', $unit) }}" class="text-xs font-semibold text-amber-700 hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-gray-400">
                                <span class="material-icons-round text-5xl">inventory_2</span>
                                <p class="mt-2 text-sm">
                                    @if ($search !== '')
                                        Tidak ada unit dengan kode <span class="font-mono">{{ $search }}</span> pada katalog ini.
                                    @else
                                        Belum ada unit yang cocok dengan filter.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($units->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $units->links() }}</div>
        @endif
    </div>
</div>

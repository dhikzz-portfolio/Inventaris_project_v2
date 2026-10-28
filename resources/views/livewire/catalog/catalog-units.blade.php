<div>
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total unit</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $totalUnits }}</div>
        </div>
        <div class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm dark:border-green-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Tersedia (aktif &amp; baik)</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-300">{{ $availableUnits }}</div>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Katalog</div>
            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $catalog->name }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $catalog->category?->name }}</div>
        </div>
    </div>

    <div class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative col-span-full lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">qr_code_2</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari kode inventaris (G1-02-12, G1-02, G1)"
                       aria-label="Cari kode inventaris"
                       class="w-full rounded-xl border py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-2 dark:bg-slate-950 dark:text-slate-100 {{ $codeError ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20 dark:border-red-700' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-600' }}">
            </div>

            <select wire:model.live="building" aria-label="Filter gedung" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Gedung</option>
                @foreach ($buildings as $buildingOption)
                    <option value="{{ $buildingOption['code'] }}">{{ $buildingOption['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" aria-label="Filter lantai" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Lantai</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor }}">Lantai {{ $floor }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" aria-label="Filter ruangan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">
                        {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="condition" aria-label="Filter kondisi unit" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Kondisi</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" aria-label="Filter status unit" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua Status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>

            <input type="number" wire:model.live.debounce.400ms="year" min="1990" max="{{ now()->year + 1 }}"
                   placeholder="Tahun perolehan"
                   aria-label="Filter tahun perolehan"
                   class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
        </div>

        @if ($codeError)
            <p class="mt-2 text-xs text-red-600 flex items-center gap-1">
                <span class="material-icons-round text-sm">error_outline</span> {{ $codeError }}
            </p>
        @endif

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $units->total() }} unit sesuai filter</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <p class="px-4 pt-3 text-xs text-slate-500 dark:text-slate-400 sm:hidden">
            Geser tabel ke samping untuk melihat semua informasi unit.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="text-left px-4 py-3">Kode Inventaris</th>
                        <th class="text-left px-4 py-3">Lokasi</th>
                        <th class="text-left px-4 py-3">Kondisi</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Perolehan</th>
                        <th class="text-right px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800" wire:loading.class="opacity-60">
                    @forelse ($units as $unit)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                <div class="text-slate-400 dark:text-slate-500">{{ $unit->room?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800') }}">
                                    {{ $unit->condition->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $unit->acquisition_year ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 hover:underline dark:text-blue-300 dark:hover:bg-slate-800">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-slate-400 dark:text-slate-500">
                                <span class="material-icons-round text-5xl text-slate-300 dark:text-slate-600">inventory_2</span>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
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
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
        @endif
    </div>
</div>

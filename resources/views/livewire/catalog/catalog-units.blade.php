<div>
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah unit</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $totalUnits }}</div>
        </div>
        <div class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm dark:border-green-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Unit tersedia</div>
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
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari kode inventaris"
                       aria-label="Cari kode inventaris"
                       class="w-full rounded-xl border py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-2 dark:bg-slate-950 dark:text-slate-100 {{ $codeError ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20 dark:border-red-700' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20 dark:border-slate-600' }}">
            </div>

            <select wire:model.live="building" aria-label="Filter gedung" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua gedung</option>
                @foreach ($buildings as $buildingOption)
                    <option value="{{ $buildingOption['code'] }}">{{ $buildingOption['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" aria-label="Filter lantai" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua lantai</option>
                @foreach ($floors as $floor)
                    <option value="{{ $floor }}">Lantai {{ $floor }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" aria-label="Filter ruangan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">
                        {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}
                    </option>
                @endforeach
            </select>

            <select wire:model.live="condition" aria-label="Filter kondisi unit" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua kondisi</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" aria-label="Filter status unit" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>

            <input type="number" wire:model.live.debounce.400ms="year" min="1990" max="{{ now()->year + 1 }}"
                   placeholder="Tahun perolehan"
                   aria-label="Filter tahun perolehan"
                   class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
        </div>

        @if ($codeError)
            <p class="mt-2 flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                <span class="material-icons-round text-sm">error_outline</span> {{ $codeError }}
            </p>
        @endif

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $units->total() }} unit ditemukan</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="space-y-3 p-3 lg:hidden" wire:loading.class="opacity-60">
            @forelse ($units as $unit)
                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kode inventaris</p>
                            <p class="font-mono text-xs font-semibold text-right text-slate-800 dark:text-slate-100 whitespace-nowrap break-all">{{ $unit->inventory_code }}</p>
                        </div>
                        <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-brand-300 dark:hover:bg-slate-800">
                            Detail <span aria-hidden="true">→</span>
                        </a>
                    </div>

                    <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Lokasi</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300">
                            Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                        </p>
                        @if ($unit->room?->name)
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $unit->room->name }}</p>
                        @endif
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <div class="min-w-0">
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kondisi</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300') }}">
                                    {{ $unit->condition->label() }}
                                </span>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Perolehan</dt>
                            <dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $unit->acquisition_year ?: '—' }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="px-3 py-10 text-center text-slate-400 dark:text-slate-500">
                    <span class="material-icons-round text-5xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        @if ($search !== '')
                            Tidak ada unit dengan kode <span class="font-mono">{{ $search }}</span> di katalog ini.
                        @else
                            Belum ada unit yang cocok dengan filter.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="text-left px-4 py-3">Kode inventaris</th>
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
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                <div class="text-slate-400 dark:text-slate-500">{{ $unit->room?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : ($unit->condition === \App\Enums\Condition::RUSAK_RINGAN ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300') }}">
                                    {{ $unit->condition->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $unit->acquisition_year ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-slate-400 dark:text-slate-500">
                                <span class="material-icons-round text-5xl text-slate-300 dark:text-slate-600">inventory_2</span>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($search !== '')
                                        Tidak ada unit dengan kode <span class="font-mono">{{ $search }}</span> di katalog ini.
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

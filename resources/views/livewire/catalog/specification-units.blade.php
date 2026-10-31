<div>
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah unit</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $totalUnits }}</div>
        </div>
        <div class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm dark:border-green-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Tersedia</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-green-700 dark:text-green-300">{{ $availableUnits }}</div>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-white p-4 shadow-sm dark:border-amber-900/60 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Rusak</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ $damagedUnits }}</div>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Spesifikasi</div>
            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $specification->brand ?: 'Tanpa Merek' }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $specification->model_type ?: 'Tidak Ditentukan' }}</div>
        </div>
    </div>

    <div class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari kode inventaris atau nama barang"
                       aria-label="Cari kode inventaris atau nama barang"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>

            <select wire:model.live="building" aria-label="Filter gedung" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua gedung</option>
                @foreach ($buildings as $buildingOption)
                    <option value="{{ $buildingOption['code'] }}">{{ $buildingOption['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="floor" aria-label="Filter lantai" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua lantai</option>
                @foreach ($floors as $floorOption)
                    <option value="{{ $floorOption }}">Lantai {{ $floorOption }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" aria-label="Filter ruangan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">Gedung {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}</option>
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
                   placeholder="Tahun pengadaan" aria-label="Filter tahun pengadaan"
                   class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
        </div>

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
                            <p class="break-all font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</p>
                        </div>
                        <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">Detail →</a>
                    </div>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                        Gedung {{ $unit->room?->building_letter }}, Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $unit->room?->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $unit->condition->label() }}</span>
                        <span class="rounded-full px-2 py-0.5 {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Perolehan {{ $unit->acquisition_year ?: '—' }}</span>
                    </div>
                </article>
            @empty
                <p class="px-3 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada unit yang cocok dengan pencarian atau filter.</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">No.</th>
                        <th class="px-4 py-3 text-left">Kode inventaris</th>
                        <th class="px-4 py-3 text-left">Gedung</th>
                        <th class="px-4 py-3 text-left">Ruangan</th>
                        <th class="px-4 py-3 text-left">Kondisi</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Pengadaan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800" wire:loading.class="opacity-60">
                    @forelse ($units as $index => $unit)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $units->firstItem() + $index }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">Gedung {{ $unit->room?->building_letter }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                Lantai {{ $unit->room?->floor }}, Ruang {{ str_pad((string) $unit->room?->room_no, 2, '0', STR_PAD_LEFT) }}
                                <div class="text-slate-400 dark:text-slate-500">{{ $unit->room?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $unit->condition->label() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $unit->acquisition_year ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada unit yang cocok dengan pencarian atau filter.</td>
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

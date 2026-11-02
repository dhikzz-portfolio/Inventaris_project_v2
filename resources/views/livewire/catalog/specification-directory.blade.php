<div>
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative lg:col-span-2">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.350ms="search"
                       placeholder="Cari merek atau tipe/model"
                       aria-label="Cari merek atau tipe model"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>

            <select wire:model.live="brand" aria-label="Filter merek" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua merek</option>
                @foreach ($brands as $brandOption)
                    <option value="{{ $brandOption }}">{{ $brandOption }}</option>
                @endforeach
            </select>

            <input type="text" wire:model.live.debounce.350ms="model" placeholder="Filter tipe/model" aria-label="Filter tipe model"
                   class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">

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

            <select wire:model.live="building" aria-label="Filter gedung" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua gedung</option>
                @foreach ($buildings as $buildingOption)
                    <option value="{{ $buildingOption['code'] }}">{{ $buildingOption['name'] }}</option>
                @endforeach
            </select>

            <select wire:model.live="room" aria-label="Filter ruangan" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                <option value="">Semua ruangan</option>
                @foreach ($rooms as $roomOption)
                    <option value="{{ $roomOption->id }}">Gedung {{ $roomOption->building_letter }}{{ $roomOption->floor }}-{{ str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT) }} — {{ $roomOption->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $specifications->total() }} spesifikasi ditemukan</span>
            @if ($activeFilters > 0)
                <button wire:click="resetFilters" class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Merek</th>
                        <th class="px-4 py-3 text-left">Tipe / model</th>
                        <th class="px-4 py-3 text-right">Jumlah unit</th>
                        <th class="px-4 py-3 text-right">Tersedia</th>
                        <th class="px-4 py-3 text-right">Rusak</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($specifications as $specification)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ $specification->brand ?: 'Tanpa Merek' }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $specification->model_type ?: 'Tidak Ditentukan' }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $specification->total_units }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-green-700 dark:text-green-300">{{ $specification->available_units }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-amber-700 dark:text-amber-300">{{ $specification->damaged_units }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('specifications.show', $specification) }}" class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:underline dark:text-brand-300 dark:hover:bg-slate-800">Lihat unit →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                Belum ada spesifikasi/unit yang cocok dengan pencarian atau filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($specifications->hasPages())
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $specifications->links() }}</div>
        @endif
    </div>
</div>

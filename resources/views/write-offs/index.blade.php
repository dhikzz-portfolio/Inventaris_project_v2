@php
    $initialMode = old('mode', 'UNIT');
    $initialCatalog = old('catalog_id', '');
    $activeFilterCount = collect($filters)->filter(fn ($value) => $value !== '')->count();
    $activeAdditionalFilterCount = collect($filters)->except('search')->filter(fn ($value) => $value !== '')->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Inventaris Keluar</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Ajukan penghapusan unit tertentu atau seluruh unit dalam satu katalog.</p>
            </div>
            @if (auth()->user()->isApproverLevel2())
                <a href="{{ route('write-offs.pending') }}" class="inline-flex min-h-10 items-center justify-center gap-1 self-start rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:self-auto dark:focus:ring-offset-slate-900">
                    Permintaan untuk disetujui
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="min-w-0" x-data="writeOffForm({
        mode: @js($initialMode),
        catalogId: @js($initialCatalog),
        summaries: @js($catalogSummaries),
        filteredTotal: {{ $units->total() }},
    })" x-cloak>

        <form method="GET" action="{{ route('write-offs.index') }}"
              x-data="{ filterOpen: {{ $activeAdditionalFilterCount > 0 ? 'true' : 'false' }} }"
              onchange="this.requestSubmit()"
              class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Pilih Unit Inventaris Keluar</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Cari unit atau gunakan filter lanjutan untuk mempersempit daftar.</p>
            </div>
            <div class="flex items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="write-off-search" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Cari kode / nama barang</label>
                    <div class="relative">
                        <span class="material-icons-round pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400" aria-hidden="true">search</span>
                        <input id="write-off-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="mis. G1-02-12 atau Kursi"
                               class="min-h-10 w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    </div>
                </div>
                <button type="button" @click="filterOpen = !filterOpen" :aria-expanded="filterOpen.toString()"
                        aria-controls="write-off-unit-filters"
                        class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                    <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                    Filter
                    @if ($activeFilterCount > 0)
                        <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $activeFilterCount }}</span>
                    @endif
                    <span class="material-icons-round text-base text-slate-400 transition-transform" :class="filterOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                </button>
                <button type="submit" class="sr-only">Cari unit</button>
            </div>

            <div id="write-off-unit-filters" x-show="filterOpen" x-cloak x-transition
                 class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kategori</label>
                    <select name="category" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category'] == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Katalog barang</label>
                    <select name="catalog" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($catalogs as $catalog)
                            <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Merek</label>
                    <select name="brand" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand }}" @selected($filters['brand'] === $brand)>{{ $brand }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Tipe / model</label>
                    <select name="model" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($models as $model)
                            <option value="{{ $model }}" @selected($filters['model'] === $model)>{{ $model }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Gedung</label>
                    <select name="building" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Lantai</label>
                    <select name="floor" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @for ($floor = 1; $floor <= 9; $floor++)
                            <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                    <select name="room" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                    <select name="condition" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($units->total(), 0, ',', '.') }} unit sesuai filter</span>
                    <div class="flex justify-end gap-2">
                        @if ($activeFilterCount > 0)
                            <a href="{{ route('write-offs.index') }}"
                               class="inline-flex min-h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('write-offs.store') }}" enctype="multipart/form-data" x-ref="deleteForm">
            @csrf
            <input type="hidden" name="mode" :value="mode">
            {{-- Filter ikut dikirim agar "pilih semua" berlaku untuk seluruh hasil filter --}}
            @foreach ($filters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="select_all" :value="selectAll ? 1 : 0">
            <input type="hidden" name="confirm_delete" :value="confirmDelete ? 1 : ''">
            <input type="hidden" name="confirm_catalog" :value="confirmCatalog ? 1 : ''">
            <template x-for="unitId in selected" :key="unitId">
                <input type="hidden" name="unit_ids[]" :value="unitId">
            </template>

            <div class="grid min-w-0 grid-cols-1 items-start gap-5 xl:grid-cols-3">
                <div class="min-w-0 space-y-4 xl:col-span-2">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Pilih cakupan penghapusan</h3>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-500 dark:text-slate-400">Tentukan unit tertentu atau seluruh unit dalam satu katalog.</p>
                            </div>
                            <div class="grid w-full shrink-0 grid-cols-2 gap-1.5 sm:w-auto" role="group" aria-label="Cakupan penghapusan">
                            <button type="button" @click="mode = 'UNIT'"
                                    :aria-pressed="(mode === 'UNIT').toString()"
                                    :class="mode === 'UNIT' ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300'"
                                    class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-brand-500 sm:px-3">
                                <span class="material-icons-round text-base" aria-hidden="true">checklist</span> Unit
                            </button>
                            <button type="button" @click="mode = 'CATALOG'"
                                    :aria-pressed="(mode === 'CATALOG').toString()"
                                    :class="mode === 'CATALOG' ? 'bg-red-600 text-white' : 'border border-slate-200 bg-white text-slate-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300'"
                                    class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-red-500 sm:px-3">
                                <span class="material-icons-round text-base" aria-hidden="true">delete_sweep</span> Seluruh katalog
                            </button>
                            </div>
                        </div>

                        <div x-show="mode === 'UNIT'" class="mt-2 inline-flex min-h-8 items-center gap-1.5 rounded-lg bg-brand-50 px-2.5 py-1.5 text-[11px] font-medium text-brand-800 dark:bg-brand-900/30 dark:text-brand-200">
                            <span class="material-icons-round text-sm" aria-hidden="true">inventory_2</span>
                            <strong x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit dipilih
                        </div>

                        <div x-show="mode === 'CATALOG'" x-cloak class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            <x-input-label for="catalog_id" value="Katalog yang dihapus seluruh unitnya *" />
                            <select id="catalog_id" name="catalog_id" x-model.number="catalogId"
                                    class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">— pilih katalog —</option>
                                @foreach ($catalogs as $catalog)
                                    <option value="{{ $catalog->id }}" @selected(old('catalog_id') == $catalog->id)>{{ $catalog->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-red-700 dark:text-red-300">Tindakan ini mencakup seluruh unit yang memenuhi syarat dalam katalog, bukan hanya unit di halaman ini.</p>
                        </div>

                        <div x-show="mode === 'CATALOG' && catalogSummary" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <div class="font-bold" x-text="catalogSummary?.name"></div>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div><div class="text-[10px] uppercase tracking-wide opacity-75">Spesifikasi</div><strong class="mt-0.5 block text-base" x-text="catalogSummary?.spec_count"></strong></div>
                                <div><div class="text-[10px] uppercase tracking-wide opacity-75">Total unit</div><strong class="mt-0.5 block text-base" x-text="catalogSummary?.unit_count"></strong></div>
                                <div><div class="text-[10px] uppercase tracking-wide opacity-75">Dapat dihapus</div><strong class="mt-0.5 block text-base" x-text="catalogSummary?.eligible_count"></strong></div>
                                <div><div class="text-[10px] uppercase tracking-wide opacity-75">Lokasi</div><strong class="mt-0.5 block text-base" x-text="catalogSummary?.locations?.length || 0"></strong></div>
                            </div>
                            <div class="mt-3 border-t border-red-200 pt-2 text-[11px] dark:border-red-900" x-show="(catalogSummary?.eligible_count || 0) === 0">Tidak ada unit yang memenuhi syarat pada katalog ini.</div>
                        </div>
                    </section>

                    <div x-show="mode === 'UNIT'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-4 dark:border-slate-800 dark:bg-slate-800/40 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Unit</h3>
                                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ number_format($units->total(), 0, ',', '.') }} unit</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        Menampilkan {{ number_format($units->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($units->lastItem() ?? 0, 0, ',', '.') }} dari {{ number_format($units->total(), 0, ',', '.') }} unit
                                    </p>
                                </div>
                            </div>
                            <label class="inline-flex min-h-10 cursor-pointer items-center gap-2.5 self-start rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-brand-700 dark:hover:bg-slate-800 sm:self-auto">
                                <input type="checkbox" x-model="selectAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                Pilih semua sesuai filter
                            </label>
                        </div>

                        <div class="max-h-[60vh] space-y-3 overflow-y-auto overscroll-contain p-3 2xl:hidden">
                            @forelse ($units as $unit)
                                @php($blocked = $unit->is_on_loan || $unit->has_pending_write_off || $unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                <article class="rounded-xl border border-slate-200 bg-white p-3 transition dark:border-slate-700 dark:bg-slate-900">
                                    <div class="flex items-start gap-3">
                                        <label class="flex min-h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-lg">
                                            <input type="checkbox" value="{{ $unit->id }}" x-model="selected"
                                                   @disabled($blocked)
                                                   aria-label="Pilih unit {{ $unit->inventory_code }}"
                                                   class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-40">
                                        </label>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</span>
                                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                            </div>
                                            <h4 class="mt-1 break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $unit->catalog?->name }}</h4>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $unit->catalog?->category?->name }}</p>
                                        </div>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                                        <div class="min-w-0">
                                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Merek / Tipe</dt>
                                            <dd class="mt-0.5 break-words text-slate-700 dark:text-slate-300">{{ $unit->specification?->displayName() ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Kondisi</dt>
                                            <dd class="mt-0.5"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span></dd>
                                        </div>
                                        <div class="col-span-2 min-w-0">
                                            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Lokasi</dt>
                                            <dd class="mt-0.5 break-words text-slate-700 dark:text-slate-300">{{ $unit->locationLabel() }}</dd>
                                        </div>
                                    </dl>
                                    @if ($unit->is_on_loan || $unit->status === \App\Enums\UnitStatus::DIPINJAM || $unit->has_pending_write_off)
                                        <p class="mt-3 flex items-center gap-1 border-t border-amber-100 pt-2 text-[11px] font-medium text-amber-700 dark:border-amber-900/60 dark:text-amber-300">
                                            <span class="material-icons-round text-sm" aria-hidden="true">info</span>
                                            {{ $unit->has_pending_write_off ? 'Menunggu persetujuan penghapusan' : 'Unit sedang dipinjam dan tidak dapat dipilih' }}
                                        </p>
                                    @endif
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center dark:border-slate-700">
                                    <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                                    <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Tidak ada unit yang cocok dengan filter.</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="hidden max-h-[60vh] overflow-auto overscroll-contain 2xl:block">
                            <table class="w-full min-w-[900px] table-fixed text-sm">
                                <colgroup>
                                    <col class="w-[5%]">
                                    <col class="w-[17%]">
                                    <col class="w-[20%]">
                                    <col class="w-[18%]">
                                    <col class="w-[18%]">
                                    <col class="w-[11%]">
                                    <col class="w-[11%]">
                                </colgroup>
                                <thead class="sticky top-0 z-10 border-b border-slate-200 bg-white text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                    <tr>
                                        <th scope="col" class="px-2 py-3 text-center"></th>
                                        <th scope="col" class="px-3 py-3 text-left">Kode</th>
                                        <th scope="col" class="px-3 py-3 text-left">Barang</th>
                                        <th scope="col" class="px-3 py-3 text-left">Merek / Tipe</th>
                                        <th scope="col" class="px-3 py-3 text-left">Lokasi</th>
                                        <th scope="col" class="px-2 py-3 text-center">Kondisi</th>
                                        <th scope="col" class="px-2 py-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($units as $unit)
                                        @php($blocked = $unit->is_on_loan || $unit->has_pending_write_off || $unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                        <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                                            <td class="px-2 py-4 text-center">
                                                <input type="checkbox" value="{{ $unit->id }}" x-model="selected"
                                                       @disabled($blocked)
                                                       aria-label="Pilih unit {{ $unit->inventory_code }}"
                                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-40">
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                            <td class="px-3 py-4">
                                                <div class="truncate font-semibold text-slate-700 dark:text-slate-200" title="{{ $unit->catalog?->name }}">{{ $unit->catalog?->name }}</div>
                                                <div class="mt-0.5 truncate text-[10px] text-slate-400" title="{{ $unit->catalog?->category?->name }}">{{ $unit->catalog?->category?->name }}</div>
                                            </td>
                                            <td class="truncate px-3 py-4 text-xs text-slate-600 dark:text-slate-300" title="{{ $unit->specification?->displayName() ?: '—' }}">
                                                {{ $unit->specification?->displayName() ?: '—' }}
                                            </td>
                                            <td class="truncate px-3 py-4 text-xs text-slate-500 dark:text-slate-400" title="{{ $unit->locationLabel() }}">{{ $unit->locationLabel() }}</td>
                                            <td class="px-2 py-4 text-center">
                                                <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                            </td>
                                            <td class="px-2 py-4 text-center">
                                                <span class="inline-flex justify-center rounded-lg px-2 py-1 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                                @if ($unit->is_on_loan || $unit->status === \App\Enums\UnitStatus::DIPINJAM)
                                                    <div class="mt-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">sedang dipinjam</div>
                                                @elseif ($unit->has_pending_write_off)
                                                    <div class="mt-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">menunggu persetujuan</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-3 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                            <span class="material-icons-round mb-1 block text-2xl text-slate-300 dark:text-slate-600" aria-hidden="true">inventory_2</span>
                                            Tidak ada unit yang cocok dengan filter.
                                        </td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-100 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="w-full min-w-0 overflow-x-auto [&_nav>div]:min-w-max [&_nav>div]:!flex-nowrap">{{ $units->links() }}</div>
                        </div>
                    </div>
                </div>

                <section class="min-w-0 space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:self-start xl:overflow-y-auto sm:p-5 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300">
                            <span class="material-icons-round text-xl" aria-hidden="true">delete_forever</span>
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Pengajuan Penghapusan</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Lengkapi alasan untuk melanjutkan. Berita acara bersifat opsional.</p>
                        </div>
                    </div>

                    <div class="flex min-h-11 items-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        <span class="material-icons-round text-base text-slate-400" aria-hidden="true">inventory_2</span>
                        <span x-show="mode === 'UNIT'"><strong class="font-bold text-slate-800 dark:text-slate-100" x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit dipilih</span>
                        <span x-show="mode === 'CATALOG' && !catalogSummary">Pilih katalog terlebih dahulu</span>
                        <span class="min-w-0 truncate" x-show="mode === 'CATALOG' && catalogSummary"><strong x-text="catalogSummary?.name"></strong> — <strong x-text="catalogSummary?.eligible_count"></strong> unit dapat dihapus</span>
                    </div>

                    <div class="space-y-1.5">
                        <x-input-label for="reason" value="Alasan Pemusnahan *" />
                        <textarea id="reason" name="reason" rows="3" required
                                  class="block w-full resize-y rounded-xl border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-red-400 focus:ring-2 focus:ring-red-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-red-700 dark:focus:ring-red-950/50"
                                  placeholder="Rusak berat, tidak layak pakai, hilang...">{{ old('reason') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('reason')" />
                    </div>

                    <div class="space-y-1.5" x-data="{ fileName: '' }">
                        <label class="block">
                            <span class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Berita Acara (PDF/Gambar, opsional)</span>
                            <span class="mt-1.5 flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition hover:border-red-300 hover:bg-red-50/60 focus-within:border-red-400 focus-within:ring-2 focus-within:ring-red-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-800 dark:hover:bg-red-950/20 dark:focus-within:border-red-700 dark:focus-within:ring-red-950/50">
                                <input type="file" name="berita_acara" accept=".pdf,.jpg,.jpeg,.png"
                                       x-on:change="fileName = $event.target.files.length ? $event.target.files[0].name : ''"
                                       class="peer sr-only">
                                <span class="material-icons-round text-lg text-red-600 dark:text-red-400" aria-hidden="true">upload_file</span>
                                <span class="min-w-0 flex-1 truncate text-sm" x-text="fileName || 'Pilih berkas berita acara'"></span>
                                <span class="shrink-0 text-xs font-bold text-red-700 dark:text-red-300">Pilih</span>
                            </span>
                        </label>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Opsional · PDF, JPG, atau PNG · Maksimal 4 MB</p>
                        <x-input-error class="mt-1" :messages="$errors->get('berita_acara')" />
                    </div>

                    <button type="button" @click="openConfirm()"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-800 dark:focus:ring-offset-slate-900">
                        <span class="material-icons-round text-lg" aria-hidden="true">delete_forever</span> Tinjau &amp; Ajukan
                    </button>
                </section>
            </div>

            {{-- Modal ringkasan + konfirmasi eksplisit --}}
            <div x-show="modalOpen" x-cloak x-on:keydown.escape.window="modalOpen = false"
                 class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4"
                 role="dialog" aria-modal="true" aria-labelledby="write-off-confirm-title">
                <div class="my-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:max-h-[calc(100dvh-2rem)] sm:p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h3 id="write-off-confirm-title" class="text-lg font-bold text-slate-900 dark:text-slate-100">Konfirmasi penghapusan</h3>

                    <div x-show="mode === 'UNIT'" class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                        <strong x-text="selectAll ? {{ $units->total() }} : selected.length"></strong> unit akan diajukan untuk dihapus.
                        <p class="mt-2 text-xs">Unit yang sedang dipinjam, sudah dihapus, atau sudah menunggu persetujuan lain akan ditolak backend.</p>
                    </div>

                    <div x-show="mode === 'CATALOG'" class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-200">
                        <p class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-800 dark:bg-red-950/40 dark:text-red-200">
                            Seluruh unit dalam katalog ini akan masuk proses penghapusan. Katalog, spesifikasi, dan histori transaksi tetap tersimpan.
                        </p>
                        <div>Katalog: <strong x-text="catalogSummary?.name"></strong></div>
                        <div>Jumlah spesifikasi: <strong x-text="catalogSummary?.spec_count"></strong></div>
                        <div>Total unit: <strong x-text="catalogSummary?.unit_count"></strong> (dapat dihapus: <strong x-text="catalogSummary?.eligible_count"></strong>)</div>
                        <div class="text-xs">
                            <div class="font-semibold">Rincian merek / tipe:</div>
                            <ul class="ms-4 list-disc">
                                <template x-for="spec in (catalogSummary?.specs || [])" :key="spec.label">
                                    <li><span x-text="spec.label"></span> — <span x-text="spec.units"></span> unit</li>
                                </template>
                            </ul>
                        </div>
                        <div class="text-xs">
                            <div class="font-semibold">Lokasi terdampak:</div>
                            <div x-text="(catalogSummary?.locations || []).join(', ') || '—'"></div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end dark:border-slate-800">
                        <button type="button" @click="modalOpen = false"
                                class="min-h-10 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                            Batal
                        </button>
                        <button type="button" @click="submitConfirmed()"
                                class="min-h-10 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Ya, ajukan penghapusan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-1 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Riwayat Inventaris Keluar</h3>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($history->total(), 0, ',', '.') }} pengajuan</span>
            </div>
            @if ($history->isEmpty())
                <div class="px-4 py-10 text-center">
                    <span class="material-icons-round text-3xl text-slate-300 dark:text-slate-600" aria-hidden="true">history</span>
                    <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">Belum ada riwayat inventaris keluar.</p>
                </div>
            @else
            <div class="divide-y divide-slate-100 p-3 dark:divide-slate-800 xl:hidden">
                @foreach ($history as $wo)
                    @php($catalogNames = $wo->units->pluck('catalog.name')->filter()->unique())
                    <article class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($catalogNames->isNotEmpty())
                                    <h4 class="break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $catalogNames->take(2)->implode(', ') }}</h4>
                                    @if ($catalogNames->count() > 2)
                                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">+{{ $catalogNames->count() - 2 }} katalog lain</p>
                                    @endif
                                @elseif ($wo->item)
                                    <a href="{{ route('items.show', $wo->item) }}" class="break-words text-sm font-semibold text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                                    <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $wo->item->internal_id }}</p>
                                @else
                                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pengajuan inventaris keluar</h4>
                                @endif
                            </div>
                            @if ($wo->status === 'PENDING')
                                <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                            @elseif ($wo->status === 'APPROVED')
                                <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold text-red-800 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($wo->reason, 120) }}</p>
                        @if ($wo->decision_note)
                            <p class="mt-1 text-xs text-red-600 dark:text-red-300">Catatan: {{ $wo->decision_note }}</p>
                        @endif
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-2 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <span class="font-semibold uppercase">{{ $wo->mode ?: 'CATALOG' }}</span>
                            <span>{{ $wo->units_count ?: $wo->quantity }} unit</span>
                            <span class="ms-auto">{{ $wo->requester?->name }} · {{ $wo->created_at->format('d M Y') }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto xl:block">
                <table class="w-full min-w-[900px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[23%]">
                        <col class="w-[12%]">
                        <col class="w-[10%]">
                        <col class="w-[27%]">
                        <col class="w-[14%]">
                        <col class="w-[14%]">
                    </colgroup>
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-3 py-3.5 text-left">Barang</th>
                            <th scope="col" class="px-2 py-3.5 text-center">Mode</th>
                            <th scope="col" class="px-2 py-3.5 text-center">Unit</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Alasan</th>
                            <th scope="col" class="px-2 py-3.5 text-center">Status</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Pengajuan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($history as $wo)
                            <tr class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/60">
                                <td class="px-3 py-4">
                                    @php($catalogNames = $wo->units->pluck('catalog.name')->filter()->unique())
                                    @if ($catalogNames->isNotEmpty())
                                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $catalogNames->take(2)->implode(', ') }}</span>
                                        @if ($catalogNames->count() > 2)
                                            <span class="text-[11px] text-slate-400">+{{ $catalogNames->count() - 2 }} katalog lain</span>
                                        @endif
                                    @else
                                        @if ($wo->item)
                                            <a href="{{ route('items.show', $wo->item) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $wo->item->name }}</a>
                                            <div class="font-mono text-[11px] text-slate-400">{{ $wo->item->internal_id }}</div>
                                        @else
                                            <span class="text-slate-500">Pengajuan inventaris keluar</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-2 py-4 text-center">
                                    <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $wo->mode ?: 'CATALOG' }}</span>
                                </td>
                                <td class="px-2 py-4 text-center">
                                    <span class="inline-flex min-w-9 justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $wo->units_count ?: $wo->quantity }}</span>
                                </td>
                                <td class="max-w-[220px] px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                                    {{ \Illuminate\Support\Str::limit($wo->reason, 80) }}
                                    @if ($wo->decision_note)
                                        <div class="mt-0.5 text-red-600 dark:text-red-300">Penolakan: {{ $wo->decision_note }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-4 text-center">
                                    @if ($wo->status === 'PENDING')
                                        <span class="inline-flex justify-center rounded-lg bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">MENUNGGU</span>
                                    @elseif ($wo->status === 'APPROVED')
                                        <span class="inline-flex justify-center rounded-lg bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">DIHAPUS</span>
                                    @else
                                        <span class="inline-flex justify-center rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">DITOLAK</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 dark:text-slate-300">
                                    {{ $wo->requester?->name }}
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $wo->created_at->format('d M Y') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Belum ada penghapusan tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $history->links() }}</div>
        </div>
    </div>

    @push('scripts')
        <script>
            function writeOffForm(config) {
                return {
                    mode: config.mode || 'UNIT',
                    catalogId: Number(config.catalogId) || '',
                    summaries: config.summaries || {},
                    selected: [],
                    selectAll: false,
                    confirmDelete: false,
                    confirmCatalog: false,
                    modalOpen: false,

                    get catalogSummary() {
                        return this.summaries[this.catalogId] || null;
                    },

                    openConfirm() {
                        if (this.mode === 'CATALOG' && !this.catalogId) {
                            alert('Pilih katalog yang akan dihapus seluruh unitnya.');

                            return;
                        }

                        if (this.mode === 'UNIT' && !this.selectAll && this.selected.length === 0) {
                            alert('Pilih minimal satu unit atau centang "Pilih semua sesuai filter".');

                            return;
                        }

                        this.modalOpen = true;
                    },

                    submitConfirmed() {
                        this.confirmDelete = true;
                        this.confirmCatalog = this.mode === 'CATALOG';
                        this.modalOpen = false;
                        this.$nextTick(() => this.$refs.deleteForm.requestSubmit());
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

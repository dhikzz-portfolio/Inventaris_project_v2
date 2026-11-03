<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 ring-1 ring-brand-100 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-800">
                    <span class="material-icons-round text-xl" aria-hidden="true">assignment</span>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold leading-tight text-slate-900 dark:text-white sm:text-xl">Detail Peminjaman</h2>
                        <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">#{{ $loan->id }}</span>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                        @if ($loan->needs_high_value_approval)
                            <span class="rounded-full bg-purple-100 px-2.5 py-1 text-[10px] font-bold text-purple-800 dark:bg-purple-950/50 dark:text-purple-300">NILAI TINGGI</span>
                        @endif
                        @if ($loan->isOverdue())
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">
                                <span class="material-icons-round text-sm" aria-hidden="true">warning</span> TERLAMBAT
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 flex items-start gap-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        <span class="material-icons-round mt-0.5 shrink-0 text-base" aria-hidden="true">notes</span>
                        <span class="min-w-0 break-words">{{ $loan->purpose }}</span>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
                <a href="{{ route('loans.index') }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                    <span class="material-icons-round text-lg" aria-hidden="true">arrow_back</span> Kembali
                </a>
                <a href="{{ route('loans.letter.preview', $loan) }}" target="_blank"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg" aria-hidden="true">print</span> Pratinjau Surat
                </a>
                @if ($loan->bast_path)
                    <a href="{{ route('loans.bast', $loan) }}"
                       class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="material-icons-round text-lg" aria-hidden="true">description</span> Unduh Berita Acara
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span><span>{{ session('success') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span><span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3 lg:gap-5">
        <div class="space-y-4 lg:col-span-2">
            {{-- Rincian barang --}}
            @php
                $filtersActive = collect($itemFilters)->filter(fn ($value) => $value !== '')->isNotEmpty();
                $activeFilterCount = collect($itemFilters)->except('search')->filter(fn ($value) => $value !== '')->count();
                $filterQuery = [
                    'item_search' => $itemFilters['search'],
                    'item_category' => $itemFilters['category'],
                    'item_catalog' => $itemFilters['catalog'],
                    'item_spec' => $itemFilters['specification'],
                    'item_building' => $itemFilters['building'],
                    'item_room' => $itemFilters['room'],
                    'item_condition' => $itemFilters['condition'],
                    'item_status' => $itemFilters['status'],
                    'item_validation' => $itemFilters['validation'],
                    'item_photo' => $itemFilters['photo'],
                    'item_from' => $itemFilters['from'],
                    'item_to' => $itemFilters['to'],
                ];
                $activeFilterTags = [];

                if ($itemFilters['search'] !== '') {
                    $activeFilterTags[] = ['key' => 'item_search', 'label' => 'Pencarian: '.$itemFilters['search']];
                }

                if ($itemFilters['category'] !== '') {
                    $categoryName = $filterOptions['categories']->firstWhere('id', (int) $itemFilters['category'])?->name;
                    $activeFilterTags[] = ['key' => 'item_category', 'label' => 'Kategori: '.($categoryName ?? $itemFilters['category'])];
                }

                if ($itemFilters['catalog'] !== '') {
                    $catalogName = $filterOptions['catalogs']->firstWhere('id', (int) $itemFilters['catalog'])?->name;
                    $activeFilterTags[] = ['key' => 'item_catalog', 'label' => 'Katalog: '.($catalogName ?? $itemFilters['catalog'])];
                }

                if ($itemFilters['specification'] !== '') {
                    $specificationName = $filterOptions['specifications']->firstWhere('id', (int) $itemFilters['specification'])?->displayName();
                    $activeFilterTags[] = ['key' => 'item_spec', 'label' => 'Spesifikasi: '.($specificationName ?? $itemFilters['specification'])];
                }

                if ($itemFilters['building'] !== '') {
                    $activeFilterTags[] = ['key' => 'item_building', 'label' => 'Gedung: '.$itemFilters['building']];
                }

                if ($itemFilters['room'] !== '') {
                    $room = $filterOptions['rooms']->firstWhere('id', (int) $itemFilters['room']);
                    $activeFilterTags[] = ['key' => 'item_room', 'label' => 'Ruangan: '.($room ? $room->code.' — '.$room->name : $itemFilters['room'])];
                }

                if ($itemFilters['condition'] !== '') {
                    $condition = collect($filterOptions['conditions'])->firstWhere('value', $itemFilters['condition']);
                    $activeFilterTags[] = ['key' => 'item_condition', 'label' => 'Kondisi: '.($condition?->label() ?? $itemFilters['condition'])];
                }

                if ($itemFilters['status'] !== '') {
                    $status = collect($filterOptions['statuses'])->firstWhere('value', $itemFilters['status']);
                    $activeFilterTags[] = ['key' => 'item_status', 'label' => 'Status inventaris: '.($status?->label() ?? $itemFilters['status'])];
                }

                if ($itemFilters['validation'] !== '') {
                    $validation = collect($filterOptions['validations'])->firstWhere('value', $itemFilters['validation']);
                    $activeFilterTags[] = ['key' => 'item_validation', 'label' => 'Pemeriksaan: '.($validation?->label() ?? $itemFilters['validation'])];
                }

                if ($itemFilters['photo'] !== '') {
                    $activeFilterTags[] = ['key' => 'item_photo', 'label' => 'Foto bukti: '.($itemFilters['photo'] === 'ada' ? 'Ada' : 'Tidak ada')];
                }

                if ($itemFilters['from'] !== '') {
                    $activeFilterTags[] = ['key' => 'item_from', 'label' => 'Diperiksa dari: '.$itemFilters['from']];
                }

                if ($itemFilters['to'] !== '') {
                    $activeFilterTags[] = ['key' => 'item_to', 'label' => 'Diperiksa sampai: '.$itemFilters['to']];
                }

                $legacyItems = $loan->loanItems->filter(fn ($li) => $li->unitAssignments->isEmpty());
            @endphp
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5 dark:border-slate-800 sm:px-5">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-slate-100">
                        <span class="material-icons-round text-base text-brand-600" aria-hidden="true">format_list_bulleted</span> Rincian Barang
                    </h3>
                    <span class="shrink-0 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ $unitAssignments->total() }} unit fisik
                    </span>
                </div>

                <div class="p-3 sm:p-4" x-data="{ filtersOpen: false }">
                  <div class="space-y-3">
                    <form method="GET" action="{{ route('loans.show', $loan) }}">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label for="item-search" class="sr-only">Cari unit atau catatan kondisi</label>
                            <input id="item-search" type="search" name="item_search" value="{{ $itemFilters['search'] }}"
                                   x-on:keydown.enter.prevent="$el.form.requestSubmit()"
                                   placeholder="Cari nama barang, kode, ruangan, atau catatan"
                                   class="min-h-10 min-w-0 flex-1 rounded-xl border-slate-200 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <button type="button" x-on:click="filtersOpen = !filtersOpen"
                                    x-bind:aria-expanded="filtersOpen"
                                    aria-controls="loan-item-filter-panel"
                                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                                <span class="material-icons-round text-lg" aria-hidden="true">tune</span>
                                Filter
                                @if ($activeFilterCount > 0)
                                    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200">{{ $activeFilterCount }}</span>
                                @endif
                            </button>
                            <button type="submit" class="sr-only">Cari unit</button>
                        </div>

                        <div id="loan-item-filter-panel" x-show="filtersOpen" x-cloak x-transition
                             class="mt-3 space-y-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900 sm:p-4">
                            <section>
                                <h4 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Barang</h4>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="item-category" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Kategori</label>
                                        <select id="item-category" name="item_category" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua kategori</option>
                                            @foreach ($filterOptions['categories'] as $category)
                                                <option value="{{ $category->id }}" @selected($itemFilters['category'] === (string) $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-catalog" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Katalog</label>
                                        <select id="item-catalog" name="item_catalog" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua katalog</option>
                                            @foreach ($filterOptions['catalogs'] as $catalog)
                                                <option value="{{ $catalog->id }}" @selected($itemFilters['catalog'] === (string) $catalog->id)>{{ $catalog->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-spec" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Spesifikasi</label>
                                        <select id="item-spec" name="item_spec" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua spesifikasi</option>
                                            @foreach ($filterOptions['specifications'] as $specification)
                                                <option value="{{ $specification->id }}" @selected($itemFilters['specification'] === (string) $specification->id)>{{ $specification->displayName() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h4 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</h4>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="item-building" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Gedung</label>
                                        <select id="item-building" name="item_building" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua gedung</option>
                                            @foreach ($filterOptions['buildings'] as $building)
                                                <option value="{{ $building }}" @selected($itemFilters['building'] === (string) $building)>Gedung {{ $building }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-room" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Ruangan</label>
                                        <select id="item-room" name="item_room" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua ruangan</option>
                                            @foreach ($filterOptions['rooms'] as $room)
                                                <option value="{{ $room->id }}" @selected($itemFilters['room'] === (string) $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h4 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</h4>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="item-condition" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Kondisi unit</label>
                                        <select id="item-condition" name="item_condition" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua kondisi</option>
                                            @foreach ($filterOptions['conditions'] as $condition)
                                                <option value="{{ $condition->value }}" @selected($itemFilters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-status" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Status inventaris</label>
                                        <select id="item-status" name="item_status" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua status</option>
                                            @foreach ($filterOptions['statuses'] as $status)
                                                <option value="{{ $status->value }}" @selected($itemFilters['status'] === $status->value)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-validation" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Status pemeriksaan</label>
                                        <select id="item-validation" name="item_validation" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua status</option>
                                            @foreach ($filterOptions['validations'] as $validation)
                                                <option value="{{ $validation->value }}" @selected($itemFilters['validation'] === $validation->value)>{{ $validation->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="item-photo" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Foto bukti kerusakan</label>
                                        <select id="item-photo" name="item_photo" class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                            <option value="">Semua unit</option>
                                            <option value="ada" @selected($itemFilters['photo'] === 'ada')>Ada foto</option>
                                            <option value="tidak" @selected($itemFilters['photo'] === 'tidak')>Tanpa foto</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h4 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal pemeriksaan</h4>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="item-from" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Diperiksa dari</label>
                                        <input id="item-from" type="date" name="item_from" value="{{ $itemFilters['from'] }}"
                                               class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                    </div>
                                    <div>
                                        <label for="item-to" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Sampai</label>
                                        <input id="item-to" type="date" name="item_to" value="{{ $itemFilters['to'] }}"
                                               class="w-full rounded-lg border-slate-200 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                    </div>
                                </div>
                            </section>

                            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                    @if ($filtersActive)
                                        Menampilkan {{ $unitAssignments->firstItem() ?? 0 }}–{{ $unitAssignments->lastItem() ?? 0 }} dari {{ $unitAssignments->total() }} unit sesuai filter.
                                    @else
                                        Menampilkan {{ $unitAssignments->count() }} dari {{ $unitAssignments->total() }} unit.
                                    @endif
                                </span>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('loans.show', $loan) }}"
                                       class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                        Hapus filter
                                    </a>
                                    <button type="submit" x-on:click="filtersOpen = false"
                                            class="inline-flex min-h-9 items-center justify-center rounded-lg bg-brand-600 px-3 text-xs font-semibold text-white transition hover:bg-brand-700">
                                        Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if ($activeFilterTags !== [])
                        <div class="flex flex-wrap items-center gap-2" aria-label="Filter aktif">
                            @foreach ($activeFilterTags as $tag)
                                @php
                                    $remainingFilters = array_filter($filterQuery, fn ($value, $key) => $key !== $tag['key'] && $value !== '', ARRAY_FILTER_USE_BOTH);
                                @endphp
                                <a href="{{ route('loans.show', ['loan' => $loan->id] + $remainingFilters) }}"
                                   class="inline-flex max-w-full items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-brand-300 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:text-brand-300"
                                   aria-label="Hapus filter {{ $tag['label'] }}">
                                    <span class="truncate">{{ $tag['label'] }}</span>
                                    <span class="material-icons-round shrink-0 text-sm" aria-hidden="true">close</span>
                                </a>
                            @endforeach
                            <a href="{{ route('loans.show', $loan) }}" class="px-1 text-[11px] font-semibold text-brand-700 underline dark:text-brand-300">Hapus semua</a>
                        </div>
                    @endif
                  </div>
                </div>

                <div x-data="{
                        photoOpen: false,
                        photoError: false,
                        photo: {},
                        openPhoto(data) { this.photo = data; this.photoError = false; this.photoOpen = true; },
                        closePhoto() { this.photoOpen = false; },
                     }"
                     @keydown.escape.window="closePhoto()">
                    @if ($unitAssignments->isNotEmpty())
                        <div role="region" aria-label="Daftar unit barang" tabindex="0"
                             class="max-h-[26rem] overflow-auto overscroll-contain border-y border-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-slate-800">
                            <table class="w-full min-w-[680px] text-left text-xs">
                            <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500 shadow-[inset_0_-1px_0_0_#e2e8f0] dark:bg-slate-800 dark:text-slate-400 dark:shadow-[inset_0_-1px_0_0_#334155]">
                                <tr>
                                    <th class="px-4 py-3">Kode unit</th>
                                    <th class="px-4 py-3">Lokasi</th>
                                    <th class="px-4 py-3">Kondisi</th>
                                    <th class="px-4 py-3">Dikembalikan</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($unitAssignments as $assignment)
                                @php
                                    $unit = $assignment->unit;
                                    $loanItem = $assignment->loanItem;
                                    $specification = $unit?->specification?->displayName()
                                        ?: ($loanItem?->item?->specification ?: 'Spesifikasi belum dicatat');
                                    $checkedAt = $assignment->confirmed_at ?? $assignment->returned_at;
                                    $damagePhoto = $assignment->damage_photo_path;
                                @endphp
                                <tr class="align-top transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40 {{ $damagePhoto ? 'bg-red-50/30 dark:bg-red-950/10' : '' }}">
                                    <td class="max-w-[18rem] px-4 py-3">
                                        <div class="font-mono text-[11px] font-bold text-slate-800 dark:text-slate-100">{{ $unit?->inventory_code ?? '-' }}</div>
                                        <div class="mt-1 truncate font-semibold text-slate-700 dark:text-slate-200">{{ $loanItem?->item?->name ?? '-' }}</div>
                                        <div class="truncate text-[11px] text-slate-500 dark:text-slate-400" title="{{ $specification }}">{{ $specification }}</div>
                                        @if ($loanItem?->item?->brand || $loanItem?->item?->model_type)
                                            <div class="mt-0.5 truncate text-[10px] text-slate-400 dark:text-slate-500">{{ trim(($loanItem?->item?->brand ?? '').' '.($loanItem?->item?->model_type ?? '')) }}</div>
                                        @endif
                                        @if ($assignment->return_notes)
                                            <p class="mt-1 line-clamp-2 text-[10px] italic text-slate-500 dark:text-slate-400" title="{{ $assignment->return_notes }}">Catatan: {{ $assignment->return_notes }}</p>
                                        @endif
                                        @if ($damagePhoto && $canViewDamagePhotos)
                                            <button type="button"
                                                    x-on:click="openPhoto({{ \Illuminate\Support\Js::from([
                                                        'url' => route('files.show', ['path' => $damagePhoto]),
                                                        'code' => $unit?->inventory_code,
                                                        'name' => $loanItem?->item?->name,
                                                        'specification' => $specification,
                                                        'date' => $checkedAt?->format('d M Y H:i'),
                                                        'notes' => $assignment->return_notes,
                                                        'officer' => $assignment->confirmer?->name,
                                                    ]) }})"
                                                    class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-red-700 hover:underline dark:text-red-300">
                                                <span class="material-icons-round text-[13px]" aria-hidden="true">photo_camera</span>                                                 Lihat foto bukti kerusakan
                                            </button>
                                        @elseif ($damagePhoto)
                                            <p class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-red-700 dark:text-red-300">
                                                <span class="material-icons-round text-[13px]" aria-hidden="true">photo_camera</span> Foto bukti tersedia (akses petugas)
                                            </p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $unit?->locationLabel() ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ ($assignment->condition_in ?? $unit?->condition)?->isDamaged() ? 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-300' }}">
                                            {{ ($assignment->condition_in ?? $unit?->condition)?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">
                                        @if ($checkedAt)
                                            {{ $checkedAt->format('d M Y H:i') }}
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">Belum kembali</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ ($assignment->return_status ?? \App\Enums\ReturnStatus::BELUM_DIPERIKSA)->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                            </table>
                        </div>
                    @else
                        <div class="border-y border-slate-100 px-4 py-8 text-center dark:border-slate-800">
                            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                                <span class="material-icons-round text-xl" aria-hidden="true">inventory_2</span>
                            </span>
                            <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ $filtersActive ? 'Tidak ada unit yang cocok' : 'Unit fisik belum dicatat' }}
                            </p>
                            <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $filtersActive ? 'Coba ubah kata kunci atau filter untuk melihat hasil lainnya.' : 'Peminjaman ini menggunakan data katalog lama dan belum memiliki unit fisik yang ditautkan.' }}
                            </p>
                            @if ($filtersActive)
                                <a href="{{ route('loans.show', $loan) }}" class="mt-3 inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-brand-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">Hapus semua filter</a>
                            @endif
                        </div>
                    @endif

                    {{-- Popup bukti kerusakan --}}
                    <div x-show="photoOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="damage-photo-title">
                        <div class="absolute inset-0 bg-slate-900/70" x-on:click="closePhoto()"></div>
                        <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-4 shadow-xl dark:bg-slate-900">
                            <div class="mb-2 flex items-start justify-between gap-3">
                                <div>
                                    <h4 id="damage-photo-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Foto Bukti Kerusakan</h4>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400" x-text="photo.code || '-'"></p>
                                </div>
                                <button type="button" x-on:click="closePhoto()" aria-label="Tutup popup foto"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                    <span class="material-icons-round">close</span>
                                </button>
                            </div>

                            <img x-show="!photoError" x-on:error="photoError = true" :src="photo.url" :alt="'Foto bukti kerusakan unit ' + (photo.code || '')"
                                 class="max-h-[55vh] w-full rounded-xl border border-slate-200 bg-slate-100 object-contain dark:border-slate-700 dark:bg-slate-800">
                            <div x-show="photoError" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                Foto tidak ditemukan atau tidak dapat dibuka. Hubungi petugas inventaris untuk bukti fisik.
                            </div>

                            <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs text-slate-500 dark:text-slate-400">Barang</dt><dd class="font-semibold text-slate-800 dark:text-slate-100" x-text="photo.name || '-'"></dd></div>
                                <div><dt class="text-xs text-slate-500 dark:text-slate-400">Spesifikasi</dt><dd class="text-slate-700 dark:text-slate-200" x-text="photo.specification || '-'"></dd></div>
                                <div><dt class="text-xs text-slate-500 dark:text-slate-400">Tanggal pemeriksaan</dt><dd class="text-slate-700 dark:text-slate-200" x-text="photo.date || '-'"></dd></div>
                                <div><dt class="text-xs text-slate-500 dark:text-slate-400">Petugas</dt><dd class="text-slate-700 dark:text-slate-200" x-text="photo.officer || '-'"></dd></div>
                            </dl>
                            <div class="mt-2 text-sm">
                                <div class="text-xs text-slate-500 dark:text-slate-400">Catatan kerusakan</div>
                                <p class="text-slate-700 dark:text-slate-200" x-text="photo.notes || 'Tidak ada catatan.'"></p>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($unitAssignments->hasPages())
                    <div class="flex justify-end px-3 py-3">
                        {{ $unitAssignments->links() }}
                    </div>
                @endif

                @if ($legacyItems->isNotEmpty())
                    <div class="mt-5">
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/60">
                                <h4 class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Baris katalog tanpa unit fisik (data lama)</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[520px] table-fixed text-left text-[11px]">
                                    <colgroup>
                                        <col class="w-[30%]">
                                        <col class="w-[14%]">
                                        <col class="w-[18%]">
                                        <col class="w-[19%]">
                                        <col class="w-[19%]">
                                    </colgroup>
                                    <thead class="border-b border-slate-200 bg-white text-[9px] font-bold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Barang</th>
                                            <th class="px-2 py-2 text-left">Ruangan</th>
                                            <th class="px-2 py-2 text-center">Pinjam / Kembali</th>
                                            <th class="px-2 py-2 text-center">Kondisi Keluar</th>
                                            <th class="px-2 py-2 text-center">Kondisi Masuk</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                                        @foreach ($legacyItems as $li)
                                            <tr class="align-middle transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                <td class="px-3 py-2.5">
                                                    <a href="{{ route('items.show', $li->item) }}" class="block break-words font-semibold leading-tight text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $li->item?->name }}</a>
                                                    <span class="mt-1 block break-all font-mono text-[10px] text-slate-500 dark:text-slate-400">{{ $li->item?->internal_id }}</span>
                                                </td>
                                                <td class="px-2 py-2.5 text-slate-600 dark:text-slate-300">{{ $li->item?->room?->code ?: '—' }}</td>
                                                <td class="px-2 py-2.5 text-center">
                                                    <span class="block font-semibold text-slate-800 dark:text-slate-100">{{ $li->qty_borrowed }}</span>
                                                    <span class="mt-0.5 block text-[10px] text-slate-500 dark:text-slate-400">
                                                        @if ($li->qty_returned === null)
                                                            — kembali
                                                        @else
                                                            {{ $li->qty_returned }} kembali
                                                        @endif
                                                    </span>
                                                    @if ($li->hasShortage())
                                                        <span class="mt-1 inline-flex rounded-full bg-red-50 px-1.5 py-0.5 text-[9px] font-bold text-red-700 dark:bg-red-950/50 dark:text-red-300">HILANG {{ $li->missingQuantity() }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2.5 text-center text-slate-600 dark:text-slate-300">{{ $li->condition_out->label() }}</td>
                                                <td class="px-2 py-2.5 text-center">
                                                    @if ($li->condition_in)
                                                        <span class="text-xs font-semibold {{ $li->condition_in->isDamaged() ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-300' }}">{{ $li->condition_in->label() }}</span>
                                                        @if ($li->damage_photo_path && $canViewDamagePhotos)
                                                            <a href="{{ route('files.show', ['path' => $li->damage_photo_path]) }}" target="_blank" rel="noopener" class="mt-1 block text-[10px] text-brand-700 underline dark:text-brand-300">Foto bukti</a>
                                                        @endif
                                                    @else
                                                        <span class="text-slate-400 dark:text-slate-500">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($hasShortage)
                    <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                        <div class="flex items-start gap-2">
                            <span class="material-icons-round">warning</span>
                            <div>
                                <strong>Selisih kuantitas terdeteksi.</strong> Status pinjaman tidak dapat diselesaikan selama ada barang yang kurang — selisih otomatis berflag <strong>Hilang</strong> dan riwayat peminjam terakhir tercatat.
                                @if (auth()->user()->isApproverLevel2() && $loan->status !== \App\Enums\LoanStatus::SELESAI)
                                    <form method="POST" action="{{ route('loans.close-shortage', $loan) }}" class="mt-3"
                                          onsubmit="return confirm('Tutup pinjaman ini? Barang tetap berstatus Hilang sampai ditemukan kembali.')">
                                        @csrf
                                        <button class="min-h-9 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                            Tutup Pinjaman (barang tetap Hilang)
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::APPROVED && $canManage)
                    <div class="mt-4 overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm dark:border-brand-900/60 dark:bg-slate-900">
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600 dark:bg-green-950/50 dark:text-green-400">
                                    <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Peminjaman disetujui</p>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Serahkan barang kepada peminjam untuk memulai masa pinjam.</p>
                                </div>
                            </div>
                            <a href="{{ route('loans.handover-form', $loan) }}"
                               class="inline-flex min-h-10 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 sm:w-auto">
                                <span class="material-icons-round text-lg" aria-hidden="true">inventory</span> Serah Terima + Pilih Unit
                            </a>
                        </div>
                        @if ($loan->loanItems->isNotEmpty())
                            <div class="flex items-start gap-2 border-t border-slate-100 bg-slate-50/70 px-4 py-3 text-xs leading-relaxed text-slate-500 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400 sm:px-5">
                                <span class="material-icons-round mt-0.5 shrink-0 text-sm text-slate-400" aria-hidden="true">info</span>
                                <p>Pilih unit fisik pada halaman serah terima. Unit yang sedang dipinjam, tidak aktif, atau rusak tidak dapat dipilih.</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::DIPINJAM && $canManage)
                    <div class="mt-4 flex justify-end">
                        <a href="{{ route('loans.return-form', $loan) }}"
                           class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <span class="material-icons-round text-lg">assignment_return</span> Proses Pengembalian
                        </a>
                    </div>
                @endif
            </div>

            {{-- Approval --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3.5 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100 sm:px-5">
                    <span class="material-icons-round text-base text-brand-600" aria-hidden="true">verified_user</span> Jejak Persetujuan (Berjenjang)
                </h3>

                <div class="p-4 sm:p-5">
                    @if ($loan->approvals->isEmpty())
                        <p class="text-sm text-slate-400">Belum ada persetujuan.</p>
                    @else
                        <ul class="space-y-4">
                        @foreach ($loan->approvals->sortBy('level') as $approval)
                            @php
                                $approverRole = $approval->approver?->role
                                    ? \App\Enums\Role::from($approval->approver->role)->shortLabel()
                                    : 'Pihak berwenang';
                            @endphp
                            <li class="flex items-start gap-3 border-b border-dashed border-slate-200 pb-4 last:border-0 last:pb-0 dark:border-slate-700">
                                <span class="inline-flex items-center justify-center h-9 w-9 rounded-full
                                    {{ $approval->decision->value === 'APPROVED' ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                    <span class="material-icons-round text-xl">{{ $approval->decision->value === 'APPROVED' ? 'check_circle' : 'cancel' }}</span>
                                </span>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        Level {{ $approval->level }} — {{ $approverRole }}:
                                        {{ $approval->approver?->name }}
                                        <span class="text-[11px] font-bold {{ $approval->decision->value === 'APPROVED' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $approval->decision->label() }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $approval->decided_at->format('d M Y H:i') }}</div>
                                    @if ($approval->note)
                                        <div class="mt-1 text-xs italic text-slate-600 dark:text-slate-300">"{{ $approval->note }}"</div>
                                    @endif
                                </div>
                                @if ($approval->signature_path)
                                    <div class="text-center shrink-0">
                                        <img src="{{ route('files.show', ['path' => $approval->signature_path]) }}" alt="TTD" class="h-12 object-contain border border-slate-200 rounded bg-white px-1 dark:border-slate-600">
                                        <div class="text-[9px] text-slate-400">TTD Digital</div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                        </ul>
                    @endif

                    {{-- Form persetujuan --}}
                    @if ($canAct && $nextLevel !== null)
                    @php $actorRole = \App\Enums\Role::from(auth()->user()->role)->shortLabel(); @endphp
                    <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-700">
                        <div class="flex items-center gap-2 mb-3 text-sm text-slate-700">
                            <span class="material-icons-round text-brand-600">gesture</span>
                            Keputusan Anda: <strong>Persetujuan Level {{ $nextLevel }} ({{ $actorRole }})</strong>
                        </div>

                        <form method="POST" action="{{ route('loans.approve', $loan) }}" id="approve-form" class="space-y-4" x-data="signaturePad()">
                            @csrf
                            <input type="hidden" name="signature" x-ref="signatureInput">
                            <div>
                                <x-input-label for="approval-note" value="Catatan (opsional)" />
                                <textarea id="approval-note" name="note" rows="2" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                          placeholder="Syarat/catatan persetujuan..."></textarea>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                        <span class="material-icons-round text-base text-brand-600" aria-hidden="true">gesture</span>
                                        <span class="font-semibold">Tanda Tangan Digital <span class="font-normal text-slate-400 dark:text-slate-500">(opsional)</span></span>
                                        <span x-show="signature" x-cloak x-transition.opacity class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-800 dark:bg-green-950/50 dark:text-green-300">
                                            <span class="material-icons-round text-[12px]" aria-hidden="true">check_circle</span> Tersemat
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" x-on:click="openPad()"
                                                class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-brand-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">
                                            <span class="material-icons-round text-sm" aria-hidden="true">edit</span>
                                            <span x-text="signature ? 'Ubah tanda tangan' : 'Tambah tanda tangan'">Tambah tanda tangan</span>
                                        </button>
                                        <button type="button" x-show="signature" x-cloak x-on:click="removeSignature()"
                                                class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">
                                            <span class="material-icons-round text-sm" aria-hidden="true">delete</span> Hapus
                                        </button>
                                    </div>
                                </div>
                                <div x-show="signature" x-cloak class="mt-2 flex items-center gap-2">
                                    <img :src="signature" alt="Pratinjau tanda tangan digital" class="h-14 rounded-lg border border-slate-200 bg-white px-1 dark:border-slate-600">
                                    <span class="text-[10px] leading-relaxed text-slate-400 dark:text-slate-500">Tanda tangan ini dilampirkan saat persetujuan dikirim.</span>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-bold">
                                    <span class="material-icons-round text-lg">check_circle</span> Setujui
                                </button>
                            </div>

                            {{-- Modal tanda tangan digital --}}
                            <div x-show="padOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="signature-pad-title" x-on:keydown.escape.window="closePad()">
                                <div class="absolute inset-0 bg-slate-900/70" x-on:click="closePad()"></div>
                                <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-4 shadow-xl dark:bg-slate-900">
                                    <div class="mb-2 flex items-start justify-between gap-3">
                                        <div>
                                            <h4 id="signature-pad-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Tanda Tangan Digital</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Gambar tanda tangan menggunakan mouse, jari, atau stylus.</p>
                                        </div>
                                        <button type="button" x-on:click="closePad()" aria-label="Tutup panel tanda tangan"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                            <span class="material-icons-round">close</span>
                                        </button>
                                    </div>

                                    <canvas x-ref="pad" class="h-40 w-full touch-none cursor-crosshair rounded-xl border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-100"></canvas>

                                    <p x-show="padError" x-cloak class="mt-2 flex items-center gap-1 text-xs font-semibold text-red-600 dark:text-red-400">
                                        <span class="material-icons-round text-sm" aria-hidden="true">error_outline</span>
                                        <span x-text="padError"></span>
                                    </p>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <button type="button" x-on:click="clearCanvas()"
                                                class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                            <span class="material-icons-round text-sm" aria-hidden="true">refresh</span> Ulangi
                                        </button>
                                        <div class="flex items-center gap-2">
                                            <button type="button" x-on:click="closePad()"
                                                    class="inline-flex min-h-9 items-center rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Batal</button>
                                            <button type="button" x-on:click="saveSignature()"
                                                    class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-700">
                                                <span class="material-icons-round text-sm" aria-hidden="true">check</span> Gunakan Tanda Tangan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('loans.reject', $loan) }}" id="reject-form" class="mt-3 space-y-2">
                            @csrf
                            <div>
                                <x-input-label value="Alasan Penolakan *" />
                                <input type="text" name="note" required
                                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                       placeholder="Barang dipakai kegiatan lain, dsb.">
                                <x-input-error :messages="$errors->get('note')" class="mt-1" />
                            </div>
                            <button class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                                <span class="material-icons-round text-lg">cancel</span> Tolak Peminjaman
                            </button>
                        </form>

                        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            @if (auth()->user()->isApproverLevel2())
                                Persetujuan Anda sebagai Wakasek Sarpras langsung menutup rangkaian persetujuan — tidak dituntut persetujuan Admin tambahan.
                            @elseif ($loan->requiresLevel2() && $nextLevel === 1)
                                Setelah Level 1, request lanjut ke Wakasek Sarpras (Level 2) untuk barang bernilai tinggi.
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-5">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3.5 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100 sm:px-5">
                    <span class="material-icons-round text-base text-brand-600" aria-hidden="true">info</span> Ringkasan
                </h3>
                <div class="space-y-3 p-4 sm:p-5">
                @unless (auth()->user()->isPeminjam())
                    @if ($loan->borrower?->displayName())
                        <div class="border-b border-slate-100 pb-3 dark:border-slate-800">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Peminjam</p>
                            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $loan->borrower->displayName() }}</p>
                            @if ($loan->borrower?->email)
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower->email }}</p>
                            @endif
                            @if ($loan->borrower?->major?->name)
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $loan->borrower->major->name }}</p>
                            @endif
                        </div>
                    @endif
                @endunless
                <div>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ auth()->user()->isPeminjam() ? 'Mulai pinjam' : 'Pinjam' }}</p>
                    <p class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $loan->start_date->format('d M Y · H:i') }}</p>
                </div>
                <div class="border-t border-slate-100 pt-3 dark:border-slate-800">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ auth()->user()->isPeminjam() ? 'Batas pengembalian' : 'Batas kembali' }}</p>
                    <p class="mt-1 font-semibold {{ $loan->isOverdue() ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $loan->due_date->format('d M Y · H:i') }}</p>
                </div>
                @if ($loan->event)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Kegiatan:</div>
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $loan->event->name }}</div>
                        @if (filled($loan->event->picDisplayName()))
                            <div class="text-xs text-slate-600 dark:text-slate-300">PJ: {{ $loan->event->picDisplayName() }}</div>
                        @endif
                        @if ($loan->event->start_date || $loan->event->end_date)
                            <div class="text-xs text-slate-600 dark:text-slate-300">{{ $loan->event->start_date->format('d/m/Y') }} — {{ $loan->event->end_date->format('d/m/Y') }}</div>
                        @endif
                        <a href="{{ route('events.show', $loan->event) }}" class="inline-flex min-h-8 items-center text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Lihat rekap kegiatan →</a>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DITOLAK && $loan->reject_reason)
                    <div class="border-t border-dashed border-slate-200 pt-3 text-red-600 dark:border-slate-700 dark:text-red-400">
                        <div class="text-xs font-bold">DITOLAK:</div>
                        <div class="text-xs">{{ $loan->reject_reason }}</div>
                    </div>
                @endif
                @if ($loan->status === \App\Enums\LoanStatus::DIBATALKAN)
                    <div class="border-t border-dashed border-slate-200 pt-3 text-slate-600 dark:border-slate-700 dark:text-slate-300">
                        <div class="text-xs font-bold">DIBATALKAN:</div>
                        <div class="text-xs">{{ $loan->cancel_reason ?: 'Tanpa alasan tertulis' }}</div>
                        @php($cancelMeta = collect([
                            filled($loan->canceller?->name) ? 'oleh '.$loan->canceller->name : null,
                            $loan->cancelled_at?->format('d M Y H:i'),
                        ])->filter()->implode(' • '))
                        @if ($cancelMeta !== '')
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $cancelMeta }}</div>
                        @endif
                    </div>
                @endif
                @if ($loan->handed_over_at)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400">Diserahkan:</span> <span class="text-slate-700 dark:text-slate-200">{{ $loan->handed_over_at->format('d M Y H:i') }}</span>
                        @if (filled($loan->handoverOfficer?->name))
                            <div class="text-xs text-slate-500 dark:text-slate-400">oleh {{ $loan->handoverOfficer->name }}</div>
                        @endif
                    </div>
                @endif

                @if ($canCancel)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <form method="POST" action="{{ route('loans.cancel', $loan) }}"
                              onsubmit="return confirm('Batalkan pengajuan peminjaman ini? Jadwal akan dibebaskan.')"
                              class="space-y-2">
                            @csrf
                            <input type="text" name="cancel_reason" maxlength="500" placeholder="Alasan pembatalan (opsional)"
                                   class="w-full rounded-xl border-slate-300 bg-white text-xs text-slate-800 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <button class="inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-red-50 hover:text-red-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                <span class="material-icons-round text-sm">cancel</span> Batalkan Pengajuan
                            </button>
                        </form>
                    </div>
                @endif
                @if ($loan->returned_at)
                    <div class="border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                        <span class="text-slate-500 dark:text-slate-400">Dikembalikan:</span> <span class="text-slate-700 dark:text-slate-200">{{ $loan->returned_at->format('d M Y H:i') }}</span>
                        @if (filled($loan->returner?->name))
                            <div class="text-xs text-slate-500 dark:text-slate-400">oleh {{ $loan->returner->name }}</div>
                        @endif
                    </div>
                @endif
                </div>
            </div>

            @if (in_array($loan->status, [\App\Enums\LoanStatus::PENDING, \App\Enums\LoanStatus::APPROVED, \App\Enums\LoanStatus::DIPINJAM, \App\Enums\LoanStatus::SELESAI], true))
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="flex items-center gap-2 border-b border-slate-100 px-4 py-3.5 text-sm font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100 sm:px-5">
                        <span class="material-icons-round text-base text-brand-600" aria-hidden="true">description</span> Dokumen Resmi
                    </h3>

                    <div class="space-y-3 p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Status dokumen</span>
                        <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->document_status->badgeClass() }}">
                            {{ $loan->document_status->label() }}
                        </span>
                    </div>

                    <a href="{{ route('loans.bast', $loan) }}" target="_blank"
                       class="mb-2 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        {{ $loan->event ? 'Surat Peminjaman & Berita Acara' : 'Buat Surat Peminjaman' }}
                    </a>

                    @if ($canManageDocuments)
                        <div class="space-y-2 border-t border-dashed border-slate-200 pt-3 dark:border-slate-700">
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Catatan petugas</p>
                            <div class="flex flex-wrap gap-1.5">
                                @unless ($loan->document_status === \App\Enums\DocumentStatus::DICETAK)
                                    <form method="POST" action="{{ route('loans.document.printed', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                                            Tandai sudah dicetak
                                        </button>
                                    </form>
                                @endunless
                                @unless ($loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-physical', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                            Sudah ditandatangani (fisik)
                                        </button>
                                    </form>
                                @endunless
                                @if ($loan->approvals->contains(fn ($a) => $a->signature_path) && ! $loan->document_status->isSigned())
                                    <form method="POST" action="{{ route('loans.document.signed-digital', $loan) }}">
                                        @csrf
                                        <button class="min-h-8 rounded-lg bg-green-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                            Sudah ditandatangani (digital)
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Mencatat tanda tangan tidak mengubah status persetujuan transaksi.
                            </p>
                        </div>
                    @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function signaturePad() {
            return {
                padOpen: false,
                signature: '',
                padError: '',
                hasInk: false,
                drawing: false,
                init() {
                    this.$watch('padOpen', (open) => {
                        if (open) {
                            this.$nextTick(() => this.preparePad());
                        }
                    });

                    const canvas = this.$refs.pad;
                    if (!canvas) return;

                    const point = (event) => {
                        const rect = canvas.getBoundingClientRect();
                        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
                    };

                    canvas.onpointerdown = (event) => {
                        event.preventDefault();
                        this.drawing = true;
                        this.hasInk = true;
                        this.padError = '';
                        canvas.setPointerCapture(event.pointerId);
                        const ctx = canvas.getContext('2d');
                        const p = point(event);
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(p.x + 0.1, p.y);
                        ctx.stroke();
                    };
                    canvas.onpointermove = (event) => {
                        if (!this.drawing) return;
                        event.preventDefault();
                        const ctx = canvas.getContext('2d');
                        const p = point(event);
                        ctx.lineTo(p.x, p.y);
                        ctx.stroke();
                    };
                    canvas.onpointerup = () => { this.drawing = false; };
                    canvas.onpointercancel = () => { this.drawing = false; };
                },
                openPad() {
                    this.padError = '';
                    this.padOpen = true;
                },
                closePad() {
                    this.drawing = false;
                    this.padOpen = false;
                },
                preparePad() {
                    const canvas = this.$refs.pad;
                    if (!canvas) return;

                    const rect = canvas.getBoundingClientRect();
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.max(1, Math.round(rect.width * dpr));
                    canvas.height = Math.max(1, Math.round(rect.height * dpr));

                    const ctx = canvas.getContext('2d');
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    ctx.lineWidth = 2.2;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.strokeStyle = '#1e293b';

                    this.hasInk = false;
                    this.padError = '';
                },
                clearCanvas() {
                    this.preparePad();
                },
                saveSignature() {
                    if (!this.hasInk) {
                        this.padError = 'Tanda tangan masih kosong. Gambar tanda tangan terlebih dahulu.';
                        return;
                    }
                    const data = this.$refs.pad.toDataURL('image/png');
                    this.signature = data;
                    this.$refs.signatureInput.value = data;
                    this.padOpen = false;
                },
                removeSignature() {
                    this.signature = '';
                    if (this.$refs.signatureInput) {
                        this.$refs.signatureInput.value = '';
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>

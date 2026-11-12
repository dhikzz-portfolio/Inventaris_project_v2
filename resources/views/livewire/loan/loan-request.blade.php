<div @class([
    'grid grid-cols-1 gap-5 lg:grid-cols-5',
    'pb-16 md:pb-0' => $cart !== [],
])>
    {{-- Kiri: cari barang + keranjang --}}
    <div class="contents lg:col-span-3 lg:block lg:space-y-5">
        @php
            $selectedFilterCount = collect([$category, $building, $room, $availability])->filter(fn ($value) => $value !== '')->count();
            $categoryFilterOptions = $categories->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->name])->values()->all();
            $buildingFilterOptions = collect($buildings)->map(fn ($option) => ['value' => (string) $option['code'], 'label' => $option['name']])->values()->all();
            $roomFilterOptions = $rooms->map(fn ($option) => ['value' => (string) $option->id, 'label' => $option->code.' — '.$option->name])->values()->all();
            $availabilityFilterOptions = [
                ['value' => 'available', 'label' => 'Ada unit tersedia'],
                ['value' => 'unavailable', 'label' => 'Tidak ada unit tersedia'],
            ];
        @endphp
        <div x-data="{ filtersOpen: {{ $selectedFilterCount > 0 ? 'true' : 'false' }} }" class="order-1 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:order-none">
            <h3 class="mb-4 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-brand-600" aria-hidden="true">add_shopping_cart</span> 1. Pilih barang dari katalog
            </h3>
            <div class="flex flex-col gap-2 sm:flex-row">
              <div class="relative min-w-0 flex-1">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">search</span>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Cari nama, spesifikasi, atau kode inventaris"
                       aria-label="Cari barang"
                       class="w-full rounded-xl border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
              </div>
              <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="loan-request-filter-panel"
                      class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                  <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                  Filter
                  @if ($selectedFilterCount > 0)
                      <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $selectedFilterCount }}</span>
                  @endif
              </button>
            </div>

            <div id="loan-request-filter-panel" x-show="filtersOpen" x-cloak x-transition class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
              <div class="grid grid-cols-2 gap-2 text-xs">
                <x-searchable-select id="loan-request-filter-category" wire-model="category" :selected="$category"
                                     :options="$categoryFilterOptions" placeholder="Semua kategori"
                                     aria-label="Filter kategori" class="min-w-0" />
                <x-searchable-select id="loan-request-filter-building" wire-model="building" :selected="$building"
                                     :options="$buildingFilterOptions" placeholder="Semua gedung"
                                     aria-label="Filter gedung" class="min-w-0" />
                <x-searchable-select id="loan-request-filter-room" wire-model="room" :selected="$room"
                                     :options="$roomFilterOptions" placeholder="Semua ruangan"
                                     aria-label="Filter ruangan" class="min-w-0" />
                <x-searchable-select id="loan-request-filter-availability" wire-model="availability" :selected="$availability"
                                     :options="$availabilityFilterOptions" placeholder="Semua ketersediaan"
                                     aria-label="Filter ketersediaan" class="min-w-0" />
              </div>
              @if ($activeFilters > 0)
                <div class="mt-2 flex justify-end">
                    <button wire:click="resetFilters" class="inline-flex min-h-8 items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50">
                        <span class="material-icons-round text-sm">filter_alt_off</span> Reset filter
                    </button>
                </div>
              @endif
            </div>

            @if ($items->isNotEmpty())
                <ul class="mt-3 max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    @foreach ($items as $item)
                        @php($inCart = collect($cart)->contains(fn ($line) => (int) $line['item_id'] === $item->id))
                        <li wire:key="loan-item-{{ $item->id }}" class="flex items-start gap-3 py-3">
                            <div class="flex-1 min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item->name }}</div>
                                <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $item->item_type_code ?: $item->internal_id }} • {{ $item->room?->name ?? $item->room?->code }}</div>
                                @if ($item->specification)
                                    <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $item->specification }}</div>
                                @endif
                                <div class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-300">
                                    Tersedia <strong>{{ $item->available_units_count }}</strong> unit siap dipinjam
                                </div>
                                @if ($item->units->isNotEmpty())
                                    @php($unitCodes = $item->units->pluck('inventory_code'))
                                    <p
                                        class="mt-0.5 truncate font-mono text-[10px] text-slate-500 dark:text-slate-400"
                                        title="Unit: {{ $unitCodes->implode(', ') }}{{ $item->available_units_count > $unitCodes->count() ? ', dan unit lainnya' : '' }}"
                                    >
                                        Unit: {{ $unitCodes->first() }}{{ $item->available_units_count > 1 ? ' + '.($item->available_units_count - 1).' unit lainnya' : '' }}
                                    </p>
                                @endif
                            </div>
                            @if ($item->is_high_value)
                                <span class="rounded-full bg-purple-50 px-2 py-1 text-[10px] font-bold text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">Nilai tinggi</span>
                            @endif
                            @if ($inCart)
                                <span class="shrink-0 text-[11px] font-semibold text-green-700 dark:text-green-300">Sudah ditambahkan</span>
                            @elseif ($item->available_units_count < 1)
                                <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">tidak tersedia</span>
                            @else
                                <button wire:click="add({{ $item->id }})"
                                        class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                    <span class="material-icons-round text-sm">add</span> Tambah
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 rounded-xl bg-slate-50 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Tidak ada barang yang cocok dengan pencarian atau filter.</p>
            @endif
        </div>

        <div class="order-2 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:order-none">
            <h3 class="mb-4 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-brand-600" aria-hidden="true">shopping_basket</span> 2. Keranjang peminjaman ({{ count($cart) }} jenis barang)
            </h3>

            @if ($cart === [])
                <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Belum ada barang yang dipilih. Tambahkan barang dari katalog di atas.</p>
            @else
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2">Barang</th>
                                <th class="text-left py-2">Ruangan</th>
                                <th class="text-center py-2 w-28">Jumlah</th>
                                <th class="text-left py-2">Ketersediaan</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($cart as $index => $line)
                                <tr wire:key="cart-row-{{ $line['item_id'] }}">
                                    <td class="py-2.5">
                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $line['name'] }}</div>
                                        <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $line['internal_id'] }}</div>
                                    </td>
                                    <td class="py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $line['room'] }}</td>
                                    <td class="py-2.5 text-center">
                                        <input type="number" min="1" max="{{ $line['max'] }}" wire:model.live="cart.{{ $index }}.qty"
                                               aria-label="Jumlah {{ $line['name'] }}"
                                               class="w-20 rounded-lg border-slate-300 bg-white text-center text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400">maks {{ $line['max'] }}</div>
                                    </td>
                                    <td class="py-2.5">
                                        @if (isset($lineStatus[$line['item_id']]))
                                            @if ($lineStatus[$line['item_id']]['ok'])
                                                <span class="inline-flex items-center gap-1 text-[11px] text-green-700 dark:text-green-300">
                                                    <span class="material-icons-round text-sm">check_circle</span> Tersedia
                                                </span>
                                            @else
                                                <span class="inline-flex max-w-[220px] items-start gap-1 text-[11px] text-red-600 dark:text-red-300">
                                                    <span class="material-icons-round text-sm">error</span> {{ $lineStatus[$line['item_id']]['msg'] }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400">isi tanggal dulu</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <button wire:click="remove({{ $index }})" title="Hapus {{ $line['name'] }} dari keranjang" aria-label="Hapus {{ $line['name'] }} dari keranjang"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:hover:bg-red-950/40 dark:hover:text-red-200">
                                            <span class="material-icons-round text-lg" aria-hidden="true">close</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="grid gap-3 sm:hidden">
                    @foreach ($cart as $index => $line)
                        <li wire:key="cart-card-{{ $line['item_id'] }}" class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $line['name'] }}</p>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $line['internal_id'] }}</p>
                                </div>
                                <button wire:click="remove({{ $index }})" title="Hapus {{ $line['name'] }} dari keranjang" aria-label="Hapus {{ $line['name'] }} dari keranjang"
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-red-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:hover:bg-red-950/40 dark:hover:text-red-200">
                                    <span class="material-icons-round text-lg" aria-hidden="true">delete_outline</span>
                                </button>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label for="cart-qty-{{ $index }}" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                                    <input id="cart-qty-{{ $index }}" type="number" min="1" max="{{ $line['max'] }}" wire:model.live="cart.{{ $index }}.qty"
                                           class="mt-1 w-full rounded-lg border-slate-300 bg-white text-center text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">Maks. {{ $line['max'] }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ruangan</p>
                                    <p class="mt-2 text-xs text-slate-700 dark:text-slate-200">{{ $line['room'] ?: '—' }}</p>
                                </div>
                            </div>
                            <div class="mt-3 border-t border-slate-100 pt-2 dark:border-slate-800">
                                @if (isset($lineStatus[$line['item_id']]))
                                    @if ($lineStatus[$line['item_id']]['ok'])
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-300">
                                            <span class="material-icons-round text-sm" aria-hidden="true">check_circle</span> Tersedia untuk jadwal ini
                                        </span>
                                    @else
                                        <span class="inline-flex items-start gap-1 text-xs text-red-600 dark:text-red-300">
                                            <span class="material-icons-round text-sm" aria-hidden="true">error</span> {{ $lineStatus[$line['item_id']]['msg'] }}
                                        </span>
                                    @endif
                                @else
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Atur tanggal untuk memeriksa ketersediaan.</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 flex items-start gap-2 rounded-xl bg-brand-50 px-3 py-2.5 text-xs leading-relaxed text-brand-800 dark:bg-brand-900/40 dark:text-brand-200">
                    <span class="material-icons-round shrink-0 text-sm" aria-hidden="true">workspace_premium</span>
                    <span>Barang bernilai tinggi memerlukan persetujuan Laboran dan <strong>Wakasek Sarpras</strong>. Tanda tangan digital bersifat opsional.</span>
                </div>
            @endif
        </div>

        {{-- Kalender ketersediaan --}}
        <div class="order-3 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:order-none">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h3 class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600" aria-hidden="true">calendar_month</span> Ketersediaan
                </h3>
                <div class="flex shrink-0 items-center gap-1">
                    <button wire:click="prevMonth" aria-label="Bulan sebelumnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><span class="material-icons-round text-lg" aria-hidden="true">chevron_left</span></button>
                    <span class="w-24 text-center text-xs font-semibold text-slate-700 dark:w-32 dark:text-slate-200 sm:w-32">{{ $calendar['label'] }}</span>
                    <button wire:click="nextMonth" aria-label="Bulan berikutnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><span class="material-icons-round text-lg" aria-hidden="true">chevron_right</span></button>
                </div>
            </div>

            @if (count($cart) === 0)
                <p class="rounded-xl bg-slate-50 px-3 py-6 text-center text-xs text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Pilih barang untuk melihat jadwal ketersediaannya.</p>
            @else
                <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                        <div>{{ $d }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-1">
                    @for ($i = 0; $i < $calendar['leading']; $i++)
                        <div></div>
                    @endfor
                    @foreach ($calendar['days'] as $d)
                        <div @class([
                            'rounded-lg border py-1.5 text-xs font-semibold',
                            'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' => $d['busy'],
                            'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300' => ! $d['busy'],
                        ]) title="{{ $d['busy'] ? 'Sudah dipesan atau dipinjam' : 'Tersedia' }}"
                             aria-label="Tanggal {{ $d['day'] }}: {{ $d['busy'] ? 'sudah dipesan atau dipinjam' : 'tersedia' }}">
                            {{ $d['day'] }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-slate-600 dark:text-slate-300">
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-950"></span> Tersedia</span>
                    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-red-300 bg-red-100 dark:border-red-700 dark:bg-red-950"></span> Dipesan atau dipinjam</span>
                </div>
                @if ($calendar['busyRanges'])
                    <ul class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-300">
                        @foreach ($calendar['busyRanges'] as $range)
                            <li class="flex items-start gap-1"><span class="material-icons-round text-sm text-red-400">event_busy</span> {{ $range }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>

    {{-- Kanan: form pengajuan --}}
    <div class="order-4 lg:order-none lg:col-span-2 lg:flex" id="loan-request-form">
        <div class="flex w-full flex-col rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-brand-600" aria-hidden="true">edit_note</span> 3. Detail pengajuan
            </h3>

            @if ($error)
                <div class="mb-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <span class="material-icons-round text-lg shrink-0">error</span>
                    <span>{{ $error }}</span>
                </div>
            @endif

            <div class="flex flex-1 flex-col space-y-4">
                @if ($canChooseBorrower)
                    <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-900 dark:bg-sky-950/30">
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Siapa yang meminjam barang? *</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-950">
                                <input type="radio" name="borrower_mode" value="SELF" wire:model.live="borrower_mode" class="text-brand-600 focus:ring-brand-500">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Diri sendiri</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-950">
                                <input type="radio" name="borrower_mode" value="OTHER" wire:model.live="borrower_mode" class="text-brand-600 focus:ring-brand-500">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Orang lain</span>
                            </label>
                        </div>

                        @if ($borrower_mode === 'SELF')
                            <p class="mt-2 text-[11px] text-slate-600 dark:text-slate-300">
                                Peminjam: <strong>{{ auth()->user()->displayName() }}</strong> — identitas diambil otomatis dari akun Anda.
                            </p>
                        @else
                            <div class="mt-3 space-y-2">
                                <div>
                                    <label for="borrower-search" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cari akun peminjam (opsional)</label>
                                    <input id="borrower-search" type="text" wire:model.live.debounce.300ms="borrower_search"
                                           placeholder="Nama, email, kelas, atau unit kerja"
                                           class="mt-1 w-full rounded-lg border-slate-300 bg-white text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                </div>

                                @if ($borrowerAccounts->isNotEmpty())
                                    <div class="max-h-32 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-1 dark:border-slate-700">
                                        @foreach ($borrowerAccounts as $account)
                                            <button type="button" wire:key="borrower-{{ $account->id }}" wire:click="selectBorrower({{ $account->id }})"
                                                    class="flex w-full items-start justify-between gap-2 rounded-md px-2 py-1.5 text-left text-[11px] transition hover:bg-brand-50 dark:hover:bg-slate-800">
                                                <span class="min-w-0">
                                                    <span class="block truncate font-semibold text-slate-700 dark:text-slate-200">{{ $account->displayName() }}</span>
                                                    <span class="block truncate text-slate-500 dark:text-slate-400">{{ $account->email ?: \App\Enums\Role::tryFrom($account->role)?->label() }}</span>
                                                </span>
                                                <span class="material-icons-round shrink-0 text-sm text-brand-600">person_add</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                <div>
                                    <label for="borrower-name" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama peminjam *</label>
                                    <input id="borrower-name" type="text" wire:model.live="borrower_name" maxlength="255"
                                           placeholder="Nama siswa/guru/pihak yang meminjam"
                                           class="mt-1 w-full rounded-lg border-slate-300 bg-white text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                </div>

                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div>
                                        <label for="borrower-category" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</label>
                                        <select id="borrower-category" wire:model.live="borrower_category" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-xs text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                                            <option value="">— tidak ditentukan —</option>
                                            @foreach ($borrowerCategories as $categoryOption)
                                                <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="borrower-class" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kelas (siswa)</label>
                                        <input id="borrower-class" type="text" wire:model.live="borrower_class" maxlength="50" placeholder="Contoh: X RPL 1"
                                               class="mt-1 w-full rounded-lg border-slate-300 bg-white text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="borrower-work-unit" class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Unit kerja (guru/staf)</label>
                                        <input id="borrower-work-unit" type="text" wire:model.live="borrower_work_unit" maxlength="100" placeholder="Contoh: Tata Usaha"
                                               class="mt-1 w-full rounded-lg border-slate-300 bg-white text-xs text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    </div>
                                </div>

                                @if ($borrower_user_id !== '')
                                    <div class="flex items-center justify-between gap-2 rounded-lg bg-white px-2.5 py-1.5 text-[11px] dark:bg-slate-950">
                                        <span class="text-slate-600 dark:text-slate-300">Terhubung ke akun #{{ $borrower_user_id }}</span>
                                        <button type="button" wire:click="clearBorrower" class="inline-flex min-h-7 items-center justify-center rounded-md border border-red-200 bg-red-50 px-2 text-[10px] font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">Lepas</button>
                                    </div>
                                @endif
                                <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">Peminjam tanpa akun tetap dapat dicatat. Data identitas disimpan sebagai snapshot pada transaksi.</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        Peminjam: <strong>{{ auth()->user()->displayName() }}</strong>. Akun peminjam hanya dapat mengajukan untuk dirinya sendiri.
                    </div>
                @endif

                <div>
                    <x-input-label value="Tujuan peminjaman *" />
                    <textarea wire:model.live="purpose" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Contoh: ujian praktik atau kegiatan sekolah"></textarea>
                </div>

                <div>
                    <x-input-label value="Waktu mulai peminjaman *" />
                    <input type="datetime-local" wire:model.live="start_date"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <x-input-label value="Waktu pengembalian *" />
                    <input type="datetime-local" wire:model.live="due_date"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>

                <div>
                    <x-input-label value="Kegiatan (opsional)" />
                    <select wire:model.live="event_id" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-700 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                        <option value="">Tanpa kegiatan (peminjaman biasa)</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}">{{ $event->name }} ({{ $event->start_date->format('d/m') }}–{{ $event->end_date->format('d/m/Y') }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Peminjaman massal terikat satu kegiatan → satu paket BAST.</p>
                </div>

                <p class="text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    @if (auth()->user()->isMember())
                        Permintaan akan ditinjau oleh petugas. Barang bernilai tinggi memerlukan persetujuan berjenjang dari Laboran lalu Wakasek. Dokumen dapat dicetak untuk ditandatangani secara fisik.
                    @else
                        Barang bernilai tinggi melewati persetujuan Laboran lalu Wakasek. Tanda tangan digital opsional — dokumen dapat ditandatangani secara fisik.
                    @endif
                </p>

                <button wire:click="submit" wire:loading.attr="disabled"
                        class="mt-auto inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900">
                    <span wire:loading.remove wire:target="submit" class="material-icons-round text-lg">send</span>
                    <span wire:loading wire:target="submit" class="material-icons-round text-lg animate-spin">progress_activity</span>
                    Kirim pengajuan
                </button>
            </div>
        </div>

        @if ($cart !== [])
            <a href="#loan-request-form" x-data="{ visible: true }" x-show="visible" @click="visible = false"
               class="fixed inset-x-4 bottom-20 z-30 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand-900/20 transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 md:hidden">
                <span class="material-icons-round text-lg" aria-hidden="true">arrow_downward</span>
                Lanjut ke formulir · {{ count($cart) }} jenis barang
            </a>
        @endif
    </div>
</div>

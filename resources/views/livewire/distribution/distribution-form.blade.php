<div>
    @if (session('success'))
        <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <form wire:submit="submit" class="lg:col-span-2 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-slate-100">Pilih barang yang didistribusikan</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2" x-data="{
                        catalogOpen: false,
                        catalogQuery: '',
                        catalogHighlight: 0,
                        catalogOptions: {{ \Illuminate\Support\Js::from($catalogs->map(fn ($catalogOption) => ['id' => (string) $catalogOption->id, 'name' => $catalogOption->name, 'category_name' => $catalogOption->category?->name])->values()) }},
                        get filteredCatalogOptions() {
                            const term = this.catalogQuery.trim().toLowerCase();
                            if (term === '') return this.catalogOptions;
                            return this.catalogOptions.filter((option) =>
                                option.name.toLowerCase().includes(term) ||
                                (option.category_name || '').toLowerCase().includes(term)
                            );
                        },
                        get selectedCatalogOption() {
                            return this.catalogOptions.find((option) => option.id === String($wire.catalogId));
                        },
                        chooseCatalog(id) {
                            $wire.set('catalogId', String(id));
                            this.catalogOpen = false;
                            this.catalogQuery = '';
                        },
                        clearCatalog() {
                            $wire.set('catalogId', '');
                            this.catalogOpen = false;
                            this.catalogQuery = '';
                        },
                        chooseCatalogHighlighted() {
                            const option = this.filteredCatalogOptions[this.catalogHighlight];
                            if (option) this.chooseCatalog(option.id);
                        },
                        catalogNext() {
                            this.catalogOpen = true;
                            this.catalogHighlight = (this.catalogHighlight + 1) % Math.max(1, this.filteredCatalogOptions.length);
                        },
                        catalogPrev() {
                            this.catalogOpen = true;
                            const total = Math.max(1, this.filteredCatalogOptions.length);
                            this.catalogHighlight = (this.catalogHighlight - 1 + total) % total;
                        },
                    }" @click.outside="catalogOpen = false">
                    <label for="distribution-catalog" class="block text-sm font-medium text-slate-700 dark:text-slate-300">1. Katalog barang *</label>
                    <div class="relative mt-1">
                        <input id="distribution-catalog" type="text" x-model="catalogQuery"
                               @focus="catalogQuery = ''; catalogOpen = true"
                               @click="catalogOpen = true; catalogHighlight = 0"
                               @input="catalogOpen = true; catalogHighlight = 0"
                               @keydown.arrow-down.prevent="catalogNext()"
                               @keydown.arrow-up.prevent="catalogPrev()"
                               @keydown.enter.prevent="chooseCatalogHighlighted()"
                               @keydown.escape="catalogOpen = false"
                               @blur="catalogQuery = ''"
                               :placeholder="selectedCatalogOption ? selectedCatalogOption.name + (selectedCatalogOption.category_name ? ' — ' + selectedCatalogOption.category_name : '') : 'Ketik nama atau kategori katalog…'"
                               autocomplete="off" role="combobox" aria-autocomplete="list"
                               aria-controls="distribution-catalog-options" :aria-expanded="catalogOpen.toString()"
                               class="block w-full rounded-xl border-slate-300 bg-white pr-16 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button type="button" x-show="$wire.catalogId" x-cloak @mousedown.prevent="clearCatalog()"
                                aria-label="Hapus pilihan katalog"
                                class="absolute right-8 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            <span class="material-icons-round text-base">close</span>
                        </button>
                        <span x-show="!$wire.catalogId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">expand_more</span>
                        <span x-show="$wire.catalogId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">search</span>

                        <ul x-show="catalogOpen" x-cloak id="distribution-catalog-options" role="listbox"
                            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <li role="option" @mousedown.prevent="clearCatalog()" :aria-selected="!$wire.catalogId"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                                — pilih katalog —
                            </li>
                            <template x-for="(option, index) in filteredCatalogOptions" :key="option.id">
                                <li role="option" @mousedown.prevent="chooseCatalog(option.id)"
                                    @mouseenter="catalogHighlight = index"
                                    :aria-selected="String($wire.catalogId) === option.id"
                                    :class="catalogHighlight === index ? 'bg-brand-50 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200' : 'text-slate-700 dark:text-slate-200'"
                                    class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50 dark:hover:bg-brand-900/40">
                                    <span class="block font-medium" x-text="option.name"></span>
                                    <span x-show="option.category_name" class="block text-[11px] text-slate-500 dark:text-slate-400" x-text="option.category_name"></span>
                                </li>
                            </template>
                            <li x-show="filteredCatalogOptions.length === 0" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                Tidak ada katalog yang cocok.
                            </li>
                        </ul>
                    </div>
                    @error('catalogId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="distribution-specification" class="block text-sm font-medium text-slate-700 dark:text-slate-300">2. Merek &amp; tipe/model *</label>
                    <select id="distribution-specification" wire:model.live="specificationId"
                            @disabled($specifications->isEmpty())
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih spesifikasi —</option>
                        @foreach ($specifications as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }} — tersedia {{ $option['available'] }} unit</option>
                        @endforeach
                    </select>
                    @if ($catalogId !== '' && $specifications->isEmpty())
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Katalog ini belum memiliki spesifikasi/unit yang dapat didistribusikan.</p>
                    @endif
                    @error('specificationId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="distribution-quantity" class="block text-sm font-medium text-slate-700 dark:text-slate-300">3. Jumlah unit *</label>
                    <input id="distribution-quantity" type="number" min="1" wire:model.live="quantity"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    @error('quantity') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div x-data="{
                        roomOpen: false,
                        roomQuery: '',
                        roomHighlight: 0,
                        roomOptions: {{ \Illuminate\Support\Js::from($rooms->map(fn ($roomOption) => [
                            'id' => (string) $roomOption->id,
                            'name' => $roomOption->name,
                            'code' => 'Gedung '.$roomOption->building_letter.$roomOption->floor.'-'.str_pad((string) $roomOption->room_no, 2, '0', STR_PAD_LEFT),
                        ])->values()) }},
                        get filteredRoomOptions() {
                            const term = this.roomQuery.trim().toLowerCase();
                            if (term === '') return this.roomOptions;
                            return this.roomOptions.filter((option) =>
                                option.name.toLowerCase().includes(term) ||
                                option.code.toLowerCase().includes(term)
                            );
                        },
                        get selectedRoomOption() {
                            return this.roomOptions.find((option) => option.id === String($wire.toRoomId));
                        },
                        chooseRoom(id) {
                            $wire.set('toRoomId', String(id));
                            this.roomOpen = false;
                            this.roomQuery = '';
                        },
                        clearRoom() {
                            $wire.set('toRoomId', '');
                            this.roomOpen = false;
                            this.roomQuery = '';
                        },
                        chooseRoomHighlighted() {
                            const option = this.filteredRoomOptions[this.roomHighlight];
                            if (option) this.chooseRoom(option.id);
                        },
                        roomNext() {
                            this.roomOpen = true;
                            this.roomHighlight = (this.roomHighlight + 1) % Math.max(1, this.filteredRoomOptions.length);
                        },
                        roomPrev() {
                            this.roomOpen = true;
                            const total = Math.max(1, this.filteredRoomOptions.length);
                            this.roomHighlight = (this.roomHighlight - 1 + total) % total;
                        },
                    }" @click.outside="roomOpen = false">
                    <label for="distribution-room" class="block text-sm font-medium text-slate-700 dark:text-slate-300">4. Lokasi tujuan *</label>
                    <div class="relative mt-1">
                        <input id="distribution-room" type="text" x-model="roomQuery"
                               @focus="roomQuery = ''; roomOpen = true"
                               @click="roomOpen = true; roomHighlight = 0"
                               @input="roomOpen = true; roomHighlight = 0"
                               @keydown.arrow-down.prevent="roomNext()"
                               @keydown.arrow-up.prevent="roomPrev()"
                               @keydown.enter.prevent="chooseRoomHighlighted()"
                               @keydown.escape="roomOpen = false"
                               @blur="roomQuery = ''"
                               :placeholder="selectedRoomOption ? selectedRoomOption.name + ' — ' + selectedRoomOption.code : 'Ketik nama atau gedung ruangan…'"
                               autocomplete="off" role="combobox" aria-autocomplete="list"
                               aria-controls="distribution-room-options" :aria-expanded="roomOpen.toString()"
                               class="block w-full rounded-xl border-slate-300 bg-white pr-16 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button type="button" x-show="$wire.toRoomId" x-cloak @mousedown.prevent="clearRoom()"
                                aria-label="Hapus pilihan lokasi tujuan"
                                class="absolute right-8 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            <span class="material-icons-round text-base">close</span>
                        </button>
                        <span x-show="!$wire.toRoomId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">expand_more</span>
                        <span x-show="$wire.toRoomId" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 material-icons-round text-base text-slate-400" aria-hidden="true">search</span>

                        <ul x-show="roomOpen" x-cloak id="distribution-room-options" role="listbox"
                            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <li role="option" @mousedown.prevent="clearRoom()" :aria-selected="!$wire.toRoomId"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                                — pilih ruangan —
                            </li>
                            <template x-for="(option, index) in filteredRoomOptions" :key="option.id">
                                <li role="option" @mousedown.prevent="chooseRoom(option.id)"
                                    @mouseenter="roomHighlight = index"
                                    :aria-selected="String($wire.toRoomId) === option.id"
                                    :class="roomHighlight === index ? 'bg-brand-50 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200' : 'text-slate-700 dark:text-slate-200'"
                                    class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50 dark:hover:bg-brand-900/40">
                                    <span class="block font-medium" x-text="option.name"></span>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400" x-text="option.code"></span>
                                </li>
                            </template>
                            <li x-show="filteredRoomOptions.length === 0" class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">
                                Tidak ada ruangan yang cocok.
                            </li>
                        </ul>
                    </div>
                    @error('toRoomId') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="distribution-note" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Catatan</label>
                    <textarea id="distribution-note" wire:model="note" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Contoh: kebutuhan praktik kelas XI"></textarea>
                    @error('note') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                <p class="font-semibold text-slate-800 dark:text-slate-100">Ringkasan sebelum disimpan</p>
                <dl class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Katalog</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $catalogs->firstWhere('id', (int) $catalogId)?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Merek / tipe</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $specifications->firstWhere('id', (int) $specificationId)['label'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Stok spesifikasi</dt><dd class="font-bold text-slate-900 dark:text-white">{{ $stockAvailable }} unit</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Jumlah distribusi</dt><dd class="font-bold text-slate-900 dark:text-white">{{ $quantity }} unit</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Tujuan</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $selectedRoom?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500 dark:text-slate-400">Sisa setelah distribusi</dt><dd class="font-medium {{ $stockAvailable - $quantity < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $stockAvailable - $quantity }} unit</dd></div>
                </dl>
                <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                    Stok dihitung hanya dari unit spesifikasi ini yang aktif, tidak dipinjam, dan belum pernah didistribusikan. Merek lain tidak ikut dihitung.
                </p>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    <span class="material-icons-round text-lg">local_shipping</span> Distribusikan
                </button>
                <span wire:loading class="text-xs text-slate-500 dark:text-slate-400">Memproses…</span>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Stok per spesifikasi</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Stok dihitung terpisah untuk setiap merek dan tipe/model.</p>

            <div class="mt-3 space-y-2">
                @forelse ($specifications as $option)
                    <div class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $option['label'] }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">Siap didistribusikan: {{ $option['available'] }} unit</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pilih katalog untuk melihat stok tiap spesifikasi.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Riwayat distribusi terakhir</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Setiap baris terhubung ke unit inventaris yang dipindahkan.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Waktu</th>
                        <th class="px-4 py-3 text-left">Katalog</th>
                        <th class="px-4 py-3 text-left">Merek / tipe</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                        <th class="px-4 py-3 text-left">Tujuan</th>
                        <th class="px-4 py-3 text-left">Petugas</th>
                        <th class="px-4 py-3 text-right">Unit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($recent as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $row->distributed_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->catalog?->name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->specification?->displayName() }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $row->quantity }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{{ $row->toRoom?->name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $row->actor?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500 dark:text-slate-400">
                                <a href="{{ route('specifications.show', $row->specification_id) }}" class="font-semibold text-brand-700 hover:underline dark:text-brand-300">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada distribusi tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

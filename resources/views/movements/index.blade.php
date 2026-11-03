<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Mutasi &amp; Pindah Barang</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Satu transaksi dapat memindahkan unit ke beberapa lokasi tujuan sekaligus</p>
        </div>
    </x-slot>

    @if (session('success'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    {{-- Filter: 6 kolom pada layar lebar, menumpuk rapi pada layar sempit --}}
    <form method="GET" action="{{ route('movements.index') }}" class="mb-5 rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
                <span class="material-icons-round text-base text-brand-600">filter_alt</span>Filter Unit</h3>
            <a href="{{ route('movements.index') }}" class="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Hapus filter</a>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Cari kode unit</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="G1-02-12"
                       class="w-full rounded-xl border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Jenis barang</label>
                <select name="catalog" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($catalogs as $catalog)
                        <option value="{{ $catalog->id }}" @selected($filters['catalog'] == $catalog->id)>{{ $catalog->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Gedung</label>
                <select name="building" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($buildings as $building)
                        <option value="{{ $building->code }}" @selected(strtoupper($filters['building']) === $building->code)>{{ $building->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Lantai</label>
                <select name="floor" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @for ($floor = 1; $floor <= 9; $floor++)
                        <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Lantai {{ $floor }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Ruangan</label>
                <select name="room" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room'] == $room->id)>{{ $room->code }} — {{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Kondisi</label>
                <select name="condition" class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Semua</option>
                    @foreach ($conditions as $condition)
                        <option value="{{ $condition->value }}" @selected($filters['condition'] === $condition->value)>{{ $condition->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="inline-flex min-h-9 items-center rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">Terapkan filter</button>
        </div>
    </form>

    <form method="POST" action="{{ route('movements.store') }}"
          x-data="movementSelection({ unitOptions: {{ \Illuminate\Support\Js::from($unitOptions) }}, roomLabels: {{ \Illuminate\Support\Js::from($roomLabels) }} })">
        @csrf

        <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
            {{-- Daftar unit: tinggi dibatasi + header tabel menempel --}}
            <div class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Unit ({{ $units->total() }})</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="selectAll()" :disabled="selected.length === pageUnitIds.length"
                                class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Pilih semua di halaman
                        </button>
                        <button type="button" @click="clearSelection()" :disabled="selected.length === 0"
                                class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                            Kosongkan pilihan
                        </button>
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><strong x-text="selected.length"></strong> unit dipilih</span>
                    </div>
                    <p class="w-full text-[11px] font-normal text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
                </div>
                <div class="max-h-[60vh] overflow-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="w-10 px-3 py-2.5"></th>
                                <th class="px-3 py-2.5 text-left">Kode Inventaris</th>
                                <th class="px-3 py-2.5 text-left">Barang</th>
                                <th class="px-3 py-2.5 text-left">Lokasi Asal</th>
                                <th class="px-3 py-2.5 text-left">Kondisi</th>
                                <th class="px-3 py-2.5 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($units as $unit)
                                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                    <td class="px-3 py-2.5">
                                        <input type="checkbox" value="{{ $unit->id }}" x-model="selected"
                                               aria-label="Pilih unit {{ $unit->inventory_code }}"
                                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                                    </td>
                                    <td class="px-3 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-medium text-slate-700 dark:text-slate-200">{{ $unit->catalog?->name }}</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $unit->catalog?->category?->name }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $unit->locationLabel() }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->condition === \App\Enums\Condition::BAIK ? 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">{{ $unit->condition->label() }}</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-10 text-center text-sm text-slate-400">Tidak ada unit yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $units->links() }}</div>
            </div>

            {{-- Panel tujuan: menempel saat halaman di-scroll --}}
            <div class="space-y-4 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm lg:sticky lg:top-20 dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">move_to_inbox</span> Tujuan perpindahan
                </h3>

                <div>
                    <x-input-label value="Ringkasan unit terpilih" />
                    <div class="mt-1 min-h-[42px] rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                        <span x-show="selected.length === 0">Belum ada unit yang belum ditetapkan.</span>
                        <span x-show="selected.length > 0"><strong x-text="selected.length"></strong> unit belum ditetapkan ke tujuan.</span>
                        <span class="mt-1 block" x-show="destinations.length > 0"><strong x-text="assignedCount"></strong> unit sudah masuk <span x-text="destinations.length"></span> kelompok tujuan.</span>
                    </div>
                </div>

                <div>
                    <x-input-label for="destination_room" value="Ruangan Tujuan *" />
                    <select id="destination_room" x-model="draftRoom"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">— pilih ruangan tujuan —</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }} (Lt.{{ $room->floor }})</option>
                        @endforeach
                    </select>
                    <button type="button" @click="assign()" :disabled="selected.length === 0 || draftRoom === ''"
                            class="mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-slate-800 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-700">
                        <span class="material-icons-round text-base">playlist_add</span> Tetapkan unit terpilih ke tujuan ini
                    </button>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Tambahkan kelompok tujuan lain dengan memilih unit berbeda lalu menetapkan ruangan lain.</p>
                </div>

                <div>
                    <x-input-label value="Kelompok tujuan" />
                    <div class="mt-1 space-y-2">
                        <p x-show="destinations.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">
                            Belum ada kelompok tujuan. Unit yang dipilih belum akan dipindahkan.
                        </p>

                        <template x-for="(group, groupIndex) in destinations" :key="group.roomId">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <div class="mb-1.5 flex items-start justify-between gap-2">
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100">
                                        Tujuan: <span x-text="roomLabels[group.roomId] || group.roomId"></span>
                                    </p>
                                    <button type="button" @click="removeGroup(groupIndex)" class="text-[11px] font-semibold text-red-600 hover:underline dark:text-red-400">Hapus</button>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="unitId in group.unitIds" :key="`${group.roomId}-${unitId}`">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200">
                                            <span x-text="unitOptions[unitId]?.code || unitId"></span>
                                            <button type="button" @click="removeUnit(groupIndex, unitId)" class="text-slate-400 hover:text-red-600" aria-label="Keluarkan unit dari tujuan">×</button>
                                        </span>
                                    </template>
                                </div>

                                {{-- Detail mutasi per unit dengan lokasi tujuan masing-masing. --}}
                                <input type="hidden" :name="`destinations[${groupIndex}][room_id]`" :value="group.roomId">
                                <template x-for="unitId in group.unitIds" :key="`input-${group.roomId}-${unitId}`">
                                    <input type="hidden" :name="`destinations[${groupIndex}][unit_ids][]`" :value="unitId">
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <x-input-label for="note" value="Catatan perpindahan" />
                    <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">{{ old('note') }}</textarea>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    Setiap perpindahan menerbitkan kode inventaris baru sesuai ruangan tujuan. Kode lama disimpan sebagai riwayat dan tetap dapat ditelusuri. Cetak ulang label setelah pindah.
                </div>

                <button type="submit" :disabled="selected.length > 0 || destinations.length === 0"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:disabled:bg-slate-700">
                    <span class="material-icons-round text-lg">swap_horiz</span> Simpan perpindahan
                </button>
                <p x-show="selected.length > 0" class="text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                    Tetapkan dulu semua unit yang dipilih ke salah satu kelompok tujuan.
                </p>
            </div>
        </div>
    </form>

    <div class="mt-6 rounded-2xl border border-sky-100 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h3 class="mb-3 flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-slate-100">
            <span class="material-icons-round text-brand-600">route</span> Riwayat perpindahan barang ({{ $movements->total() }})
        </h3>
        <p class="mb-2 text-[11px] text-slate-400 md:hidden dark:text-slate-500">Geser tabel ke samping untuk melihat semua kolom.</p>
        <div class="max-h-[45vh] overflow-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2.5 text-left">No. Mutasi</th>
                        <th class="px-3 py-2.5 text-left">Unit</th>
                        <th class="px-3 py-2.5 text-left">Kode Lama → Baru</th>
                        <th class="px-3 py-2.5 text-left">Asal → Tujuan</th>
                        <th class="px-3 py-2.5 text-left">Petugas</th>
                        <th class="px-3 py-2.5 text-left">Waktu</th>
                        <th class="px-3 py-2.5 text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movements as $movement)
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <td class="px-3 py-2.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $movement->movement_no ?: '—' }}</td>
                            <td class="px-3 py-2.5">
                                <a href="{{ route('units.show', $movement->unit) }}" class="font-medium text-slate-800 hover:text-brand-700 dark:text-slate-100 dark:hover:text-brand-300">{{ $movement->unit?->catalog?->name }}</a>
                                <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $movement->unit?->inventory_code }}</div>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $movement->old_code }} → {{ $movement->new_code }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ $movement->fromRoom?->code }} → {{ $movement->toRoom?->code }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->mover?->name }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->moved_at?->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-sm text-slate-400">Belum ada perpindahan yang tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>

    @push('scripts')
    <script>
        function movementSelection(config) {
            return {
                unitOptions: config.unitOptions || {},
                roomLabels: config.roomLabels || {},
                selected: [],
                destinations: [],
                draftRoom: '',

                get pageUnitIds() {
                    return Object.keys(this.unitOptions);
                },

                get assignedCount() {
                    return this.destinations.reduce((total, group) => total + group.unitIds.length, 0);
                },

                selectAll() {
                    this.selected = [...this.pageUnitIds];
                },

                clearSelection() {
                    this.selected = [];
                },

                assign() {
                    const roomId = String(this.draftRoom || '');

                    if (roomId === '' || this.selected.length === 0) {
                        return;
                    }

                    const ids = this.selected.map(String);

                    // Unit yang dipindah ke tujuan baru tidak boleh tersisa di kelompok lama.
                    this.destinations.forEach((group) => {
                        group.unitIds = group.unitIds.filter((unitId) => !ids.includes(String(unitId)));
                    });
                    this.destinations = this.destinations.filter((group) => group.unitIds.length > 0);

                    let group = this.destinations.find((item) => String(item.roomId) === roomId);

                    if (!group) {
                        group = { roomId: roomId, unitIds: [] };
                        this.destinations.push(group);
                    }

                    ids.forEach((unitId) => {
                        if (!group.unitIds.map(String).includes(unitId)) {
                            group.unitIds.push(unitId);
                        }
                    });

                    this.selected = [];
                },

                removeUnit(groupIndex, unitId) {
                    const group = this.destinations[groupIndex];

                    if (!group) {
                        return;
                    }

                    group.unitIds = group.unitIds.filter((id) => String(id) !== String(unitId));

                    if (group.unitIds.length === 0) {
                        this.destinations.splice(groupIndex, 1);
                    }

                    if (!this.selected.map(String).includes(String(unitId))) {
                        this.selected.push(String(unitId));
                    }
                },

                removeGroup(groupIndex) {
                    const group = this.destinations[groupIndex];

                    if (!group) {
                        return;
                    }

                    group.unitIds.forEach((unitId) => {
                        if (!this.selected.map(String).includes(String(unitId))) {
                            this.selected.push(String(unitId));
                        }
                    });

                    this.destinations.splice(groupIndex, 1);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>

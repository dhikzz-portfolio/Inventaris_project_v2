<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Serah Terima Barang</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pinjaman #{{ $loan->id }} — {{ $loan->purpose }}</p>
            </div>
            <a href="{{ route('loans.show', $loan) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Kembali ke detail</a>
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

    @php
        $limits = collect($availableUnits)->mapWithKeys(fn ($bucket, $id) => [(int) $id => (int) $bucket['qty_borrowed']])->all();
        $unitMeta = [];
        foreach ($availableUnits as $loanItemId => $bucket) {
            foreach ($bucket['units'] as $unit) {
                $unitMeta[(int) $unit->id] = (int) $loanItemId;
            }
        }
        $totalCapacity = (int) array_sum($limits);

        $categories = collect($availableUnits)->pluck('item.category')->filter()->unique('id')->sortBy('name')->values();
        $catalogs = collect($availableUnits)->pluck('item')->filter()->unique('id')->sortBy('name')->values();
        $specifications = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('specification'))
            ->filter()
            ->unique('id')
            ->sortBy(fn ($spec) => $spec->displayName())
            ->values();
        $buildings = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('room'))
            ->filter()
            ->map(fn ($room) => $room->buildingCode())
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $rooms = collect($availableUnits)
            ->flatMap(fn ($bucket) => $bucket['units']->pluck('room'))
            ->filter()
            ->unique('id')
            ->sortBy('code')
            ->values();
    @endphp

    <div class="max-w-4xl" x-data="handoverForm({
            limits: {{ \Illuminate\Support\Js::from($limits) }},
            unitItem: {{ \Illuminate\Support\Js::from($unitMeta) }},
            scanUrl: '{{ route('loans.handover-scan', $loan) }}',
            totalCapacity: {{ $totalCapacity }},
        })" x-init="init()">
        <p class="mb-4 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm leading-relaxed text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100">
            Serah terima barang ke peminjam dan pilih unit inventaris fisik yang diserahkan.
            Setiap unit yang dipilih akan tercatat kode inventaris, kondisi saat keluar, dan status akan berubah menjadi <strong class="text-blue-700">Sedang Dipinjam</strong>.
        </p>

        {{-- Pencarian & filter unit --}}
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="handover-search" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Cari unit</label>
                    <input id="handover-search" type="search" x-model="search" @input.debounce.200ms="applyFilters()"
                           placeholder="Nama, spesifikasi, kode, gedung, ruangan…"
                           class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div>
                    <label for="handover-filter-category" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Kategori</label>
                    <select id="handover-filter-category" x-model="filters.category" @change="applyFilters()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="handover-filter-catalog" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Katalog</label>
                    <select id="handover-filter-catalog" x-model="filters.catalog" @change="applyFilters()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua katalog</option>
                        @foreach ($catalogs as $catalog)
                            <option value="{{ $catalog->id }}">{{ $catalog->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="handover-filter-spec" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Spesifikasi</label>
                    <select id="handover-filter-spec" x-model="filters.spec" @change="applyFilters()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua spesifikasi</option>
                        @foreach ($specifications as $specification)
                            <option value="{{ $specification->id }}">{{ $specification->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="handover-filter-building" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Gedung</label>
                    <select id="handover-filter-building" x-model="filters.building" @change="applyFilters()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua gedung</option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building }}">Gedung {{ $building }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="handover-filter-room" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Ruangan</label>
                    <select id="handover-filter-room" x-model="filters.room" @change="applyFilters()"
                            class="w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Semua ruangan</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                        <span x-text="selected.length"></span> unit terpilih
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span x-text="Math.max(0, totalCapacity - selected.length)"></span> unit masih dapat diserahkan
                    </span>
                    <span class="text-slate-500 dark:text-slate-400" x-show="totalFiltersActive()">
                        Menampilkan <strong x-text="totalVisible"></strong> unit sesuai filter
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="selectAll()" :disabled="selected.length >= totalCapacity"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-icons-round text-base">done_all</span> Pilih Semua Unit yang Dapat Diserahkan
                    </button>
                    <button type="button" @click="clearAll()" x-show="selected.length > 0"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base">remove_done</span> Batal Pilih Semua
                    </button>
                    <button type="button" @click="toggleScanner()"
                            class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <span class="material-icons-round text-base">qr_code_scanner</span> Scan QR Unit
                    </button>
                </div>
            </div>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                Pilih Semua hanya memilih unit yang memenuhi syarat pada peminjaman ini dan berhenti pada batas jumlah setiap barang.
                Pilihan yang sudah dibuat tetap tersimpan ketika filter berubah.
            </p>
        </div>

        {{-- Panel scan QR --}}
        <div x-show="scannerOpen" x-cloak class="mb-4 rounded-2xl border border-sky-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Scan QR Unit</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Hasil scan langsung dimasukkan ke daftar pilihan, tidak mengalihkan halaman.</p>
                </div>
                <button type="button" @click="toggleScanner()" aria-label="Tutup panel scan"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-lg">close</span>
                </button>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div id="handover-qr-reader" class="flex min-h-[180px] items-center justify-center overflow-hidden rounded-lg bg-slate-900">
                        <span x-show="!cameraRunning" class="px-4 text-center text-sm text-slate-400">Kamera belum aktif</span>
                    </div>
                    <div class="mt-2 flex justify-center gap-2">
                        <button type="button" x-show="!cameraRunning" @click="startCamera()"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                            <span class="material-icons-round text-base">photo_camera</span> Aktifkan Kamera
                        </button>
                        <button type="button" x-show="cameraRunning" @click="stopCamera()"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
                            <span class="material-icons-round text-base">photo_camera_back</span> Matikan Kamera
                        </button>
                    </div>
                </div>
                <div>
                    <label for="handover-scan-code" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Input manual kode inventaris</label>
                    <div class="flex gap-2">
                        <input id="handover-scan-code" type="text" x-model="scanCode" @keydown.enter.prevent="submitScan()"
                               placeholder="Contoh: G1-02-12" autocomplete="off"
                               class="min-w-0 flex-1 rounded-xl border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button type="button" @click="submitScan()"
                                class="inline-flex min-h-10 items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Cari</button>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                        Kamera tidak tersedia atau izin ditolak? Masukkan kode inventaris secara manual — fallback ini selalu aktif.
                        Pemindaian hanya memasukkan unit ke daftar pilihan; penyerahan tetap disimpan lewat tombol di bawah.
                    </p>
                </div>
            </div>

            <p x-show="scanMessage" class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200" x-text="scanMessage" role="status"></p>
            <p x-show="scanError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" x-text="scanError" role="alert"></p>
        </div>

        <div x-show="totalVisible === 0" x-cloak class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            Tidak ada unit yang cocok dengan pencarian/filter.
            <button type="button" @click="resetFilters()" class="font-semibold text-blue-700 underline dark:text-blue-300">Reset filter</button>
        </div>

        <form method="POST" action="{{ route('loans.handover', $loan) }}" id="handover-form">
            @csrf

            <div class="space-y-6">
                @foreach ($availableUnits as $loanItemId => $bucket)
                    @php
                        $item = $bucket['item'];
                        $room = $bucket['room'];
                        $qtyBorrowed = $bucket['qty_borrowed'];
                    @endphp
                    <section data-item-section="{{ $loanItemId }}"
                             x-show="(visibleCount[{{ $loanItemId }}] ?? 0) > 0"
                             class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                            <div>
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $item->name }}</span>
                                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $item->internal_id }}</span>
                                @if ($item->is_high_value)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">NILAI TINGGI</span>
                                @endif
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ $item->category?->name ?: 'Tanpa kategori' }}
                                    • {{ $item->specification ?: 'Spesifikasi belum dicatat' }}
                                    @if ($item->brand || $item->model_type)
                                        • {{ trim(($item->brand ?? '').' '.($item->model_type ?? '')) }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                                <div>{{ $qtyBorrowed }} {{ $item->unit }} • Ruang asal: {{ $room?->code ?? '-' }}</div>
                                <div>Terpilih <strong x-text="countFor({{ $loanItemId }})"></strong> / {{ $qtyBorrowed }}
                                    • Sisa <strong x-text="remainingFor({{ $loanItemId }})"></strong></div>
                            </div>
                        </div>

                        @if ($bucket['units']->isEmpty())
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Tidak ada unit yang dapat diserahkan untuk barang ini.
                                Bisa karena seluruh unit sedang dipinjam, tidak aktif, atau rusak.
                            </div>
                        @else
                            <div class="max-h-[360px] divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700">
                                @foreach ($bucket['units'] as $unit)
                                    @php
                                        $unitRoom = $unit->room;
                                        $specLabel = $unit->specification?->displayName() ?: ($item->specification ?: '');
                                        $searchText = strtolower(trim(implode(' ', array_filter([
                                            $unit->inventory_code,
                                            $item->name,
                                            $item->internal_id,
                                            $specLabel,
                                            $item->brand,
                                            $item->model_type,
                                            $unit->locationLabel(),
                                            $unitRoom?->name,
                                            $unitRoom?->code,
                                            $item->category?->name,
                                        ]))));
                                    @endphp
                                    <label id="handover-unit-{{ $unit->id }}"
                                           data-unit-card
                                           data-unit-id="{{ $unit->id }}"
                                           data-item-id="{{ $loanItemId }}"
                                           data-category="{{ $item->category_id }}"
                                           data-catalog="{{ $item->id }}"
                                           data-spec="{{ $unit->specification_id }}"
                                           data-building="{{ $unitRoom?->buildingCode() }}"
                                           data-room="{{ $unitRoom?->id }}"
                                           data-search="{{ $searchText }}"
                                           class="flex cursor-pointer items-start gap-3 p-3 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                                           :class="isDisabled({{ $unit->id }}) ? 'cursor-not-allowed opacity-40' : ''">
                                        <input type="checkbox"
                                               name="unit_assignments[{{ $loanItemId }}][unit_ids][]"
                                               value="{{ $unit->id }}"
                                               x-model="selected"
                                               :disabled="isDisabled({{ $unit->id }})"
                                               class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-950">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $unit->inventory_code }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $unit->condition->isDamaged() ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">{{ $unit->condition->label() }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $unit->status->badgeClass() }}">{{ $unit->status->label() }}</span>
                                            </div>
                                            <div class="text-[12px] font-medium text-slate-700 dark:text-slate-200">{{ $item->name }}</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                                {{ $specLabel ?: 'Spesifikasi belum dicatat' }}
                                                @if ($item->brand || $item->model_type)
                                                    • {{ trim(($item->brand ?? '').' '.($item->model_type ?? '')) }}
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-600 dark:text-slate-300">
                                                {{ $unit->locationLabel() }}@if ($unitRoom?->name) • {{ $unitRoom->name }} @endif
                                            </div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Unit No. {{ $unit->unit_no }} • {{ $unit->funding_source ? 'Dana: '.$unit->funding_source : '' }}</div>
                                        </div>
                                    </label>
                                @endforeach
                                <div class="flex items-center justify-between gap-2 bg-slate-50 p-3 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <span>
                                        Dipilih <strong x-text="countFor({{ $loanItemId }})"></strong> dari {{ $qtyBorrowed }} unit yang wajib diserahkan.
                                        Unit tambahan otomatis dinonaktifkan setelah batas terpenuhi.
                                    </span>
                                    <span x-show="countFor({{ $loanItemId }}) === {{ $qtyBorrowed }}" class="text-green-700 font-semibold whitespace-nowrap">✓ lengkap</span>
                                </div>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Pilih tepat {{ $qtyBorrowed }} unit untuk barang ini. Hapus centang untuk mengganti pilihan sebelum menyimpan.
                            </p>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label value="Catatan serah terima (opsional)" />
                    <textarea name="note" rows="2"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Kondisi saat serah terima, catatan khusus..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                        <span class="material-icons-round text-lg">inventory</span>
                        Serahkan Barang + Tandai Unit Dipinjam
                    </button>
                    <a href="{{ route('loans.show', $loan) }}"
                       class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </a>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function handoverForm(config) {
                return {
                    limits: config.limits,
                    unitItem: config.unitItem,
                    scanUrl: config.scanUrl,
                    totalCapacity: config.totalCapacity,
                    selected: [],
                    search: '',
                    filters: { category: '', catalog: '', spec: '', building: '', room: '' },
                    visibleCount: {},
                    totalVisible: Object.keys(config.unitItem).length,
                    scannerOpen: false,
                    cameraRunning: false,
                    html5: null,
                    scanCode: '',
                    scanMessage: '',
                    scanError: '',

                    init() {
                        this.applyFilters();
                    },

                    countFor(itemId) {
                        const key = String(itemId);

                        return this.selected.filter((id) => String(this.unitItem[id]) === key).length;
                    },

                    remainingFor(itemId) {
                        return Math.max(0, (this.limits[itemId] || 0) - this.countFor(itemId));
                    },

                    isDisabled(unitId) {
                        const itemId = this.unitItem[unitId];

                        if (this.selected.map(String).includes(String(unitId))) {
                            return false;
                        }

                        return this.countFor(itemId) >= (this.limits[itemId] || 0);
                    },

                    selectAll() {
                        // Pilih sebanyak kuota tiap barang; tidak memaksakan unit
                        // melebihi batas penyerahan.
                        const perItem = {};
                        Object.keys(this.unitItem).forEach((unitId) => {
                            const itemId = String(this.unitItem[unitId]);
                            const limit = this.limits[itemId] || 0;
                            perItem[itemId] = perItem[itemId] || 0;

                            if (this.selected.map(String).includes(String(unitId))) {
                                perItem[itemId]++;

                                return;
                            }

                            if (perItem[itemId] < limit) {
                                this.selected.push(String(unitId));
                                perItem[itemId]++;
                            }
                        });

                        this.scanError = '';
                        this.scanMessage = 'Seluruh unit yang memenuhi syarat dipilih sesuai batas penyerahan.';
                    },

                    clearAll() {
                        this.selected = [];
                        this.scanMessage = 'Pilihan unit dibatalkan.';
                        this.scanError = '';
                    },

                    resetFilters() {
                        this.search = '';
                        this.filters = { category: '', catalog: '', spec: '', building: '', room: '' };
                        this.applyFilters();
                    },

                    totalFiltersActive() {
                        return this.search.trim() !== '' || Object.values(this.filters).some((value) => value !== '');
                    },

                    applyFilters() {
                        const term = this.search.toLowerCase().trim();
                        const counts = {};
                        let total = 0;

                        this.$root.querySelectorAll('[data-unit-card]').forEach((el) => {
                            const data = el.dataset;
                            let matches = term === '' || (data.search || '').includes(term);

                            if (matches && this.filters.category && data.category !== this.filters.category) matches = false;
                            if (matches && this.filters.catalog && data.catalog !== this.filters.catalog) matches = false;
                            if (matches && this.filters.spec && data.spec !== this.filters.spec) matches = false;
                            if (matches && this.filters.building && data.building !== this.filters.building) matches = false;
                            if (matches && this.filters.room && data.room !== this.filters.room) matches = false;

                            el.classList.toggle('hidden', !matches);

                            if (matches) {
                                const itemId = data.itemId;
                                counts[itemId] = (counts[itemId] || 0) + 1;
                                total++;
                            }
                        });

                        this.visibleCount = counts;
                        this.totalVisible = total;
                    },

                    async toggleScanner() {
                        this.scannerOpen = !this.scannerOpen;
                        this.scanError = '';
                        this.scanMessage = '';

                        if (this.scannerOpen) {
                            await this.$nextTick();
                            if (!window.Html5Qrcode) {
                                this.scanError = 'Modul kamera belum dimuat — gunakan input kode manual.';
                            }
                        } else {
                            await this.stopCamera();
                        }
                    },

                    async startCamera() {
                        this.scanError = '';

                        if (!window.Html5Qrcode) {
                            this.scanError = 'Kamera tidak tersedia — gunakan input kode manual.';

                            return;
                        }

                        try {
                            this.html5 = this.html5 || new window.Html5Qrcode('handover-qr-reader');
                            await this.html5.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: { width: 220, height: 220 } },
                                (decoded) => this.onDecoded(decoded),
                                () => {}
                            );
                            this.cameraRunning = true;
                        } catch (e) {
                            this.scanError = 'Kamera tidak tersedia atau izin ditolak. Gunakan input kode manual.';
                        }
                    },

                    async stopCamera() {
                        if (this.html5 && this.cameraRunning) {
                            try {
                                await this.html5.stop();
                            } catch (e) {}
                        }

                        this.cameraRunning = false;
                    },

                    async onDecoded(text) {
                        await this.stopCamera();
                        await this.resolveScan(text);
                    },

                    async submitScan() {
                        const value = (this.scanCode || '').trim();

                        if (value === '') {
                            this.scanError = 'Masukkan kode inventaris terlebih dahulu.';

                            return;
                        }

                        await this.resolveScan(value);
                    },

                    async resolveScan(raw) {
                        this.scanError = '';
                        this.scanMessage = '';

                        const params = new URLSearchParams();
                        params.set('code', raw);
                        this.selected.forEach((id) => params.append('selected[]', id));

                        try {
                            const response = await fetch(this.scanUrl + '?' + params.toString(), {
                                headers: { 'Accept': 'application/json' },
                            });
                            const data = await response.json();

                            if (data.status !== 'ok') {
                                this.scanError = data.message || 'QR tidak dapat diproses.';

                                return;
                            }

                            const unit = data.unit;

                            if (!this.selected.map(String).includes(String(unit.id))) {
                                this.selected.push(String(unit.id));
                            }

                            this.scanCode = '';
                            this.scanMessage = 'Unit ' + unit.inventory_code + ' berhasil ditambahkan ke daftar serah terima.';
                            this.search = '';
                            this.filters = { category: '', catalog: '', spec: '', building: '', room: '' };
                            this.applyFilters();

                            await this.$nextTick();
                            document.getElementById('handover-unit-' + unit.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } catch (e) {
                            this.scanError = 'Gagal memproses hasil scan. Periksa koneksi lalu coba lagi.';
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

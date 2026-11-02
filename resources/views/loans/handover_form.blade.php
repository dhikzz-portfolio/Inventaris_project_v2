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
            <a href="{{ route('loans.show', $loan) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">← Kembali ke detail</a>
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

    <div class="max-w-4xl">
        <p class="mb-4 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm leading-relaxed text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100">
            Serah terima barang ke peminjam dan pilih unit inventaris fisik yang diserahkan.
            Setiap unit yang dipilih akan tercatat kode inventaris, kondisi saat keluar, dan status akan berubah menjadi <strong class="text-blue-700">Sedang Dipinjam</strong>.
        </p>

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
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">NILAI TINGGI</span>
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
                                               :disabled="selected.length >= max && ! selected.includes('{{ $unit->id }}')"
                                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed dark:border-slate-600 dark:bg-slate-950">
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
                                    <span x-show="countFor({{ $loanItemId }}) === {{ $qtyBorrowed }}" class="inline-flex items-center gap-1 text-green-700 font-semibold whitespace-nowrap"><span class="material-icons-round text-sm" aria-hidden="true">check_circle</span> lengkap</span>
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
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                              placeholder="Kondisi saat serah terima, catatan khusus..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
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
                                this.scanError = 'Modul kamera belum dimuat — masukkan kode secara manual.';
                            }
                        } else {
                            await this.stopCamera();
                        }
                    },

                    async startCamera() {
                        this.scanError = '';
                        this.scanMessage = 'Menghubungkan ke kamera...';

                        try {
                            if (window.AppScanner) {
                                this.html5 = await window.AppScanner.start('handover-qr-reader', {
                                    onScan: (decoded) => this.onDecoded(decoded),
                                });
                            } else if (window.Html5Qrcode) {
                                const instance = new window.Html5Qrcode('handover-qr-reader');
                                let cameras = [];
                                try { cameras = await window.Html5Qrcode.getCameras(); } catch (e) {}
                                const config = {
                                    fps: 10,
                                    qrbox: (w, h) => {
                                        const edge = Math.floor(Math.min(w, h) * 0.75);
                                        return { width: Math.max(edge, 160), height: Math.max(edge, 160) };
                                    }
                                };
                                if (cameras && cameras.length > 0) {
                                    let selectedId = cameras[0].id;
                                    for (const c of cameras) {
                                        const lbl = (c.label || '').toLowerCase();
                                        if (lbl.includes('back') || lbl.includes('rear') || lbl.includes('belakang') || lbl.includes('environment')) {
                                            selectedId = c.id;
                                            break;
                                        }
                                    }
                                    await instance.start(selectedId, config, (decoded) => this.onDecoded(decoded), () => {});
                                } else {
                                    try {
                                        await instance.start({ facingMode: 'environment' }, config, (decoded) => this.onDecoded(decoded), () => {});
                                    } catch (e2) {
                                        await instance.start({ facingMode: 'user' }, config, (decoded) => this.onDecoded(decoded), () => {});
                                    }
                                }
                                this.html5 = instance;
                            } else {
                                throw new Error('Modul kamera belum siap dimuat.');
                            }
                            this.cameraRunning = true;
                            this.scanMessage = 'Kamera aktif — arahkan ke QR unit.';
                        } catch (e) {
                            this.cameraRunning = false;
                            this.scanError = (e && e.message) ? e.message : 'Kamera tidak tersedia atau izin ditolak. Masukkan kode secara manual.';
                            this.scanMessage = '';
                        }
                    },

                    async stopCamera() {
                        if (this.html5) {
                            if (window.AppScanner) {
                                await window.AppScanner.stop(this.html5);
                            } else {
                                try {
                                    if (this.html5.isScanning) {
                                        await this.html5.stop();
                                    }
                                    await this.html5.clear();
                                } catch (e) {}
                            }
                            this.html5 = null;
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
                            this.scanError = 'Gagal memproses hasil pindai. Periksa koneksi lalu coba lagi.';
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>

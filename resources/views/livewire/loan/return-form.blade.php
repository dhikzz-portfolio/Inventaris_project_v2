<div class="space-y-5"
    x-data="{
        scanOpen: false,
        running: false,
        html5: null,
        async toggleScan() {
            this.scanOpen = !this.scanOpen;
            if (!this.scanOpen) { await this.stopCamera(); }
        },
        async startCamera() {
            try {
                if (window.AppScanner) {
                    this.html5 = await window.AppScanner.start('return-qr-reader', {
                        onScan: (decoded) => this.handleScan(decoded),
                    });
                } else if (window.Html5Qrcode) {
                    const instance = new window.Html5Qrcode('return-qr-reader');
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
                        await instance.start(selectedId, config, (decoded) => this.handleScan(decoded), () => {});
                    } else {
                        try {
                            await instance.start({ facingMode: 'environment' }, config, (decoded) => this.handleScan(decoded), () => {});
                        } catch (e2) {
                            await instance.start({ facingMode: 'user' }, config, (decoded) => this.handleScan(decoded), () => {});
                        }
                    }
                    this.html5 = instance;
                } else {
                    return;
                }
                this.running = true;
            } catch (e) {
                this.running = false;
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
            this.running = false;
        },
        async handleScan(text) {
            await this.stopCamera();
            const value = (text || '').trim();
            if (value === '') { return; }
            await $wire.scanUnitCode(value);
        },
    }"
    x-on:focus-unit.window="setTimeout(() => document.getElementById('return-unit-' + $event.detail.index)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150)">
    @if (session('success'))
        <div role="status" class="flex items-start gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
            <span class="material-icons-round text-lg" aria-hidden="true">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-700">
            <span class="material-icons-round text-lg" aria-hidden="true">error_outline</span>
            <div class="space-y-1">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if ($mismatches !== [])
        <div class="flex items-start gap-2 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-200" wire:key="mismatch-alert">
            <span class="material-icons-round">warning</span>
            <div>
                <strong>Barang tertukar / ruangan berbeda!</strong>
                Ada unit yang tujuannya bukan ruangan asalnya. Sistem tetap memproses setelah Anda menyetujui, namun tercatat sebagai
                <strong>mutasi lokasi otomatis</strong> (riwayat + pembaruan ID Internal).
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-1.5 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600">fact_check</span>
                    Pemeriksaan Pengembalian per Unit
                </h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Peminjam: <strong>{{ $loan->borrower?->name }}</strong> • Kembali
                    {{ $loan->due_date->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold text-slate-800 dark:text-slate-100">
                    {{ $progress['confirmed'] }} dari {{ $progress['total'] }} unit sudah dikonfirmasi
                </div>
                <div class="text-xs {{ $progress['pending'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-green-700 dark:text-green-300' }}">
                    {{ $progress['pending'] > 0 ? $progress['pending'] . ' unit belum dikonfirmasi' : 'Seluruh unit terkonfirmasi' }}
                </div>
            </div>
        </div>

        <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar"
            aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full transition-all {{ $progress['complete'] ? 'bg-green-500' : 'bg-brand-600' }}"
                style="width: {{ $progress['percent'] }}%"></div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-200">
                {{ $selectedCount }} unit dipilih
            </span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                {{ $progress['pending'] }} belum dikonfirmasi
            </span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Menampilkan {{ $visibleUnitCount }} dari {{ $totalUnitCount }} unit
            </span>
            @if ($selectedCount > 0)
                <button type="button" wire:click="clearSelection"
                    class="rounded-full border border-slate-300 px-2.5 py-1 font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    Bersihkan pilihan
                </button>
            @endif
        </div>

        {{-- Pencarian, filter, dan scan untuk menemukan unit --}}
        @php
            $selectedFilterCount = collect([$filterValidation, $filterCondition, $filterReturnState, $filterRoom])->filter(fn ($value) => $value !== '')->count();
            $validationFilterOptions = collect($returnStatuses)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
            $conditionFilterOptions = collect($conditions)->map(fn ($option) => ['value' => $option->value, 'label' => $option->label()])->values()->all();
            $returnStateFilterOptions = [
                ['value' => 'belum', 'label' => 'Belum dikonfirmasi'],
                ['value' => 'sudah', 'label' => 'Sudah dikonfirmasi'],
            ];
            $roomFilterOptions = collect($filterRooms)->map(fn ($option) => ['value' => (string) $option['id'], 'label' => $option['label']])->values()->all();
        @endphp
        <div x-data="{ filtersOpen: {{ $selectedFilterCount > 0 ? 'true' : 'false' }} }" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/40">
            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="min-w-0 flex-1">
                    <label for="return-search" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Cari unit</label>
                    <input id="return-search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Barang, spesifikasi, kode, ruangan, catatan…"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <button type="button" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="return-unit-filter-panel"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 self-end rounded-xl border border-brand-200 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-brand-900/30">
                    <span class="material-icons-round text-base" aria-hidden="true">filter_list</span>
                    Filter
                    @if ($selectedFilterCount > 0)
                        <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-200">{{ $selectedFilterCount }}</span>
                    @endif
                </button>
            </div>
            <div id="return-unit-filter-panel" x-show="filtersOpen" x-cloak x-transition class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="return-filter-validation" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Status pemeriksaan</label>
                    <x-searchable-select id="return-filter-validation" wire-model="filterValidation"
                                         :selected="$filterValidation" :options="$validationFilterOptions"
                                         placeholder="Semua status" />
                </div>
                <div>
                    <label for="return-filter-condition" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Kondisi unit</label>
                    <x-searchable-select id="return-filter-condition" wire-model="filterCondition"
                                         :selected="$filterCondition" :options="$conditionFilterOptions"
                                         placeholder="Semua kondisi" />
                </div>
                <div>
                    <label for="return-filter-state" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Pengembalian</label>
                    <x-searchable-select id="return-filter-state" wire-model="filterReturnState"
                                         :selected="$filterReturnState" :options="$returnStateFilterOptions"
                                         placeholder="Semua unit" />
                </div>
                <div>
                    <label for="return-filter-room" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Ruangan asal</label>
                    <x-searchable-select id="return-filter-room" wire-model="filterRoom"
                                         :selected="$filterRoom" :options="$roomFilterOptions"
                                         placeholder="Semua ruangan" />
                </div>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($selectedFilterCount > 0 || $search !== '')
                    <button type="button" wire:click="resetFilters"
                    class="inline-flex min-h-8 items-center rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Hapus filter
                    </button>
                @endif
              </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="toggleScan()"
                    class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                    <span class="material-icons-round text-sm">qr_code_scanner</span> Pindai QR untuk Menemukan Unit
                </button>
            </div>

            <div x-show="scanOpen" x-cloak class="mt-3 rounded-lg border border-sky-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <div class="relative flex min-h-[160px] items-center justify-center overflow-hidden rounded-lg bg-slate-900">
                            <div id="return-qr-reader" class="w-full h-full min-h-[160px]"></div>
                            <span x-show="!running" class="absolute px-3 text-center text-xs text-slate-400 pointer-events-none">Kamera belum aktif</span>
                        </div>
                        <div class="mt-2 flex justify-center gap-2">
                            <button type="button" x-show="!running" x-on:click="startCamera()"
                                class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-brand-700">
                                <span class="material-icons-round text-sm">photo_camera</span> Aktifkan Kamera
                            </button>
                            <button type="button" x-show="running" x-on:click="stopCamera()"
                                class="inline-flex min-h-8 items-center gap-1 rounded-lg bg-red-600 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-red-700">
                                <span class="material-icons-round text-sm">photo_camera_back</span> Matikan Kamera
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="return-scan-code" class="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">Masukkan kode inventaris secara manual</label>
                        <div class="flex gap-2">
                            <input id="return-scan-code" type="text" wire:model="scanCode"
                                wire:keydown.enter.prevent="scanUnitCode"
                                placeholder="Contoh: G1-02-12" autocomplete="off"
                                class="min-w-0 flex-1 rounded-lg border-slate-300 bg-white font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <button type="button" wire:click="scanUnitCode"
                                class="inline-flex min-h-9 items-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Cari</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($scanMessage !== '')
                <p role="status" class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ $scanMessage }}</p>
            @endif
            @if ($scanError !== '')
                <p role="alert" class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-700">{{ $scanError }}</p>
            @endif
        </div>

        @if ($progress['pending'] > 0 && $pendingLabels->isNotEmpty())
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                <strong>Unit yang perlu ditemukan:</strong>
                {{ $pendingLabels->implode(', ') }}@if ($progress['pending'] > $pendingLabels->count()) dan {{ $progress['pending'] - $pendingLabels->count() }} lainnya @endif
            </div>
        @endif
    </div>

    @if ($visibleUnitCount === 0)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-600 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
            Tidak ada unit yang cocok dengan pencarian/filter.
            <button type="button" wire:click="resetFilters" class="ms-1 inline-flex min-h-8 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 transition hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300 dark:hover:bg-brand-900/50">Hapus filter</button>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($groupedRows as $group)
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" wire:key="group-{{ $group['item_id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-4 dark:border-slate-700 sm:p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="font-bold text-slate-900 dark:text-slate-100">{{ $group['item_name'] }}</h4>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ $group['total_borrowed'] }} unit dipinjam
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $group['specification'] ?: 'Spesifikasi tidak tersedia' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            Sudah kembali: <strong>{{ $group['confirmed'] }}</strong> /
                            {{ $group['total_borrowed'] }} • Belum: <strong>{{ $group['remaining'] }}</strong> • Status: <strong>{{ $group['status'] }}</strong>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($group['visible_assignable'] > 0)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">
                                <input type="checkbox" @checked($group['all_selected'])
                                    wire:click="toggleGroup({{ $group['item_id'] }})"
                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950">
                                Pilih semua ({{ $group['visible_assignable'] }})
                            </label>
                        @endif

                        @if ($group['can_mass_validate'])
                            <button type="button" wire:click="requestMassValidation({{ $group['item_id'] }})"
                                class="inline-flex min-h-10 items-center gap-1.5 rounded-lg bg-purple-600 px-3 py-2 text-xs font-bold text-white hover:bg-purple-700">
                                <span class="material-icons-round text-base">done_all</span>
                                Pemeriksaan Semua {{ $group['item_name'] }}
                            </button>
                        @endif
                    </div>
                </div>

                @if ($massGroupId === (int) $group['item_id'])
                    <div class="border-b border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-900 dark:border-purple-900 dark:bg-purple-950/30 dark:text-purple-100">
                        <p class="font-semibold">Konfirmasi pemeriksaan massal</p>
                        <p class="mt-1 text-xs">
                            Akan memproses <strong>{{ $massPendingCount }} unit {{ $massGroupName }}</strong> yang belum dikonfirmasi.
                            Unit yang sudah selesai tidak ikut diproses. Kondisi dan catatan per unit tetap disimpan sesuai isian masing-masing.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="confirmMassValidation" wire:loading.attr="disabled"
                                class="rounded-lg bg-purple-700 px-3 py-2 text-xs font-bold text-white hover:bg-purple-800 disabled:opacity-50">
                                Ya, lakukan pemeriksaan {{ $massPendingCount }} unit
                            </button>
                            <button type="button" wire:click="cancelMassValidation"
                                class="rounded-lg border border-purple-300 px-3 py-2 text-xs font-semibold text-purple-800 hover:bg-purple-100 dark:border-purple-700 dark:text-purple-100 dark:hover:bg-purple-900/40">
                                Batal
                            </button>
                        </div>
                    </div>
                @endif

                <div class="px-4 pb-4 pt-3 sm:px-5">
                    <div class="flex items-center justify-between gap-2 pb-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Daftar unit</p>
                        @if (count($group['rows']) > 4)
                            <span class="inline-flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                                <span class="material-icons-round text-sm" aria-hidden="true">unfold_more</span>
                                Gulir ke bawah untuk melihat unit lain
                            </span>
                        @endif
                    </div>

                    <div role="region" aria-label="Daftar unit {{ $group['item_name'] }}" tabindex="0"
                         class="max-h-[30rem] overflow-y-auto overscroll-contain rounded-xl px-1 pb-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:max-h-[40rem]">
                        <div class="space-y-3">
                            @foreach ($group['rows'] as $row)
                                @php($index = $row['row_index'])
                                @php($isMismatch = in_array($index, $mismatches, true))
                                @php($unitStatus = $row['returned']
                                    ? \App\Enums\ReturnStatus::SUDAH_DIKONFIRMASI
                                    : (in_array($index, $selected)
                                        ? \App\Enums\ReturnStatus::MENUNGGU_KONFIRMASI
                                        : \App\Enums\ReturnStatus::BELUM_DIPERIKSA))
                                <div class="min-w-0" id="return-unit-{{ $index }}" wire:key="unit-row-{{ $row['assignment_id'] ?? 'legacy-' . $index }}">
                                    <div @class([
                                        'flex h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-slate-900',
                                        'border-orange-300 dark:border-orange-900' => $isMismatch,
                                        'border-slate-200 dark:border-slate-700' => ! $isMismatch,
                                        'ring-2 ring-amber-400 ring-offset-2 dark:ring-offset-slate-900' => $focusedIndex === $index,
                                    ])>
                                        {{-- Kepala kartu: judul + status pemeriksaan --}}
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                                            <div class="flex min-w-0 items-center gap-2">
                                                @if ($row['has_assignment'] && ! $row['returned'])
                                                    <input type="checkbox" value="{{ $index }}" wire:model.live="selected"
                                                        aria-label="Pilih unit {{ $row['inventory_code'] ?? $row['item_name'] }}"
                                                        class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950">
                                                @endif
                                                <h5 class="text-sm font-bold text-slate-900 dark:text-slate-100">Pengembalian Aset</h5>
                                            </div>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $unitStatus->badgeClass() }}">
                                                {{ $unitStatus->label() }}
                                            </span>
                                        </div>

                                        {{-- Jalur aset: nama barang › spesifikasi › kode unit --}}
                                        <nav aria-label="Jalur aset"
                                            class="flex flex-wrap items-center gap-x-1 gap-y-0.5 border-b border-slate-100 bg-slate-50/70 px-4 py-1.5 text-[11px] text-slate-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400">
                                            <span>{{ $row['item_name'] }}</span>
                                            <span class="material-icons-round text-sm text-slate-300 dark:text-slate-600" aria-hidden="true">chevron_right</span>
                                            <span>{{ $row['specification'] ?: 'Spesifikasi tidak tersedia' }}</span>
                                            <span class="material-icons-round text-sm text-slate-300 dark:text-slate-600" aria-hidden="true">chevron_right</span>
                                            <span aria-current="page" class="font-mono font-semibold text-slate-700 dark:text-slate-200">
                                                {{ $row['inventory_code'] ?? $row['internal_id'] }}
                                            </span>
                                        </nav>

                                        <div class="grid flex-1 grid-cols-1 md:grid-cols-[15rem_1fr]">
                                            {{-- Panel kiri: identitas dan riwayat unit --}}
                                            <div class="border-b border-slate-200 bg-brand-50/60 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/40 md:border-b-0 md:border-e">
                                                <p class="truncate font-bold text-slate-900 dark:text-slate-100" title="{{ $row['item_name'] }}">{{ $row['item_name'] }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $row['specification'] ?: 'Spesifikasi tidak tersedia' }}</p>

                                                <dl class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                                                    <div>
                                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode Aset</dt>
                                                        <dd class="mt-0.5 break-all font-mono text-xs font-bold text-brand-700 dark:text-brand-300">
                                                            {{ $row['inventory_code'] ?? $row['internal_id'] }}
                                                        </dd>
                                                    </div>
                                                    @if (! empty($row['qr']))
                                                        <div class="flex justify-center rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-700">
                                                            <img src="{{ $row['qr'] }}" loading="lazy"
                                                                alt="QR unit {{ $row['inventory_code'] ?? $row['item_name'] }}"
                                                                class="h-32 w-32">
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</dt>
                                                        <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-200">
                                                            {{ $row['unit_location'] ?? $row['room_code'] }}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kondisi Keluar</dt>
                                                        <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-200">
                                                            {{ \App\Enums\Condition::from($row['condition_out'])->label() }}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dikonfirmasi</dt>
                                                        <dd class="mt-0.5 text-xs text-slate-700 dark:text-slate-200">
                                                            @if ($row['returned'] && $row['confirmed_at'])
                                                                {{ $row['confirmed_at']->format('d/m/Y H:i') }}
                                                                @if ($row['confirmed_by_name'])
                                                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">oleh {{ $row['confirmed_by_name'] }}</span>
                                                                @endif
                                                            @else
                                                                <span class="text-slate-500 dark:text-slate-400">Belum dikonfirmasi</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </div>

                                            {{-- Panel kanan: isian pemeriksaan --}}
                                            <div class="flex flex-col p-4">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <div>
                                                        @if ($row['has_assignment'])
                                                            <x-input-label value="Jumlah kembali" />
                                                            <div class="mt-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                                1 unit ({{ $row['inventory_code'] ?? '-' }})
                                                            </div>
                                                        @else
                                                            <x-input-label value="Jumlah Kembali *" />
                                                            <input type="number" min="0" max="{{ $row['remaining'] }}"
                                                                wire:model.live="rows.{{ $index }}.qty_returned"
                                                                @disabled($row['returned'])
                                                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                        @endif
                                                    </div>

                                                    <div>
                                                        <x-input-label value="Kondisi Akhir *" />
                                                        <select wire:model.live="rows.{{ $index }}.condition_in" @disabled($row['returned'])
                                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                                            @foreach ($conditions as $condition)
                                                                <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                                            @endforeach
                                                        </select>

                                                        @if (($row['condition_in'] ?? 'BAIK') !== 'BAIK' && ! $row['returned'])
                                                            <div class="mt-2">
                                                                <x-input-label value="Foto bukti kerusakan (opsional)" />
                                                                <input type="file" wire:model.live="rows.{{ $index }}.damage_photo"
                                                                    accept="image/*"
                                                                    class="mt-1 block w-full text-xs text-slate-600 file:me-2 file:rounded file:border-0 file:bg-red-50 file:px-2 file:py-1.5 file:text-red-700 dark:text-slate-300 dark:file:bg-red-950/50 dark:file:text-red-300">
                                                                <div wire:loading wire:target="rows.{{ $index }}.damage_photo" class="mt-1 text-[11px] text-slate-500">
                                                                    Mengunggah...
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                </div>

                                                <div class="mt-3">
                                                    <x-input-label value="Dikembalikan ke Ruangan *" />
                                                    <select wire:model.live="rows.{{ $index }}.returned_room_id" @disabled($row['returned'])
                                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 @if ($isMismatch) border-orange-400 dark:border-orange-500 @endif">
                                                        @foreach ($rooms as $room)
                                                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mt-3">
                                                    <x-input-label value="Catatan kondisi / kerusakan (opsional)" />
                                                    <textarea rows="2" wire:model.live="rows.{{ $index }}.return_notes" @disabled($row['returned'])
                                                        placeholder="Contoh: retak pada rangka, kabel terkelupas, atau catatan pemeriksaan lain"
                                                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-100 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"></textarea>
                                                </div>

                                                @if (! $row['returned'])
                                                    <div class="mt-auto flex justify-end pt-3">
                                                        <button type="button" wire:click="confirmOne({{ $index }})" wire:loading.attr="disabled"
                                                            class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white hover:bg-brand-700 disabled:opacity-50">
                                                            <span class="material-icons-round text-base">task_alt</span>
                                                            Konfirmasi unit ini
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endforeach
    </div>

    <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        @if ($mismatches !== [])
            <label class="mb-4 flex items-start gap-2 rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-200">
                <input type="checkbox" wire:model.live="confirmMismatch"
                    class="mt-0.5 rounded border-orange-400 text-orange-600 focus:ring-orange-500">
                <span>Saya memahami ada unit dikembalikan ke ruangan berbeda dan setuju tercatat sebagai
                    <strong>mutasi otomatis</strong>.</span>
            </label>
            <x-input-error :messages="$errors->get('confirmMismatch')" class="mb-3" />
        @endif

        @if ($hasUnitBasedRows)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">
                        {{ $progress['confirmed'] }} dari {{ $progress['total'] }} unit sudah dikonfirmasi
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Status peminjaman baru menjadi <strong>Selesai</strong> setelah seluruh unit dikonfirmasi dan tombol penyelesaian ditekan.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" wire:click="confirmSelected" wire:loading.attr="disabled"
                        @disabled($selectedCount === 0)
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-3 text-sm font-bold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-icons-round text-lg">playlist_add_check</span>
                        Konfirmasi {{ $selectedCount > 0 ? $selectedCount . ' unit terpilih' : 'unit terpilih' }}
                    </button>

                    <button type="button" wire:click="finishReturn" wire:loading.attr="disabled"
                        @disabled(! $canFinish)
                        aria-disabled="{{ $canFinish ? 'false' : 'true' }}"
                        data-can-finish="{{ $canFinish ? 'true' : 'false' }}"
                        title="{{ $canFinish ? 'Seluruh unit sudah dikonfirmasi' : 'Masih ada unit yang belum dikonfirmasi' }}"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 {{ $canFinish ? 'bg-green-600 hover:bg-green-700' : 'bg-slate-400 dark:bg-slate-700' }}">
                        <span class="material-icons-round text-lg">task_alt</span>
                        Selesaikan Pengembalian
                    </button>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">
                Centang unit lalu konfirmasi sesuai kondisi masing-masing. Pilihan unit tidak otomatis mengonfirmasi pengembalian.
                Pemeriksaan massal hanya tampil untuk kelompok sejenis dengan minimal {{ $massThreshold }} unit dan tidak memproses unit yang sudah dikonfirmasi.
            </p>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm">
                    @if ($progress['pending'] > 0)
                        <span class="font-semibold text-brand-700 dark:text-brand-300">
                            {{ $progress['confirmed'] }} dari {{ $progress['total'] }} unit sudah dikembalikan;
                            {{ $progress['pending'] }} unit masih belum diselesaikan.
                        </span>
                    @else
                        <span class="font-semibold text-green-700 dark:text-green-300">
                            Semua unit telah dikembalikan — pinjaman akan berstatus Selesai.
                        </span>
                    @endif
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" wire:click="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-600 px-5 py-3 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="submit" class="material-icons-round text-lg">task_alt</span>
                        <span wire:loading wire:target="submit" class="material-icons-round animate-spin text-lg">progress_activity</span>
                        Proses Pengembalian
                    </button>
                    <a href="{{ route('loans.show', $loan) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </a>
                </div>
            </div>

            <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                Jika kondisi akhir rusak: foto bukti opsional, status barang diperbarui (Rusak Ringan/Berat), dan riwayat peminjam terakhir tercatat.
            </p>
        @endif

        <x-input-error :messages="$errors->get('selection')" class="mt-2" />
        <x-input-error :messages="$errors->get('finish')" class="mt-2" />
        <x-input-error :messages="$errors->get('mass')" class="mt-2" />
    </div>
</div>

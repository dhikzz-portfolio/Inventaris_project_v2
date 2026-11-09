<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Scan Inventaris</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pindai QR unit atau ketik kode inventaris — hasil tampil langsung di halaman ini</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4" x-data="scanner()" x-init="init()">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Kamera --}}
            <div class="flex flex-col rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600 dark:text-brand-400" aria-hidden="true">qr_code_scanner</span>
                    Pindai QR
                    <span class="ml-auto inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                          :class="running ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                        <span class="h-2 w-2 rounded-full" :class="running ? 'bg-green-500' : 'bg-slate-400'"></span>
                        <span x-text="running ? 'Aktif' : 'Nonaktif'"></span>
                    </span>
                </h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Arahkan kamera ke QR Code pada label unit.</p>

                <div class="relative mx-auto mt-4 aspect-square w-full max-w-[300px] overflow-hidden rounded-xl bg-slate-900">
                    <div id="qr-reader" class="h-full w-full"></div>

                    {{-- Placeholder saat kamera belum aktif --}}
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 text-slate-400" x-show="!running">
                        <span class="material-icons-round text-5xl" aria-hidden="true">qr_code_scanner</span>
                        <span class="text-sm">Kamera belum aktif</span>
                    </div>

                    {{-- Bingkai pemindai di tengah area tampilan --}}
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative h-3/5 w-3/5">
                            <span class="absolute left-0 top-0 h-8 w-8 rounded-tl-lg border-l-2 border-t-2 border-brand-400"></span>
                            <span class="absolute right-0 top-0 h-8 w-8 rounded-tr-lg border-r-2 border-t-2 border-brand-400"></span>
                            <span class="absolute bottom-0 left-0 h-8 w-8 rounded-bl-lg border-b-2 border-l-2 border-brand-400"></span>
                            <span class="absolute bottom-0 right-0 h-8 w-8 rounded-br-lg border-b-2 border-r-2 border-brand-400"></span>
                            <span class="scan-line h-0.5 rounded-full bg-brand-400 shadow-[0_0_12px_rgba(56,189,248,0.9)]" x-show="running"></span>
                        </div>
                    </div>
                </div>

                <div class="mt-auto flex items-center justify-center gap-2 pt-4">
                    <button type="button" x-show="!running" @click="start()"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="material-icons-round text-lg" aria-hidden="true">photo_camera</span> Aktifkan Kamera
                    </button>
                    <button type="button" x-show="running" @click="stop()"
                            class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <span class="material-icons-round text-lg" aria-hidden="true">photo_camera_back</span> Matikan Kamera
                    </button>
                </div>

                <p class="mt-2 min-h-[1rem] text-center text-xs"
                   :class="error ? 'text-red-600 dark:text-red-300' : 'text-slate-500 dark:text-slate-400'"
                   x-text="message || error"></p>
            </div>

            {{-- Input manual --}}
            <div class="flex flex-col rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                    <span class="material-icons-round text-brand-600 dark:text-brand-400" aria-hidden="true">keyboard</span>
                    Input Manual
                </h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Ketik kode inventaris bila kamera tidak tersedia.</p>

                <div class="flex flex-1 flex-col justify-center">
                    <form method="GET" action="{{ route('scan') }}" class="mt-3 flex gap-2" @submit="running = false">
                        <label for="scan-code" class="sr-only">Kode inventaris</label>
                        <input id="scan-code" type="text" name="code" value="{{ $code }}" inputmode="text"
                               autocomplete="off" placeholder="Contoh: G1-02-12"
                               class="min-w-0 flex-1 rounded-xl border-slate-300 bg-white px-3 py-2.5 font-mono text-sm uppercase text-slate-800 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <button type="submit"
                                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <span class="material-icons-round text-base" aria-hidden="true">search</span> Cari
                        </button>
                    </form>

                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                        Masukkan kode unit <span class="font-mono">G1-02-12</span> (kode lama tetap diterima). Kamera hanya diperlukan bila perangkat mendukung; input manual selalu tersedia.
                    </p>

                    <div class="mt-3 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        <span class="material-icons-round text-base text-slate-400 dark:text-slate-500" aria-hidden="true">info</span>
                        <span>Pemindaian hanya membaca data inventaris. Informasi detail mengikuti kewenangan akun Anda.</span>
                    </div>
                </div>
            </div>
        </div>

        @if ($result !== null)
            <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900" id="scan-result">
                @if ($result['status'] === 'found')
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $result['name'] }}</h3>
                            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $result['specification_label'] ?: 'Spesifikasi belum dicatat' }}</p>
                            @if ($result['specification'] && $result['specification'] !== $result['specification_label'])
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $result['specification'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="font-mono text-lg font-bold text-slate-900 dark:text-slate-100">{{ $result['code'] }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">Kode Inventaris</div>
                        </div>
                    </div>

                    @if ($result['archived'])
                        <div class="mb-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            Unit ini sudah diarsipkan/dihapus secara logis. Data ditampilkan untuk penelusuran riwayat, bukan sebagai unit aktif.
                        </div>
                    @endif

                    @if ($result['payload_mismatch'])
                        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            Kode pada QR ({{ $result['payload_code'] }}) berbeda dari data unit di database. Data terbaru dari database yang ditampilkan; jangan gunakan QR lama untuk transaksi.
                        </div>
                    @endif

                    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Gedung</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $result['building'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Lantai</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $result['floor'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Ruangan</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">
                                {{ $result['room'] }}
                                @if ($result['room_code'])
                                    <span class="font-mono text-xs text-slate-400 dark:text-slate-500">({{ $result['room_code'] }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Nomor unit</dt>
                            <dd class="font-mono font-medium text-slate-800 dark:text-slate-100">{{ str_pad((string) $result['unit_no'], 2, '0', STR_PAD_LEFT) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Kondisi</dt>
                            <dd><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $result['condition_badge'] }}">{{ $result['condition'] }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Status</dt>
                            <dd><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $result['unit_status_badge'] }}">{{ $result['unit_status'] }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Kategori</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $result['category'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Jurusan</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $result['major'] ?: '—' }}</dd>
                        </div>
                        @if ($result['brand'] || $result['model_type'])
                            <div>
                                <dt class="text-xs text-slate-400 dark:text-slate-500">Merek / Tipe</dt>
                                <dd class="font-medium text-slate-800 dark:text-slate-100">{{ trim(($result['brand'] ?? '') . ' — ' . ($result['model_type'] ?? '')) ?: '—' }}</dd>
                                @if ($result['specification_code'])
                                    <dd class="font-mono text-[10px] text-slate-400 dark:text-slate-500">{{ $result['specification_code'] }}</dd>
                                @endif
                            </div>
                        @endif
                    </dl>

                    @if ($result['register_no'] || $result['acquisition_value'] || $result['notes'])
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                            <div class="mb-1 font-semibold">Informasi internal (sesuai kewenangan)</div>
                            <div>Nomor register: {{ $result['register_no'] ?: '—' }}</div>
                            <div>Nilai perolehan: {{ $result['acquisition_value'] ? 'Rp ' . number_format((float) $result['acquisition_value'], 0, ',', '.') : '—' }}</div>
                            <div>Catatan: {{ $result['notes'] ?: '—' }}</div>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-start gap-3">
                        <a href="{{ $result['url'] }}"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            <span class="material-icons-round text-base" aria-hidden="true">open_in_new</span> Buka detail unit
                        </a>
                        <a href="{{ route('movements.index') }}?move={{ urlencode($result['code']) }}"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:bg-brand-500 dark:hover:bg-brand-600 dark:focus:ring-brand-400 dark:focus:ring-offset-slate-900"
                           aria-label="Pindahkan unit ini ke tujuan perpindahan">
                            <span class="material-icons-round text-base" aria-hidden="true">swap_horiz</span> Pindahkan
                        </a>
                    </div>
                @elseif ($result['status'] === 'catalog')
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                        Kode ini adalah ID internal katalog lama: <strong>{{ $result['name'] }}</strong> ({{ $result['code'] }}).
                        <a href="{{ $result['url'] }}"
                           class="ms-1 inline-flex min-h-8 items-center justify-center rounded-lg bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Buka detail katalog
                        </a>
                    </div>
                @elseif ($result['status'] === 'restricted')
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                        <strong class="font-mono">{{ $result['code'] }}</strong> — {{ $result['message'] }}
                    </div>
                @else
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        <strong>{{ $result['code'] }}</strong> — {{ $result['message'] }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @push('styles')
        <style>
            @keyframes scanline {
                0% { top: 0; opacity: 0; }
                10% { opacity: 1; }
                85% { opacity: 1; }
                100% { top: 100%; opacity: 0; }
            }
            .scan-line {
                position: absolute;
                left: 0.5rem;
                right: 0.5rem;
                animation: scanline 2.4s ease-in-out infinite;
            }
            @media (prefers-reduced-motion: reduce) {
                .scan-line { animation: none; top: 50%; opacity: 0.6; }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            function scanner() {
                return {
                    scannerInstance: null,
                    running: false,
                    message: '',
                    error: '',
                    async init() {
                        this.error = '';
                        this.message = '';
                        // Check if we're in mutasi mode - if so, we need to communicate
                        // with the movements form
                        this.isMutasiMode = '{{ request()->query('mode') }}' === 'mutasi';
                    },
                    async start() {
                        this.error = '';
                        this.message = 'Menghubungkan ke kamera...';
                        try {
                            if (window.AppScanner) {
                                this.scannerInstance = await window.AppScanner.start('qr-reader', {
                                    onScan: (decoded) => this.onScan(decoded),
                                });
                            } else if (window.Html5Qrcode) {
                                const instance = new window.Html5Qrcode('qr-reader');
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
                                    await instance.start(selectedId, config, (decoded) => this.onScan(decoded), () => {});
                                } else {
                                    try {
                                        await instance.start({ facingMode: 'environment' }, config, (decoded) => this.onScan(decoded), () => {});
                                    } catch (e2) {
                                        await instance.start({ facingMode: 'user' }, config, (decoded) => this.onScan(decoded), () => {});
                                    }
                                }
                                this.scannerInstance = instance;
                            } else {
                                throw new Error('Modul kamera belum selesai dimuat. Silakan tunggu sebentar atau gunakan input manual.');
                            }
                            this.running = true;
                            this.message = 'Kamera aktif — arahkan ke QR Code.';
                        } catch (e) {
                            this.running = false;
                            this.error = (e && e.message) ? e.message : 'Kamera tidak tersedia/ditolak. Gunakan input manual di samping.';
                            this.message = '';
                        }
                    },
                    async stop() {
                        if (this.scannerInstance) {
                            if (window.AppScanner) {
                                await window.AppScanner.stop(this.scannerInstance);
                            } else {
                                try {
                                    if (this.scannerInstance.isScanning) {
                                        await this.scannerInstance.stop();
                                    }
                                    await this.scannerInstance.clear();
                                } catch (e) {}
                            }
                            this.scannerInstance = null;
                        }
                        this.running = false;
                        this.message = 'Kamera dimatikan.';
                    },
                    async onScan(text) {
                        await this.stop();
                        const value = (text || '').trim();
                        if (!value) {
                            return;
                        }

                        // QR baru berisi URL halaman scan (dengan metadata release);
                        // stiker lama berisi URL detail unit atau kode/ID mentah.
                        if (/^https?:\/\//i.test(value)) {
                            window.location.href = value;
                            return;
                        }

                        // Mutasi mode: tambahkan ke movements form dan kembalikan
                        if (this.isMutasiMode) {
                            // Navigate back to movements with the scanned code
                            const base = '{{ route('movements.index') }}';
                            window.location.href = base + '?scannedCodes=' + encodeURIComponent(value);
                            return;
                        }

                        const base = '{{ route('scan') }}';
                        window.location.href = base + '?code=' + encodeURIComponent(value);
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>

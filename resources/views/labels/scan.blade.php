<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scan Inventaris</h2>
                <p class="text-sm text-gray-500">Pindai QR unit atau ketik kode inventaris — hasil tampil langsung di halaman ini</p>
            </div>
            @if (auth()->user()->canApproveLoans())
                <a href="{{ route('labels.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Cetak Label</a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" x-data="scanner()" x-init="init()">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <div id="qr-reader" class="rounded-lg overflow-hidden bg-slate-900 min-h-[220px] flex items-center justify-center">
                        <span class="text-slate-500 text-sm px-4 text-center" x-show="!running">Kamera belum aktif</span>
                    </div>

                    <div class="mt-3 flex items-center justify-center gap-2">
                        <button type="button" x-show="!running" @click="start()"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">
                            <span class="material-icons-round text-lg">photo_camera</span> Aktifkan Kamera
                        </button>
                        <button type="button" x-show="running" @click="stop()"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                            <span class="material-icons-round text-lg">photo_camera_back</span> Matikan Kamera
                        </button>
                    </div>

                    <p class="mt-2 text-center text-xs" :class="error ? 'text-red-600' : 'text-gray-400'" x-text="message || error"></p>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-800 text-sm mb-2">Input Manual (Kode Inventaris)</h3>
                    <form method="GET" action="{{ route('scan') }}" class="flex gap-2" @submit="running = false">
                        <input type="text" name="code" value="{{ $code }}" inputmode="text" autocomplete="off"
                               placeholder="Contoh: G1-02-12"
                               class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500 font-mono uppercase">
                        <button class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Cari</button>
                    </form>
                    <p class="text-[11px] text-gray-400 mt-2">
                        Masukkan kode unit <span class="font-mono">G1-02-12</span> (kode lama tetap diterima). Kamera hanya diperlukan bila perangkat mendukung; input manual selalu tersedia.
                    </p>
                    <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 p-3 text-[11px] text-slate-500">
                        Pemindaian tidak mengubah data inventaris. Informasi detail mengikuti kewenangan akun Anda.
                    </div>
                </div>
            </div>
        </div>

        @if ($result !== null)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" id="scan-result">
                @if ($result['status'] === 'found')
                    <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">{{ $result['name'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $result['specification'] ?: 'Spesifikasi belum dicatat' }}</p>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-lg text-gray-900">{{ $result['code'] }}</div>
                            <div class="text-xs text-gray-400">Kode Inventaris</div>
                        </div>
                    </div>

                    @if ($result['archived'])
                        <div class="mb-4 rounded-lg bg-gray-100 border border-gray-300 px-4 py-3 text-sm text-gray-700">
                            Unit ini sudah diarsipkan/dihapus secara logis. Data ditampilkan untuk penelusuran riwayat, bukan sebagai unit aktif.
                        </div>
                    @endif

                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-400">Gedung</dt><dd class="font-medium">{{ $result['building'] ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Lantai</dt><dd class="font-medium">{{ $result['floor'] }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Ruangan</dt><dd class="font-medium">{{ $result['room'] }} @if($result['room_code'])<span class="text-xs font-mono text-gray-400">({{ $result['room_code'] }})</span>@endif</dd></div>
                        <div><dt class="text-xs text-gray-400">Nomor unit</dt><dd class="font-mono font-medium">{{ str_pad((string) $result['unit_no'], 2, '0', STR_PAD_LEFT) }}</dd></div>
                        <div>
                            <dt class="text-xs text-gray-400">Kondisi</dt>
                            <dd><span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $result['condition_badge'] }}">{{ $result['condition'] }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Status</dt>
                            <dd><span class="text-[11px] font-bold px-2 py-0.5 rounded-full {{ $result['unit_status_badge'] }}">{{ $result['unit_status'] }}</span></dd>
                        </div>
                        <div><dt class="text-xs text-gray-400">Kategori</dt><dd class="font-medium">{{ $result['category'] ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Jurusan</dt><dd class="font-medium">{{ $result['major'] ?: '—' }}</dd></div>
                        @if ($result['brand'] || $result['model_type'])
                            <div><dt class="text-xs text-gray-400">Merek / Tipe</dt><dd class="font-medium">{{ trim(($result['brand'] ?? '').' '.($result['model_type'] ?? '')) ?: '—' }}</dd></div>
                        @endif
                    </dl>

                    @if ($result['register_no'] || $result['acquisition_value'] || $result['notes'])
                        <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900">
                            <div class="font-semibold mb-1">Informasi internal (sesuai kewenangan)</div>
                            <div>Nomor register: {{ $result['register_no'] ?: '—' }}</div>
                            <div>Nilai perolehan: {{ $result['acquisition_value'] ? 'Rp '.number_format((float) $result['acquisition_value'], 0, ',', '.') : '—' }}</div>
                            <div>Catatan: {{ $result['notes'] ?: '—' }}</div>
                        </div>
                    @endif

                    <div class="mt-4 flex items-center gap-3">
                        <a href="{{ $result['url'] }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold">
                            <span class="material-icons-round text-sm">open_in_new</span> Buka detail unit
                        </a>
                        @if (auth()->user()->canApproveLoans())
                            <a href="{{ route('units.label', $result['unit_id']) }}" target="_blank" class="text-xs font-semibold text-amber-700 hover:underline">Cetak ulang label</a>
                        @endif
                    </div>
                @elseif ($result['status'] === 'catalog')
                    <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-900">
                        Kode ini adalah ID internal katalog lama: <strong>{{ $result['name'] }}</strong> ({{ $result['code'] }}).
                        <a href="{{ $result['url'] }}" class="font-semibold underline">Buka detail katalog</a>.
                    </div>
                @elseif ($result['status'] === 'restricted')
                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
                        <strong class="font-mono">{{ $result['code'] }}</strong> — {{ $result['message'] }}
                    </div>
                @else
                    <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        <strong>{{ $result['code'] }}</strong> — {{ $result['message'] }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function scanner() {
            return {
                html5: null,
                running: false,
                message: '',
                error: '',
                async init() {
                    if (!window.Html5Qrcode) { this.error = 'Modul kamera belum dimuat — gunakan input manual.'; return; }
                    this.html5 = new window.Html5Qrcode('qr-reader');
                },
                async start() {
                    this.error = ''; this.message = '';
                    try {
                        await this.html5.start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: { width: 220, height: 220 } },
                            (decoded) => this.onScan(decoded),
                            () => {}
                        );
                        this.running = true;
                        this.message = 'Kamera aktif — arahkan ke QR.';
                    } catch (e) {
                        this.error = 'Kamera tidak tersedia/ditolak. Gunakan input manual di samping.';
                    }
                },
                async stop() {
                    if (this.html5 && this.running) {
                        try { await this.html5.stop(); } catch (e) {}
                        this.running = false;
                        this.message = 'Kamera dimatikan.';
                    }
                },
                async onScan(text) {
                    await this.stop();
                    const value = (text || '').trim();
                    if (!value) return;

                    // QR baru berisi URL halaman scan (dengan metadata release);
                    // stiker lama berisi URL detail unit atau kode/ID mentah.
                    if (/^https?:\/\//i.test(value)) {
                        window.location.href = value;
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

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scan QR Inventaris</h2>
                <p class="text-sm text-gray-500">Arahkan kamera ke stiker QR — langsung membuka detail barang (tanpa aplikasi tambahan)</p>
            </div>
            <a href="{{ route('labels.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Cetak Label</a>
        </div>
    </x-slot>

    <div class="max-w-xl mx-auto space-y-5" x-data="scanner()" x-init="init()">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div id="qr-reader" class="rounded-lg overflow-hidden bg-slate-900 min-h-[260px] flex items-center justify-center">
                <span class="text-slate-500 text-sm" x-show="!running">Kamera belum aktif</span>
            </div>

            <div class="mt-4 flex items-center justify-center gap-3">
                <button x-show="!running" @click="start()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">
                    <span class="material-icons-round text-lg">photo_camera</span> Aktifkan Kamera
                </button>
                <button x-show="running" @click="stop()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                    <span class="material-icons-round text-lg">photo_camera_back</span> Matikan Kamera
                </button>
            </div>

            <p class="mt-3 text-center text-xs" :class="error ? 'text-red-600' : 'text-gray-400'" x-text="message || error"></p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3">Input Manual (ID Internal)</h3>
            <form @submit.prevent="goManual()" class="flex gap-2">
                <input type="text" x-model="manual" placeholder="Contoh: MEJA-G1-01-01"
                       class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500 font-mono">
                <button class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold">Cari</button>
            </form>
            <p class="text-[11px] text-gray-400 mt-2">Berguna saat stiker pudar / kamera bermasalah.</p>
        </div>
    </div>

    @push('scripts')
    <script>
        function scanner() {
            return {
                html5: null,
                running: false,
                message: '',
                error: '',
                manual: '',
                async init() {
                    if (!window.Html5Qrcode) { this.error = 'Modul kamera belum dimuat — muat ulang halaman.'; return; }
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
                        this.error = 'Tidak dapat mengakses kamera: ' + (e && e.message ? e.message : e);
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
                    const code = (text || '').trim();
                    if (!code) return;
                    // QR baru berisi URL detail; dukung juga stiker lama berisi ID internal.
                    if (/^https?:\/\//i.test(code)) {
                        window.location.href = code;
                        return;
                    }
                    window.location.href = '{{ url('scan') }}/' + encodeURIComponent(code);
                },
                goManual() {
                    const code = (this.manual || '').trim();
                    if (!code) return;
                    window.location.href = '{{ url('scan') }}/' + encodeURIComponent(code);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

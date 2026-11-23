import Chart from 'chart.js/auto';
import { Html5Qrcode } from 'html5-qrcode';
import './live-refresh';

window.Chart = Chart;
window.Html5Qrcode = Html5Qrcode;

window.AppScanner = {
    /**
     * Start the QR code scanner on elementId with smart device fallback.
     * Supports Desktop, Laptop, and Mobile browsers.
     */
    async start(elementId, { onScan, onError, config = {} } = {}) {
        if (typeof window !== 'undefined' && window.isSecureContext === false && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            throw new Error('Akses kamera memerlukan koneksi HTTPS atau localhost (Secure Context).');
        }

        if (!window.Html5Qrcode) {
            throw new Error('Modul pemindai kamera belum siap dimuat.');
        }

        const scanner = new window.Html5Qrcode(elementId);
        const scannerConfig = {
            fps: 10,
            qrbox: (viewfinderWidth, viewfinderHeight) => {
                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                const edge = Math.floor(minEdge * 0.75);
                return { width: Math.max(edge, 160), height: Math.max(edge, 160) };
            },
            ...config,
        };

        const handleSuccess = async (decodedText, decodedResult) => {
            if (typeof onScan === 'function') {
                await onScan(decodedText, decodedResult);
            }
        };

        const handleError = (errorMessage) => {
            if (typeof onError === 'function') {
                onError(errorMessage);
            }
        };

        let cameras = [];
        try {
            cameras = await window.Html5Qrcode.getCameras();
        } catch (e) {
            // Camera enumeration failed or permissions not yet granted
        }

        if (cameras && cameras.length > 0) {
            let selectedCameraId = cameras[0].id;
            for (const cam of cameras) {
                const label = (cam.label || '').toLowerCase();
                if (label.includes('back') || label.includes('rear') || label.includes('belakang') || label.includes('environment')) {
                    selectedCameraId = cam.id;
                    break;
                }
            }
            await scanner.start(selectedCameraId, scannerConfig, handleSuccess, handleError);
            return scanner;
        }

        // Fallback: try environment facingMode (typical mobile)
        try {
            await scanner.start({ facingMode: 'environment' }, scannerConfig, handleSuccess, handleError);
            return scanner;
        } catch (envErr) {
            // Fallback: try user facingMode (laptop / single webcam)
            await scanner.start({ facingMode: 'user' }, scannerConfig, handleSuccess, handleError);
            return scanner;
        }
    },

    /**
     * Safely stop and clear a scanner instance.
     */
    async stop(scanner) {
        if (!scanner) return;
        try {
            if (scanner.isScanning) {
                await scanner.stop();
            }
            await scanner.clear();
        } catch (e) {
            // Ignore stop errors
        }
    }
};

/**
 * Combobox dengan pencarian untuk filter dan pilihan panjang.
 * Dipakai oleh komponen Blade <x-searchable-select>.
 */
window.searchableSelect = (config = {}) => ({
    options: config.options || [],
    value: String(config.value ?? ''),
    submit: Boolean(config.submit),
    open: false,
    query: '',
    highlighted: 0,

    init() {
        this.query = this.selectedOption?.label || '';

        this.$watch('value', () => {
            if (!this.open) {
                this.query = this.selectedOption?.label || '';
            }
        });
    },

    get selectedOption() {
        return this.options.find((option) => option.value === String(this.value ?? '')) || null;
    },

    get filteredOptions() {
        const term = (this.query || '').trim().toLowerCase();

        if (term === '') {
            return this.options;
        }

        return this.options.filter((option) => `${option.label} ${option.hint || ''}`.toLowerCase().includes(term));
    },

    onInput() {
        this.open = true;
        this.highlighted = 0;

        if ((this.query || '') === '') {
            this.value = '';
        }
    },

    restoreQuery() {
        this.query = this.selectedOption?.label || '';
    },

    choose(option) {
        this.value = option.value;
        this.query = option.label;
        this.open = false;
        this.notifyChange();
    },

    clear() {
        this.value = '';
        this.query = '';
        this.open = false;
        this.notifyChange();
    },

    chooseHighlighted() {
        const option = this.filteredOptions[this.highlighted];

        if (option) {
            this.choose(option);
        }
    },

    highlightNext() {
        this.open = true;
        const total = this.filteredOptions.length;

        if (total > 0) {
            this.highlighted = (this.highlighted + 1) % total;
        }
    },

    highlightPrev() {
        this.open = true;
        const total = this.filteredOptions.length;

        if (total > 0) {
            this.highlighted = (this.highlighted - 1 + total) % total;
        }
    },

    notifyChange() {
        this.$nextTick(() => {
            const hidden = this.$root.querySelector('input[type="hidden"]');

            if (hidden) {
                hidden.value = String(this.value ?? '');
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                this.$root.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (this.submit) {
                const form = this.$root.closest('form');

                if (form) {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            }
        });
    },
});

// Catatan: Alpine.js sudah dibundel dan dijalankan oleh Livewire 4.
// Mengimpor & menjalankan Alpine kedua kali membuat directive wire:model
// tidak terpasang, sehingga pencarian/pagination tidak mengirim request.

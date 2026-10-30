import Chart from 'chart.js/auto';
import { Html5Qrcode } from 'html5-qrcode';

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

// Catatan: Alpine.js sudah dibundel dan dijalankan oleh Livewire 4.
// Mengimpor & menjalankan Alpine kedua kali membuat directive wire:model
// tidak terpasang, sehingga pencarian/pagination tidak mengirim request.

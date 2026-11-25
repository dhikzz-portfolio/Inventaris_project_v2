/**
 * Refresh otomatis untuk seluruh halaman aplikasi.
 *
 * Halaman memanggil endpoint versi data secara berkala. Halaman hanya dimuat
 * ulang ketika sidik jari data berubah, sehingga tampilan tetap segar tanpa
 * membebani server. Reload ditunda ketika pengguna sedang mengisi form,
 * membuka modal, memindai QR, atau mencetak agar pekerjaan tidak hilang.
 */
const STORAGE_KEY = 'live-refresh-enabled';

const EDITABLE_SELECTOR = 'input, textarea, select, [contenteditable="true"]';

const DOT_COLORS = ['bg-emerald-500', 'bg-amber-500', 'bg-slate-400'];

let activeController = null;

export function initLiveRefresh() {
    const root = document.querySelector('[data-live-refresh]');

    if (!root) {
        return;
    }

    if (activeController && activeController.root === root) {
        return;
    }

    activeController?.destroy();
    activeController = createController(root);
    activeController.start();
}

function createController(root) {
    const label = root.querySelector('[data-live-label]');
    const ping = root.querySelector('[data-live-ping]');
    const core = root.querySelector('[data-live-core]');
    const reloadButton = root.querySelector('[data-live-reload]');
    const toggleButton = root.querySelector('[data-live-toggle]');

    const url = root.dataset.versionUrl;
    const interval = Number(root.dataset.interval || 15000);

    // Versi awal diambil lewat pengecekan pertama setelah halaman siap, agar
    // perubahan yang baru saja dilakukan pengguna sendiri tidak memicu reload
    // tambahan hanya karena cache sidik jari belum kedaluwarsa.
    let version = '';
    let pending = false;
    let checking = false;
    let touched = false;
    let printing = false;
    let enabled = readEnabled();
    let timer = null;

    const markTouched = () => {
        touched = true;
    };

    const handleVisibility = () => {
        if (!document.hidden) {
            check();
        }
    };

    const handleBeforePrint = () => {
        printing = true;
    };

    const handleAfterPrint = () => {
        printing = false;
    };

    const handleReloadClick = () => reload();

    const handleToggleClick = () => {
        enabled = !enabled;
        storeEnabled(enabled);
        pending = false;
        render();
        start();

        if (enabled) {
            check();
        }
    };

    document.addEventListener('input', markTouched, true);
    document.addEventListener('change', markTouched, true);
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('beforeprint', handleBeforePrint);
    window.addEventListener('afterprint', handleAfterPrint);
    reloadButton.addEventListener('click', handleReloadClick);
    toggleButton.addEventListener('click', handleToggleClick);

    function readEnabled() {
        try {
            return localStorage.getItem(STORAGE_KEY) !== 'off';
        } catch (error) {
            return true;
        }
    }

    function storeEnabled(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value ? 'on' : 'off');
        } catch (error) {
            // Mode privat / storage penuh: cukup gunakan state di memori.
        }
    }

    function hasOpenDialog() {
        return Array.from(document.querySelectorAll('[role="dialog"], [aria-modal="true"]')).some((dialog) => {
            return dialog.offsetParent !== null && getComputedStyle(dialog).visibility !== 'hidden';
        });
    }

    function hasActiveCamera() {
        return Array.from(document.querySelectorAll('video')).some((video) => Boolean(video.srcObject));
    }

    function isEditing() {
        if (touched) {
            return true;
        }

        const active = document.activeElement;

        return Boolean(active && active.matches(EDITABLE_SELECTOR) && !active.disabled);
    }

    function canReloadNow() {
        return !printing && !hasOpenDialog() && !hasActiveCamera() && !isEditing();
    }

    function reload() {
        window.location.reload();
    }

    function setDotColor(color) {
        [ping, core].forEach((element) => {
            element.classList.remove(...DOT_COLORS);
            element.classList.add(color);
        });

        ping.classList.toggle('hidden', color === 'bg-slate-400');
    }

    function render() {
        if (!enabled) {
            label.textContent = 'Live nonaktif';
            setDotColor('bg-slate-400');
            toggleButton.textContent = 'Aktifkan';
            toggleButton.title = 'Aktifkan pembaruan otomatis';
            reloadButton.classList.add('hidden');
            reloadButton.classList.remove('inline-flex');

            return;
        }

        label.textContent = pending ? 'Data baru tersedia' : 'Live';
        setDotColor(pending ? 'bg-amber-500' : 'bg-emerald-500');
        toggleButton.textContent = 'Jeda';
        toggleButton.title = 'Jeda pembaruan otomatis';
        reloadButton.classList.toggle('hidden', !pending);
        reloadButton.classList.toggle('inline-flex', pending);
    }

    function start() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }

        if (enabled) {
            timer = setInterval(check, interval);
        }
    }

    async function check() {
        if (!root.isConnected) {
            destroy();

            return;
        }

        if (!enabled || checking || document.hidden) {
            return;
        }

        checking = true;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data || typeof data.version !== 'string') {
                return;
            }

            if (version === '') {
                version = data.version;
                render();

                return;
            }

            if (data.version !== version) {
                pending = true;
                version = data.version;
            }

            if (pending && canReloadNow()) {
                reload();

                return;
            }

            render();
        } catch (error) {
            // Offline atau server sibuk: coba lagi pada interval berikutnya.
        } finally {
            checking = false;
        }
    }

    function destroy() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }

        document.removeEventListener('input', markTouched, true);
        document.removeEventListener('change', markTouched, true);
        document.removeEventListener('visibilitychange', handleVisibility);
        window.removeEventListener('beforeprint', handleBeforePrint);
        window.removeEventListener('afterprint', handleAfterPrint);
        reloadButton.removeEventListener('click', handleReloadClick);
        toggleButton.removeEventListener('click', handleToggleClick);

        if (activeController === controller) {
            activeController = null;
        }
    }

    const controller = { root, start, destroy, check };

    render();

    if (!version) {
        check();
    }

    return controller;
}

document.addEventListener('DOMContentLoaded', initLiveRefresh);
document.addEventListener('livewire:navigated', initLiveRefresh);

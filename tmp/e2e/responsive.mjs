import { chromium } from 'playwright';
import fs from 'node:fs';

const base = process.env.BASE_URL || 'http://127.0.0.1:8098';
const outDir = 'tmp/e2e/screens-responsive';
fs.mkdirSync(outDir, { recursive: true });

const widths = [320, 375, 430, 768, 1024, 1366, 1440, 1920];
const pages = [
    ['dashboard', '/dashboard'],
    ['catalogs', '/catalogs'],
    ['catalog-detail', '/catalogs/1'],
    ['item-detail', '/items/1'],
    ['item-edit', '/items/1/edit'],
    ['procurement', '/pengadaan/create'],
    ['movements', '/mutasi'],
    ['loans', '/loans'],
    ['loan-create', '/loans/create'],
    ['loan-detail', '/loans/5'],
    ['handover', '/loans/5/serah-terima'],
    ['returns', '/pengembalian'],
    ['return-form', '/pengembalian/5'],
    ['write-offs', '/inventaris-keluar'],
    ['write-off-pending', '/inventaris-keluar/persetujuan'],
    ['reports', '/laporan'],
    ['users', '/pengguna'],
    ['profile', '/profile'],
    ['events', '/kegiatan'],
    ['event-detail', '/kegiatan/1'],
    ['specification', '/spesifikasi/1'],
    ['unit-detail', '/units/1'],
    ['labels', '/label'],
    ['scan', '/scan?code=G1-01-268-15&r=2'],
];

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
const page = await context.newPage();
const consoleErrors = [];
const results = [];
const failures = [];

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push('PAGEERROR: ' + error.message));

async function login() {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'wakasek@gmail.com');
    await page.fill('input[name="password"]', 'wakasek123');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
        page.click('button[type="submit"]'),
    ]);
}

try {
    await login();

    for (const width of widths) {
        await page.setViewportSize({ width, height: width <= 430 ? 844 : 900 });

        for (const [name, path] of pages) {
            const response = await page.goto(`${base}${path}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForTimeout(180);

            const metrics = await page.evaluate(() => {
                const doc = document.documentElement;
                const offenders = [...document.querySelectorAll('body *')]
                    .filter((el) => {
                        const rect = el.getBoundingClientRect();
                        const style = getComputedStyle(el);
                        return rect.width > 0
                            && rect.right > window.innerWidth + 2
                            && style.position !== 'fixed'
                            && style.position !== 'sticky';
                    })
                    .slice(0, 4)
                    .map((el) => ({
                        tag: el.tagName.toLowerCase(),
                        cls: String(el.className || '').slice(0, 90),
                        right: Math.round(el.getBoundingClientRect().right),
                    }));

                return {
                    viewport: window.innerWidth,
                    scrollWidth: Math.max(doc.scrollWidth, document.body.scrollWidth),
                    offenders,
                };
            });

            const overflow = metrics.scrollWidth > width + 1;
            const status = response?.status() ?? 0;
            results.push({ width, name, path, status, overflow, scrollWidth: metrics.scrollWidth });

            if (overflow || status >= 500) {
                failures.push({ width, name, path, status, ...metrics });
            }

            if (width === 320 && ['dashboard', 'write-offs', 'reports', 'users', 'loan-create', 'handover', 'procurement'].includes(name)) {
                await page.screenshot({ path: `${outDir}/${width}-${name}.png`, fullPage: true });
            }

            if (width === 768 && ['dashboard', 'write-offs', 'reports'].includes(name)) {
                await page.screenshot({ path: `${outDir}/${width}-${name}.png`, fullPage: true });
            }

            if (width === 1366 && ['dashboard', 'reports'].includes(name)) {
                await page.screenshot({ path: `${outDir}/${width}-${name}.png`, fullPage: true });
            }
        }
    }

    // Modal inventaris keluar harus berada di dalam viewport ponsel kecil.
    await page.setViewportSize({ width: 320, height: 844 });
    await page.goto(`${base}/inventaris-keluar`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Seluruh katalog/i }).click();
    await page.locator('#catalog_id').selectOption({ index: 1 });
    await page.waitForTimeout(250);
    await page.getByRole('button', { name: /Tinjau & Ajukan/i }).click();
    await page.getByRole('heading', { name: /Konfirmasi penghapusan/i }).waitFor();
    const modal = await page.locator('div.fixed').filter({ has: page.getByRole('heading', { name: /Konfirmasi penghapusan/i }) }).last().boundingBox();
    const modalInside = modal !== null && modal.x >= -1 && modal.y >= -1 && modal.x + modal.width <= 321 && modal.y + modal.height <= 845;
    results.push({ width: 320, name: 'catalog-delete-modal', modalInside, modal });
    if (!modalInside) failures.push({ width: 320, modal });
    await page.screenshot({ path: `${outDir}/320-catalog-delete-modal.png`, fullPage: false });

    console.log(JSON.stringify({
        ok: failures.length === 0,
        pages: pages.length,
        widths,
        checks: results.length,
        failures,
        consoleErrors,
    }, null, 2));
} catch (error) {
    console.log(JSON.stringify({ ok: false, error: error.message, failures, consoleErrors }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}

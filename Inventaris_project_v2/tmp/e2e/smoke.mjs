import { chromium } from 'playwright';
import fs from 'node:fs';

const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const outDir = 'tmp/e2e/screens';
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
const page = await context.newPage();

const consoleErrors = [];
const checks = [];
page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push('PAGEERROR: ' + error.message));

async function shot(name) {
    await page.screenshot({ path: `${outDir}/${name}.png`, fullPage: true });
}

try {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'wakasek@gmail.com');
    await page.fill('input[name="password"]', 'wakasek123');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
        page.click('button[type="submit"]'),
    ]);
    checks.push({ check: 'login', url: page.url() });

    await page.goto(`${base}/pengadaan/create`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#catalog_id');
    const catalogSelect = page.locator('#catalog_id');
    const firstCatalog = await catalogSelect.locator('option').nth(1).getAttribute('value');
    await catalogSelect.selectOption(firstCatalog);
    await page.waitForTimeout(300);
    const nameDisabled = await page.locator('#name').isDisabled();
    const categoryDisabled = await page.locator('#category_id').isDisabled();
    await shot('01-pengadaan-katalog-lama');
    checks.push({ check: 'procurement-existing-catalog-locks-fields', nameDisabled, categoryDisabled });

    await page.goto(`${base}/inventaris-keluar`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Seluruh katalog/i }).click();
    await page.locator('#catalog_id').selectOption(firstCatalog);
    await page.waitForTimeout(300);
    await shot('02-inventaris-keluar-katalog');
    await page.getByRole('button', { name: /Tinjau & Ajukan/i }).click();
    await page.getByRole('heading', { name: /Konfirmasi penghapusan/i }).waitFor();
    checks.push({ check: 'catalog-warning-modal-visible' });
    await page.getByRole('button', { name: /^Batal$/ }).click();

    const filters = page.locator('form[method="GET"] select[name="brand"]');
    const brandValue = await filters.locator('option').nth(1).getAttribute('value');
    await filters.selectOption(brandValue);
    await page.locator('form[method="GET"] button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    checks.push({ check: 'write-off-filter-submit', url: page.url() });

    await page.goto(`${base}/laporan`, { waitUntil: 'networkidle' });
    for (const label of ['Pratinjau PDF', 'Cetak', 'Excel', 'Impor Laporan', 'Simpan ke Arsip']) {
        checks.push({ check: `report-button-${label}`, visible: await page.getByText(label, { exact: false }).first().isVisible() });
    }
    await shot('03-laporan');

    await page.goto(`${base}/mutasi`, { waitUntil: 'networkidle' });
    checks.push({ check: 'movements-multiselect', visible: await page.getByText('Pilih satu atau beberapa unit', { exact: false }).isVisible() });
    await shot('04-mutasi');

    await page.goto(`${base}/kegiatan`, { waitUntil: 'networkidle' });
    const eventName = 'E2E Kegiatan ' + Date.now();
    await page.fill('input[name="name"]', eventName);
    await page.fill('input[name="pic_name"]', 'Panitia E2E');
    await Promise.all([
        page.waitForURL(/\/kegiatan\/\d+$/, { timeout: 20000 }),
        page.getByRole('button', { name: /Simpan Kegiatan/i }).click(),
    ]);
    checks.push({ check: 'event-created', url: page.url() });

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Hapus Kegiatan/i }).click();
    await page.waitForURL(/\/kegiatan(\?.*)?$/, { timeout: 20000 });
    checks.push({ check: 'event-deleted', url: page.url() });

    await page.goto(`${base}/profile`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Ubah Kata Sandi/i }).click();
    await page.getByRole('heading', { name: /Ubah kata sandi\?/i }).waitFor();
    checks.push({ check: 'password-warning-modal-visible' });
    await shot('05-profil-password-modal');
    await page.getByRole('button', { name: /^Batal$/ }).click();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}/inventaris-keluar`, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    await shot('06-mobile-inventaris-keluar');
    checks.push({ check: 'mobile-no-horizontal-overflow', overflow });

    console.log(JSON.stringify({ ok: true, checks, consoleErrors }, null, 2));
} catch (error) {
    await page.screenshot({ path: `${outDir}/99-failure.png`, fullPage: true }).catch(() => {});
    console.log(JSON.stringify({ ok: false, error: error.message, checks, consoleErrors }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}

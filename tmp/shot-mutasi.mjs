import { chromium } from 'playwright';
import fs from 'node:fs';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const outDir = 'tmp/shots-mutasi';
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();

async function shot(name, viewport) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', (e) => errors.push(String(e)));

    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', 'laboran.rpl@gmail.com');
    await page.fill('input[name="password"]', 'laboran123');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
        page.click('button[type="submit"]'),
    ]);

    await page.goto(`${base}/mutasi`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${outDir}/${name}-top.png` });
    await page.screenshot({ path: `${outDir}/${name}-full.png`, fullPage: true });

    // Buka satu grup unit pertama untuk melihat isi tabel.
    const groupTrigger = page.locator('tr[role="button"]').first();
    if (await groupTrigger.count()) {
        await groupTrigger.click();
        await page.waitForTimeout(300);
        await page.screenshot({ path: `${outDir}/${name}-group-open.png` });
    }

    if (name === 'desktop') {
        // Buka panel filter
        const filterBtn = page.getByRole('button', { name: /^Filter/ }).first();
        if (await filterBtn.count()) {
            await filterBtn.click();
            await page.waitForTimeout(300);
            await page.screenshot({ path: `${outDir}/${name}-filters.png` });
        }
    } else {
        // Mobile: buka grup pertama juga
        const mobileGroup = page.locator('button[aria-expanded="false"]').first();
        if (await mobileGroup.count()) {
            await mobileGroup.click();
            await page.waitForTimeout(300);
            await page.screenshot({ path: `${outDir}/${name}-group-open2.png`, fullPage: true });
        }
    }

    console.log(JSON.stringify({ name, errors }, null, 2));
    await context.close();
}

await shot('desktop', { width: 1440, height: 900 });
await shot('mobile', { width: 390, height: 844 });

await browser.close();

import { chromium } from 'playwright';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const base = process.env.BASE_URL || 'http://127.0.0.1:8098';
const outDir = 'tmp/e2e/screens-e2e';
const dbName = 'inventaris_e2e';
fs.mkdirSync(outDir, { recursive: true });

const phpEnv = { ...process.env, DB_DATABASE: dbName };
function db(code) {
    const out = execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: process.cwd(),
        env: phpEnv,
        encoding: 'utf8',
    });
    const lines = out.trim().split(/\r?\n/).filter((line) => line.trim().startsWith('{'));
    if (lines.length === 0) throw new Error('DB helper tidak mengembalikan JSON: ' + out.slice(0, 400));
    return JSON.parse(lines[lines.length - 1]);
}

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

fs.writeFileSync('tmp/e2e/ba.pdf', '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');

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

async function login(email, password) {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
        page.click('button[type="submit"]'),
    ]);
}

try {
    await login('wakasek@gmail.com', 'wakasek123');
    checks.push({ check: 'login-wakasek', url: page.url() });

    const stamp = Date.now();
    const catalogName = `Kursi E2E ${stamp}`;

    // 1. Pengadaan: katalog baru, spesifikasi, unit fisik.
    await page.goto(`${base}/pengadaan/create`, { waitUntil: 'networkidle' });
    await page.fill('#name', catalogName);
    await page.locator('#category_id').selectOption({ index: 1 });
    await page.fill('#brand', 'Futura');
    await page.fill('#model_type', 'Tipe E2E');
    await page.fill('#quantity', '1');
    await page.fill('#unit', 'unit');
    await page.fill('#acquisition_year', '2026');
    await page.fill('#funding_source', 'BOS');
    await page.fill('#specification', 'Kursi uji end-to-end');

    const buildingSelect = page.locator('select[name="allocations[0][building_hint]"]');
    await buildingSelect.waitFor();
    await buildingSelect.selectOption({ index: 1 });
    const roomSelect = page.locator('select[name="allocations[0][room_id]"]');
    await roomSelect.selectOption({ index: 1 });
    await page.locator('input[name="allocations[0][quantity]"]').fill('1');
    await page.waitForTimeout(300);
    await shot('01-pengadaan');

    await Promise.all([
        page.waitForURL(/\/catalogs(\?.*)?$/, { timeout: 20000 }),
        page.getByRole('button', { name: /Ajukan Pengadaan/i }).click(),
    ]);

    const created = db(`
        $c = App\\Models\\Item::where("name", ${JSON.stringify(catalogName)})->firstOrFail();
        $u = $c->units()->firstOrFail();
        echo json_encode([
            "catalog_id" => $c->id,
            "unit_id" => $u->id,
            "code" => $u->inventory_code,
            "room_id" => $u->room_id,
            "spec_id" => $u->specification_id,
            "brand" => $u->specification?->brand,
            "model" => $u->specification?->model_type,
            "catalog_qty" => $c->quantity,
        ]).PHP_EOL;
    `);
    assert(created.catalog_qty === 1 && created.brand === 'Futura', 'Unit/spesifikasi pengadaan tidak sesuai: ' + JSON.stringify(created));
    checks.push({ check: 'procurement-created-unit', ...created });

    // 2. Label PDF dan scan QR rilis 2.
    const label = await context.request.get(`${base}/units/${created.unit_id}/label`);
    const labelBody = await label.body();
    assert(label.ok(), 'Label PDF gagal diunduh: ' + label.status());
    assert(labelBody.subarray(0, 4).toString() === '%PDF', 'Respons label bukan PDF.');
    assert(/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+260(?:\.0+)?\s+120(?:\.0+)?\s*\]/.test(labelBody.toString('latin1')), 'MediaBox label tidak 260x120.');
    checks.push({ check: 'label-pdf-media-box', bytes: labelBody.length });

    await page.goto(`${base}/scan?code=${encodeURIComponent(created.code)}&u=${created.unit_id}&r=2&s=999999`, { waitUntil: 'networkidle' });
    assert(await page.getByText('Futura').first().isVisible(), 'Scan tidak menampilkan merek dari database.');
    assert(await page.getByText('Tipe E2E').first().isVisible(), 'Scan tidak menampilkan tipe dari database.');
    checks.push({ check: 'scan-r2-database-identity' });

    // 3. Mutasi dan verifikasi identitas tetap.
    await page.goto(`${base}/mutasi?search=${encodeURIComponent(created.code)}`, { waitUntil: 'networkidle' });
    await page.locator('input[name="unit_ids[]"]').first().check();
    const destination = await page.locator('#to_room_id option').evaluateAll((options, currentRoom) => {
        const option = options.find((o) => o.value && Number(o.value) !== Number(currentRoom));
        return option ? option.value : null;
    }, created.room_id);
    assert(destination !== null, 'Tidak ada ruangan tujuan mutasi.');
    await page.locator('#to_room_id').selectOption(destination);
    await Promise.all([
        page.waitForURL(/\/mutasi(\?.*)?$/, { timeout: 20000 }),
        page.getByRole('button', { name: /Simpan Mutasi/i }).click(),
    ]);
    await shot('02-mutasi');

    const moved = db(`
        $u = App\\Models\\AssetUnit::with("specification","room")->findOrFail(${created.unit_id});
        echo json_encode([
            "room_id" => $u->room_id,
            "code" => $u->inventory_code,
            "brand" => $u->specification?->brand,
            "spec_id" => $u->specification_id,
            "movements" => $u->movements()->count(),
        ]).PHP_EOL;
    `);
    assert(Number(moved.room_id) === Number(destination), 'Lokasi unit tidak berubah.');
    assert(moved.brand === 'Futura' && Number(moved.spec_id) === Number(created.spec_id), 'Mutasi mengubah identitas spesifikasi.');
    assert(moved.movements === 1, 'Histori mutasi tidak tercatat.');
    checks.push({ check: 'mutation-keeps-spec-and-records-history', ...moved });

    await page.goto(`${base}/scan?u=${created.unit_id}&c=${encodeURIComponent(moved.code)}&r=2`, { waitUntil: 'networkidle' });
    assert(await page.getByText('Futura').first().isVisible(), 'Scan setelah mutasi kehilangan merek.');
    checks.push({ check: 'scan-after-mutation' });

    // 4. Peminjaman melalui UI.
    await page.goto(`${base}/loans/create`, { waitUntil: 'networkidle' });
    await page.getByPlaceholder('Cari nama, spesifikasi, atau kode inventaris').fill(catalogName);
    await page.waitForTimeout(900);
    const row = page.locator('li').filter({ hasText: catalogName }).first();
    await row.getByRole('button', { name: /Tambah/i }).click();
    await page.waitForTimeout(500);
    await page.locator('textarea[wire\\:model\\.live="purpose"]').fill('Uji end-to-end inventaris');
    const start = new Date(Date.now() + 86400000);
    const due = new Date(Date.now() + 2 * 86400000);
    const local = (date) => date.toISOString().slice(0, 16);
    await page.locator('input[type="datetime-local"]').nth(0).fill(local(start));
    await page.locator('input[type="datetime-local"]').nth(1).fill(local(due));
    await Promise.all([
        page.waitForURL(/\/loans\/\d+/, { timeout: 20000 }),
        page.getByRole('button', { name: /Kirim pengajuan/i }).click(),
    ]);
    const loanId = Number(page.url().match(/\/loans\/(\d+)/)[1]);
    const loan = db(`
        $l = App\\Models\\Loan::findOrFail(${loanId});
        echo json_encode(["id"=>$l->id,"status"=>$l->status->value,"qty"=>$l->loanItems()->sum("qty_borrowed")]).PHP_EOL;
    `);
    assert(loan.status === 'APPROVED', 'Peminjaman Wakasek tidak otomatis disetujui: ' + loan.status);
    checks.push({ check: 'loan-request-approved', ...loan });

    // 5. Serah terima memilih unit fisik.
    await page.goto(`${base}/loans/${loanId}/serah-terima`, { waitUntil: 'networkidle' });
    await page.locator(`input[name^="unit_assignments"][value="${created.unit_id}"]`).check();
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.getByRole('button', { name: /Serahkan Barang/i }).click(),
    ]);
    await shot('03-serah-terima');
    const handover = db(`
        $l = App\\Models\\Loan::findOrFail(${loanId});
        $u = App\\Models\\AssetUnit::findOrFail(${created.unit_id});
        echo json_encode(["status"=>$l->status->value,"unit_status"=>$u->status->value,"assignments"=>$l->loanItems()->withCount("unitAssignments")->get()->sum("unit_assignments_count")]).PHP_EOL;
    `);
    assert(handover.status === 'DIPINJAM' && handover.unit_status === 'DIPINJAM', 'Serah terima tidak mengubah status: ' + JSON.stringify(handover));
    assert(handover.assignments >= 1, 'Penugasan unit tidak tercatat.');
    checks.push({ check: 'handover-unit-assigned', ...handover });

    // 6. Pengembalian per unit dan penyelesaian.
    await page.goto(`${base}/pengembalian/${loanId}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Konfirmasi unit ini/i }).first().click();
    await page.waitForTimeout(700);
    await page.getByRole('button', { name: /Selesaikan Pengembalian/i }).click();
    await page.waitForTimeout(1200);
    await shot('04-pengembalian');
    const returned = db(`
        $l = App\\Models\\Loan::findOrFail(${loanId});
        $u = App\\Models\\AssetUnit::findOrFail(${created.unit_id});
        echo json_encode(["status"=>$l->status->value,"unit_status"=>$u->status->value,"returned_at"=>$l->returned_at?->toDateTimeString()]).PHP_EOL;
    `);
    assert(returned.status === 'SELESAI' && returned.unit_status === 'AKTIF', 'Pengembalian tidak selesai: ' + JSON.stringify(returned));
    checks.push({ check: 'return-completed', ...returned });

    // 7. Inventaris keluar langsung oleh Wakasek.
    await page.goto(`${base}/inventaris-keluar?search=${encodeURIComponent(moved.code)}`, { waitUntil: 'networkidle' });
    await page.locator('input[name="unit_ids[]"]').first().check();
    await page.locator('#reason').fill('Uji end-to-end penghapusan unit');
    await page.locator('input[name="berita_acara"]').setInputFiles('tmp/e2e/ba.pdf');
    await page.getByRole('button', { name: /Tinjau & Ajukan/i }).click();
    await page.getByRole('heading', { name: /Konfirmasi penghapusan/i }).waitFor();
    await page.getByRole('button', { name: /Ya, ajukan penghapusan/i }).click();
    await page.waitForLoadState('networkidle');
    await shot('05-inventaris-keluar');

    const writeOff = db(`
        $u = App\\Models\\AssetUnit::findOrFail(${created.unit_id});
        $w = App\\Models\\WriteOff::latest("id")->firstOrFail();
        echo json_encode([
            "unit_status" => $u->status->value,
            "archived_at" => $u->archived_at?->toDateTimeString(),
            "write_off_status" => $w->status,
            "write_off_units" => $w->units()->count(),
            "catalog_qty" => App\\Models\\Item::findOrFail(${created.catalog_id})->quantity,
        ]).PHP_EOL;
    `);
    assert(writeOff.unit_status === 'DIHAPUS' && writeOff.write_off_status === 'APPROVED', 'Penghapusan tidak diterapkan: ' + JSON.stringify(writeOff));
    assert(writeOff.write_off_units === 1 && writeOff.catalog_qty === 0, 'Snapshot/stock penghapusan tidak konsisten.');
    checks.push({ check: 'write-off-applied-and-recorded', ...writeOff });

    // 8. QR/role failure paths.
    await page.goto(`${base}/scan?code=INVALID&r=99`, { waitUntil: 'networkidle' });
    assert(await page.getByText('Versi QR tidak didukung').isVisible(), 'QR versi tidak dikenal tidak ditolak.');

    const guruContext = await browser.newContext();
    const guruPage = await guruContext.newPage();
    await guruPage.goto(`${base}/login`);
    await guruPage.fill('input[name="email"]', 'guru.budi@gmail.com');
    await guruPage.fill('input[name="password"]', 'guru123');
    await Promise.all([
        guruPage.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
        guruPage.click('button[type="submit"]'),
    ]);
    const usersStatus = await guruPage.request.get(`${base}/pengguna`).then((r) => r.status());
    assert([403, 302].includes(usersStatus), 'Guru dapat membuka manajemen pengguna: ' + usersStatus);
    checks.push({ check: 'role-guru-users-denied', status: usersStatus });
    await guruContext.close();

    console.log(JSON.stringify({ ok: true, checks, consoleErrors }, null, 2));
} catch (error) {
    await page.screenshot({ path: `${outDir}/99-failure.png`, fullPage: true }).catch(() => {});
    console.log(JSON.stringify({ ok: false, error: error.message, checks, consoleErrors }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}

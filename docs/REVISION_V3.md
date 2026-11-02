# Revisi V3 — Audit, Implementasi, dan Hasil Pengujian

Tanggal: **2 November 2026**
Status: **SELESAI DENGAN CATATAN** — seluruh revisi yang dapat diimplementasikan sudah diterapkan; pengujian fisik label belum dapat dilakukan karena tidak ada printer/media/pemindai pada lingkungan ini.

Dokumen ini melengkapi `REVISION_V2.md`. Skema tetap mengacu ke `DATABASE.md`.

---

## 1. Ringkasan per revisi

| Revisi | Status | Bukti utama |
| --- | --- | --- |
| 1. Identitas spesifikasi unit + backfill data lama | **PASS** | `inventory:backfill-specifications`, test backfill ambigu/tunggal/alokasi, idempoten |
| 2. Penghapusan per unit dan seluruh katalog | **PASS** | `write_off_units`, snapshot identitas, locking, rollback, test unit/katalog |
| 3. Filter inventaris keluar | **PASS** | 10 parameter AND, pagination, reset; test kombinasi merek+kondisi+gedung+lantai |
| 4. Audit tombol laporan bulanan | **PASS (visual terbatas)** | Kelas tombol diseragamkan, konfirmasi hapus, loading state, cetak via popup; browser smoke |
| 5. Keamanan perubahan password | **PASS** | `current_password`, `different`, `confirmed`, rate limit 6/menit, sesi diregenerasi |
| 6. Otorisasi penghapusan kegiatan | **PASS** | `EventPolicy`, cek backend, relasi peminjaman, guard akun pembuat |
| 7. Kompatibilitas QR rilis 1 & 2 | **PASS** | Parser menerima `r=1`/`r=2`, menolak versi lain, mengabaikan `s` palsu |
| 8. Pengujian MySQL & transaksi konkuren | **PASS (lingkungan lokal)** | MySQL 8.4.3, 40 tabel, 5 FK, dua proses paralel, 0 duplikasi |
| 9. Pengujian frontend/browser/E2E | **PASS (smoke)** | Playwright 1.64.1, 12 checkpoint, 0 console error, screenshot |
| 10. Pengujian cetak fisik label/QR | **BLOCKED (fisik)** | PDF `MediaBox` 260×120 pt, QR persegi; cetak fisik **BELUM DIVERIFIKASI** |

---

## 2. Revisi 1 — identitas spesifikasi unit dan backfill

### Akar masalah

Label/scan sebelumnya membaca `item_catalogs.brand` dan `model_type`. Unit lama juga dapat memiliki `specification_id` kosong, sehingga identitas merek/tipe tidak dapat dibedakan bila satu katalog memiliki beberapa spesifikasi.

### Implementasi

- `AssetUnit.specification_id` menjadi sumber identitas utama; `AssetUnitService::create()` selalu mengisi spesifikasi (default katalog bila tidak diberikan).
- Label, PDF massal, hasil scan, dan detail unit membaca relasi `specification`; kolom katalog hanya fallback untuk unit yang belum berhasil dipetakan.
- Command `php artisan inventory:backfill-specifications`:
  - default **simulasi**; gunakan `--apply` untuk menyimpan;
  - opsi `--catalog=` dan `--report=`;
  - hanya memetakan bila buktinya tunggal: satu spesifikasi katalog, kecocokan merek/tipe katalog dengan tepat satu spesifikasi, atau alokasi pengadaan ruangan yang menunjuk satu spesifikasi;
  - tidak menebak bila ada beberapa kandidat;
  - menulis laporan CSV ke `storage/app/reports/`;
  - idempoten (hanya memproses `specification_id IS NULL`).

### Batasan

Unit ambigu tetap dibiarkan `NULL` dan menggunakan fallback katalog sampai diverifikasi manual. Fallback **tidak dihapus** pada revisi ini.

---

## 3. Revisi 2 — penghapusan per unit dan seluruh katalog

### Skema

Migration `2026_11_02_000002_add_unit_based_write_offs.php`:

- `write_offs.mode` (`CATALOG` default, `UNIT`).
- Tabel `write_off_units`:
  - `write_off_id` (cascade saat pengajuan dihapus),
  - `asset_unit_id` (`restrictOnDelete` agar histori tidak kehilangan unit),
  - `item_catalog_id`, `specification_id`, `room_id`,
  - snapshot `inventory_code`, `brand`, `model_type`, `condition`, `status_before`,
  - unique `(write_off_id, asset_unit_id)`.

### Alur

- **Unit individual**: filter + checkbox + pilih semua sesuai filter; backend memvalidasi cakupan, status, pinjaman aktif, dan pengajuan PENDING lain.
- **Seluruh katalog**: konfirmasi khusus (`confirm_catalog`) + ringkasan nama katalog, jumlah spesifikasi, total unit, unit layak, rincian merek/tipe, dan lokasi.
- Transaksi memakai `lockForUpdate()` pada unit; satu unit gagal → seluruh transaksi rollback.
- Unit diarsipkan (`status = DIHAPUS`, `archived_at`), katalog/spesifikasi/histori tidak dihapus.
- Alur lama `item_id + quantity` tetap didukung untuk kompatibilitas; tanpa snapshot unit dan memakai `archiveUnits()` legacy.

---

## 4. Revisi 3 — filter inventaris keluar

Filter yang tersedia dan digabung AND:

1. kode/nama barang, 2. kategori, 3. katalog, 4. merek, 5. tipe/model, 6. gedung, 7. lantai, 8. ruangan, 9. kondisi, 10. status.

- Pagination 25 unit dengan `withQueryString()`.
- “Pilih semua sesuai filter” mengirim filter ke backend sehingga mencakup seluruh hasil filter, bukan hanya halaman aktif.
- Reset filter mengembalikan daftar awal.
- Unit yang sedang dipinjam atau menunggu penghapusan lain ditampilkan tetapi checkbox-nya dinonaktifkan dan backend menolak.

---

## 5. Revisi 4 — tombol laporan bulanan

- Tombol Pratinjau PDF, Cetak, Excel, Impor, Simpan ke Arsip, dan Hapus memakai tinggi minimum, padding, radius, ikon, dan tipografi yang konsisten.
- Hapus memakai warna bahaya, ikon, konfirmasi periode/cakupan, dan status `Menghapus...`.
- Simpan ke Arsip memiliki status `Menyimpan...` dan mencegah klik ganda.
- Cetak membuka PDF lalu memanggil `print()` setelah load; bila pop-up diblokir, pengguna diberi pesan.
- Browser smoke memverifikasi kelima tombol utama terlihat dan tidak ada error konsol.

Catatan: audit visual manual per piksel pada semua breakpoint belum dilakukan; verifikasi dilakukan melalui kelas Tailwind yang konsisten dan screenshot desktop/mobile.

---

## 6. Revisi 5 — keamanan perubahan password

- Backend: `current_password` wajib dan benar, `Password::defaults()`, `confirmed`, dan `different:current_password`.
- Rate limit `throttle:6,1` pada route `password.update`.
- Password di-hash dengan `Hash::make`; tidak ada log password.
- Sesi aktif diregenerasi setelah berhasil.
- **Sesi lain tidak diputus paksa** karena middleware `AuthenticateSession` tidak diaktifkan; dokumentasi tidak mengklaim logout global.
- Pengujian: password lama salah, password sama, konfirmasi berbeda, request tanpa autentikasi, dan percobaan ke-7 (429).

---

## 7. Revisi 6 — otorisasi penghapusan kegiatan

- `App\Policies\EventPolicy::delete()` memeriksa pembuat dan tidak adanya peminjaman; controller memanggil `Gate::authorize('delete', $event)`.
- Frontend hanya menyembunyikan tombol; backend tetap menolak request langsung.
- Super Admin tidak otomatis dapat menghapus kegiatan milik orang lain.
- PIC tidak mengubah kepemilikan.
- Akun pembuat tidak dapat dihapus bila sudah membuat kegiatan (`422`), mencegah relasi yatim/foreign key gagal.
- Pengujian: pembuat, pengguna lain, Super Admin bukan pembuat, kegiatan dengan peminjaman.

---

## 8. Revisi 7 — kompatibilitas QR rilis 1 dan 2

- `LabelService::QR_RELEASE = 2`.
- Payload rilis 2: `u` (ID unit stabil), `c` (kode), `s` (ID spesifikasi sebagai petunjuk), `r` (versi), plus metadata lokasi.
- Parser menerima `r=1`, `r=2`, dan payload tanpa `r` (stiker lama).
- Versi lain ditolak dengan pesan “Versi QR tidak didukung”.
- `s` dari payload **tidak dipercaya**; server mengambil spesifikasi dan lokasi terbaru dari database.
- Unit terhapus tetap dapat ditelusuri dengan penanda arsip.
- Pengujian: rilis 1, rilis 2 dengan `s` palsu, versi 99, lokasi lama setelah mutasi.

---

## 9. Revisi 8 — pengujian MySQL dan konkuren

Lingkungan: **MySQL 8.4.3**, database terpisah `inventaris_v3_test` (bukan database development/produksi).

### Migrasi

- `php artisan migrate:fresh --force --seed` berhasil.
- 40 tabel, `write_off_units` ada, `write_offs.mode` ada, 5 foreign key pada `write_off_units`.
- 165 unit, 42 katalog, 13 pengguna hasil seed.
- Rollback/`down()` tidak diuji dengan `migrate:rollback`; migration bersifat additive dan memeriksa keberadaan objek.

### Konkuren

- Dua proses paralel membuat unit pada katalog/ruangan yang sama:
  - keduanya berhasil dengan `unit_no` berbeda (14 dan 15), kode berbeda;
  - `duplicate_codes=0`, `duplicate_slots=0`, `multi_active_codes=0`.
- Dua proses paralel memindahkan unit yang sama:
  - keduanya berhasil **berurutan** karena row lock;
  - 2 baris riwayat mutasi, 0 kode aktif ganda, 0 kode ledger ganda, 0 mutasi yatim.
- Kesimpulan: locking + unique constraint mencegah duplikasi; dua permintaan mutasi yang sah diproses berurutan, bukan sebagai satu unit “dua kali” dalam transaksi yang sama.

Batasan: uji ini memakai dua proses lokal, bukan load test puluhan worker; hasil tidak digeneralisasi ke beban produksi tinggi.

---

## 10. Revisi 9 — pengujian browser dan end-to-end

Alat: **Playwright 1.64.1** (sudah ada di `devDependencies`), Chromium headless, server `php artisan serve` pada database uji MySQL.

Checkpoint yang lulus:

- login Wakasek → dashboard;
- pengadaan: memilih katalog lama menonaktifkan nama & kategori;
- inventaris keluar: tab seluruh katalog, ringkasan peringatan, modal konfirmasi, filter merek;
- laporan: Pratinjau PDF, Cetak, Excel, Impor, Simpan ke Arsip terlihat;
- mutasi: teks multi-select terlihat;
- kegiatan: membuat kegiatan via UI lalu menghapusnya (pembuat) dan kembali ke daftar;
- profil: modal peringatan ubah password, tombol Batal menutup modal;
- mobile 390×844: tidak ada overflow horizontal.

Hasil: **0 console error / page error**, screenshot di `tmp/e2e/screens/`.

Batasan: ini **browser smoke**, bukan uji end-to-end penuh pengadaan → unit → label → scan → mutasi → pinjam → serah terima → kembali → hapus. Alur transaksi tersebut diverifikasi melalui feature test; interaksi browser untuk seluruh rantai belum diotomasi.

---

## 11. Revisi 10 — label dan QR

Pemeriksaan digital:

- PDF berhasil dihasilkan (`%PDF`).
- `MediaBox` label = **260 × 120 pt**, sesuai media label yang dipertahankan.
- QR PNG persegi, resolusi > 100 px.
- Nama barang, spesifikasi unit, dan kode inventaris berasal dari sumber data yang sama dengan detail unit.
- Template label massal memakai identitas spesifikasi per unit.

Pengujian fisik: **BELUM DIVERIFIKASI**. Tidak ada printer, media label, atau pemindai pada lingkungan ini. Checklist yang perlu dilakukan sebelum go-live:

1. Cetak pada media label yang disepakati.
2. Ukur lebar/tinggi aktual dan posisi margin.
3. Pastikan teks nama/spesifikasi/kode tidak terpotong.
4. Pindai QR dengan minimal dua perangkat (Android & iPhone).
5. Cetak ulang setelah mutasi dan pastikan lokasi berubah, merek/tipe tetap.

---

## 12. File utama yang berubah

**Database**

- `database/migrations/2026_11_02_000001_add_pic_user_id_to_events_table.php`
- `database/migrations/2026_11_02_000002_add_unit_based_write_offs.php`

**Backend**

- `app/Console/Commands/BackfillUnitSpecifications.php`
- `app/Http/Controllers/WriteOffController.php`
- `app/Http/Controllers/LabelController.php`
- `app/Http/Controllers/EventController.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/Auth/PasswordController.php`
- `app/Policies/EventPolicy.php`
- `app/Models/WriteOff.php`, `WriteOffUnit.php`, `AssetUnit.php`, `Event.php`
- `app/Services/LabelService.php`
- `routes/web.php`, `routes/auth.php`

**Frontend**

- `resources/views/write-offs/index.blade.php`, `pending.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/labels/scan.blade.php`
- `resources/views/pdf/unit_label.blade.php`, `unit_massal.blade.php`
- `resources/views/events/_pic_fields.blade.php`, `events/index.blade.php`, `events/show.blade.php`
- `resources/views/items/create.blade.php`
- `resources/views/users/_form.blade.php`
- `resources/views/profile/partials/update-password-form.blade.php`

**Pengujian**

- `tests/Feature/RevisionV3Test.php`
- `tests/Feature/RevisionV3AuditTest.php`
- `tmp/mysql_concurrency_create.php`, `tmp/mysql_concurrency_move.php`
- `tmp/e2e/smoke.mjs`

---

## 13. Hasil pengujian

| Lapisan | Status | Hasil |
| --- | --- | --- |
| SQLite feature/unit suite | **PASS** | 322 tes, 1602 assertions |
| MySQL migration + seed | **PASS** | MySQL 8.4.3, 40 tabel, 5 FK `write_off_units` |
| MySQL konkuren (2 proses) | **PASS (terbatas)** | 0 duplikasi kode/slot; mutasi terserialisasi |
| Browser Playwright | **PASS (smoke)** | 12 checkpoint, 0 console error |
| End-to-end penuh | **NOT TESTED** | Rantai transaksi lengkap belum diotomasi di browser |
| Cetak fisik label/QR | **BLOCKED** | Tidak ada printer/media/pemindai |
| Audit visual semua breakpoint | **PASS (terbatas)** | Desktop + mobile 390px, screenshot |

---

## 14. Risiko dan pekerjaan tersisa (status akhir V3)

> Beberapa butir di bawah diselesaikan pada revisi V4 (§16). Status ini dipertahankan sebagai riwayat.

1. **Unit ambigu belum dipetakan.** Jalankan command dengan `--apply` lalu verifikasi laporan CSV; jangan hapus fallback sebelum semua unit valid. (V4 menambah kolom kandidat/status/manual_check.)
2. **Cetak fisik label belum diverifikasi.** Jangan menganggap PDF valid sebagai bukti label dapat dipindai. (Masih berlaku.)
3. **Browser E2E belum mencakup seluruh rantai transaksi.** **Diselesaikan di V4** — Playwright menjalankan pengadaan → label → scan → mutasi → pinjam → serah terima → kembali → hapus.
4. **Konkurensi diuji dengan dua proses lokal**, bukan load test produksi. **Diperluas di V4** menjadi 6 pembuat dan 3 penghapusan paralel; tetap bukan load test produksi.
5. **`write_offs.item_id`** masih menyimpan satu katalog untuk kompatibilitas. **Diselesaikan di V4** — laporan PDF/Excel/ringkasan memakai `write_off_units`, dengan baris legacy terpisah.
6. **Sesi lain tidak diputus** setelah perubahan password. **Diselesaikan di V4** — `AuthenticateSession` + hash sesi diperbarui pada sesi aktif.

---

## 15. Perintah operasional

```bash
# Backfill spesifikasi (simulasi lalu terapkan)
php artisan inventory:backfill-specifications
php artisan inventory:backfill-specifications --apply
php artisan inventory:backfill-specifications --apply --catalog=12

# Verifikasi kode inventaris
php artisan inventory:verify-codes

# Pengujian
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

---

## 16. Revisi V4 — 12 revisi lanjutan (2 November 2026)

Status keseluruhan: **IMPLEMENTATION COMPLETE + TESTING COMPLETE untuk pengujian yang tersedia**, dengan satu validasi lingkungan **BLOCKED** (cetak fisik).

| # | Revisi | Status | Bukti |
| --- | --- | --- | --- |
| 1 | Laporan penghapusan lintas katalog | **PASS** | `ReportService` membangun baris dari `write_off_units`; PDF/Excel/ringkasan; `RevisionV4Test` dua katalog + legacy |
| 2 | Unit sama pada transaksi berbeda | **PASS** | Unique `(asset_unit_id, is_active)`, locking, rollback, penolakan melepas kunci |
| 3 | Sinkronisasi stok CATALOG/UNIT | **PASS** | Quantity = jumlah snapshot; stok dihitung ulang dari unit; test 5 → 3 → 0 |
| 4 | Backfill spesifikasi | **PASS** | Dry-run/apply, CSV 9 kolom, status `DIPETAKAN/SUDAH_BENAR/TIDAK_DIPETAKAN/KATALOG_HILANG/GAGAL`, idempoten |
| 5 | Invalidasi seluruh sesi | **PASS (mekanisme)** | `AuthenticateSession`; sesi aktif tetap login, hash lama ditolak; belum diuji dua perangkat fisik |
| 6 | Otorisasi hapus kegiatan | **PASS** | Pembuat atau Super Admin; PIC bukan pemilik; kegiatan dengan peminjaman ditolak |
| 7 | QR r1/r2 + keamanan | **PASS** | Versi tak dikenal/d rusak ditolak; identitas & lokasi dari DB; mismatch kode diperingatkan; unit arsip ditandai |
| 8 | Konkurensi inventaris | **PASS (lokal)** | 6 create paralel unik; 3 write-off unit sama → 1 sukses; 3 unit berbeda → stok konsisten |
| 9 | Playwright E2E | **PASS** | Rantai penuh di MySQL, verifikasi DB setiap tahap, 0 console error |
| 10 | Validasi cetak fisik | **BLOCKED** | PDF/QR digital PASS; printer/media/pemindai tidak tersedia |
| 11 | Responsivitas | **PASS** | 24 halaman × 8 breakpoint + modal, 193 pemeriksaan; dua perbaikan layout |
| 12 | Dokumentasi & Known Issues | **PASS** | `DATABASE.md`, `TESTING.md` §15, `PROJECT_STATUS.md`, `KNOWN_ISSUES.md` butir 15/32/34/35/37–40 |

### 16.1 Perubahan database

- Migration `2026_11_02_000003_enforce_single_active_write_off_per_unit.php`:
  - `write_off_units.is_active` nullable;
  - backfill data lama: baris PENDING/APPROVED terbaru per unit = `1`, lainnya `NULL`;
  - unique `write_off_units_unit_active_unique (asset_unit_id, is_active)`.
- `WriteOffUnit` menambahkan cast boolean; `WriteOff` menambahkan `approved_at` ke fillable/cast.
- Tidak ada penghapusan data. Transaksi lama tanpa unit tetap ditampilkan sebagai agregat.

### 16.2 Perubahan perilaku

- Pengajuan/approval mengunci ulang unit, memvalidasi status dan jumlah, lalu menghitung stok sekali dari unit.
- Penolakan pengajuan mengubah snapshot menjadi `is_active = NULL`; unit dapat diajukan kembali.
- Laporan bulanan tidak lagi bergantung pada `write_offs.item_id`; `penghapusan` = jumlah unit, `penghapusan_transaksi` = jumlah transaksi.
- `destroy` kegiatan mengizinkan Super Admin, tetapi relasi peminjaman tetap menjadi blokir mutlak.
- Password berubah → hash sesi aktif diperbarui; request lain dengan hash lama dipaksa login ulang.
- QR dengan kode berbeda dari database menampilkan peringatan, tetapi selalu memakai data database.

### 16.3 Hasil pengujian

| Lapisan | Status | Hasil |
| --- | --- | --- |
| Suite SQLite | **PASS** | 330 tes, 1682 assertions |
| MySQL migration | **PASS** | MySQL 8.4.3, kolom + unique index + 5 FK terpasang |
| Konkurensi MySQL | **PASS (lokal)** | 6 create, 3 write-off unit sama, 3 unit berbeda; tanpa duplikasi/stok negatif |
| Playwright E2E | **PASS** | 8 tahap transaksi + verifikasi DB + skenario role/QR invalid |
| Responsif | **PASS** | 193 pemeriksaan, 0 overflow, 0 console error |
| Cetak fisik | **BLOCKED** | Perangkat tidak tersedia; status **BELUM DIVERIFIKASI** |

### 16.4 Batasan yang masih berlaku

- Unit dengan beberapa kandidat spesifikasi tetap memerlukan verifikasi manual (CSV `manual_check=YA`).
- Satu BA per pengajuan, bukan per unit.
- E2E/konkurensi lokal bukan bukti kapasitas produksi.
- Cetak fisik dan pemindaian perangkat nyata belum dilakukan.
- Sesi ganda diuji melalui simulasi hash, belum dua perangkat fisik.

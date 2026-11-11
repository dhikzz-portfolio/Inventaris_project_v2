# Laporan Hasil Tes & Audit Project

Tanggal eksekusi: **11 November 2026**
Cakupan: seluruh kode aplikasi (`app`, `routes`, `resources`, `database`, `config`, `tests`), aset frontend, migrasi & seeder, serta log runtime.

## 1. Ringkasan

| Status | Jumlah |
| ------ | ------ |
| Tes otomatis lulus | **452** (2.601 assertions), 0 gagal, 0 error, durasi 732 detik |
| Pemeriksaan statis gagal | 0 (Pint, sintaks PHP, kompilasi Blade, route, build Vite, migrasi+seeder, cache produksi) |
| Temuan baru | 6 (1 sedang, 5 rendah) |
| Perbaikan kode pada sesi ini | 1 fitur (kategori impor laporan) + 3 tes baru |

**Kesimpulan:** tidak ada fitur yang gagal pada test suite. Seluruh route dapat dimuat, seluruh template Blade dapat dikompilasi, seluruh migrasi + seeder berjalan bersih pada database kosong, dan build frontend berhasil. Temuan yang ada bersifat operasional/kebersihan kode, bukan kegagalan fitur yang terdeteksi tes — kecuali risiko *out of memory* DomPDF pada laporan besar yang sudah terjadi di log runtime.

## 2. Perbaikan yang dilakukan: kategori pada impor laporan

### Perilaku sebelum perbaikan
- Kolom `kategori` hanya dipakai ketika **katalog baru** dibuat (mode `create` dan nama barang belum terdaftar). Kategori baru memang dibuat otomatis, tetapi pencocokan nama kategori tidak konsisten (tanpa `trim`).
- Bila nama barang sudah ada di database, kolom `kategori` **diabaikan sepenuhnya** — katalog yang masih berlabel `Tanpa Kategori` tidak pernah terisi.
- Pada mode `perbarui`, kolom `kategori` diabaikan sepenuhnya.

### Perilaku setelah perbaikan
- Kolom `kategori` dibaca pada **kedua mode** (buat dan perbarui).
- Katalog yang belum punya kategori (kosong atau masih `Tanpa Kategori`) kini **diisi dari berkas**; kategori baru dibuat otomatis bila belum ada, dengan pencocokan tanpa membedakan huruf besar/kecil.
- Katalog yang **sudah punya kategori tidak ditimpa** oleh berkas (keputusan: mencegah perubahan data master secara diam-diam saat impor ulang laporan bulanan).
- Pratinjau menampilkan catatan bila kategori akan dibuat/diisi, sehingga pengguna tahu perubahan sebelum menekan "Impor Sekarang".
- Bila perubahan kategori berpotensi melanggar kunci unik katalog `(kategori, nama, merek, tipe)`, kategori katalog dibiarkan apa adanya agar seluruh impor tidak dibatalkan.
- Petunjuk pada file contoh impor (`UnitImportTemplateExport`) diperbarui sesuai perilaku baru.

### File yang diubah
| File | Perubahan |
| ---- | --------- |
| `app/Services/ReportImportService.php` | `analyze()` membaca kategori di kedua mode + catatan pratinjau; helper baru `ensureCatalogCategory()`, `hasPlaceholderCategory()`, `resolveCategory()`; pengisian kategori untuk unit baru maupun unit yang diperbarui |
| `app/Exports/UnitImportTemplateExport.php` | Teks panduan kolom `kategori` disesuaikan |
| `tests/Feature/ReportImportAndArchiveTest.php` | 3 tes baru (lihat bawah) |

### Tes baru
- `test_mode_buat_mengisi_kategori_katalog_lama_yang_masih_tanpa_kategori`
- `test_mode_perbarui_mengisi_kategori_katalog_dari_kolom_kategori`
- `test_mode_perbarui_tidak_menimpa_kategori_katalog_yang_sudah_terisi`

Hasil: `php artisan test --compact tests/Feature/ReportImportAndArchiveTest.php` → **26 tes lulus (150 assertions)**, Pint bersih.

## 3. Hasil pemeriksaan

| # | Pemeriksaan | Perintah | Hasil |
| - | ----------- | -------- | ----- |
| 1 | Seluruh test suite | `php artisan test --debug` | **452 lulus, 0 gagal** (2.601 assertions, 732 dtk) |
| 2 | Gaya kode (seluruh project) | `vendor/bin/pint --test --format agent` | Lulus |
| 3 | Sintaks PHP (270 file) | `php -l` untuk `app config database routes tests` | 270 file tanpa error |
| 4 | Kompilasi Blade | `php artisan view:cache` | Berhasil |
| 5 | Registrasi route | `php artisan route:list --except-vendor` | Berhasil, 126 nama route unik |
| 6 | Build frontend | `npm run build` | Berhasil (hanya peringatan ukuran chunk > 500 kB) |
| 7 | Migrasi + seeder dari nol | `DB_DATABASE=':memory:' php artisan migrate:fresh --seed` | Berhasil, seluruh 59 migrasi + seeder |
| 8 | Cache produksi | `config:cache`, `route:cache`, `view:cache` lalu `optimize:clear` | Semua berhasil |
| 9 | Validitas composer | `composer validate` | Valid |
| 10 | Penjadwalan | `php artisan schedule:list` | 3 job terjadwal (CheckOverdueLoans, CheckDueSoonLoans, GenerateMonthlyReport) |
| 11 | Referensi `route()` di Blade/app | Perbandingan 119 referensi dengan 126 nama route | Semua terdefinisi, kecuali 5 referensi pada view mati (temuan F2) |
| 12 | Referensi `view()` di controller | Cek keberadaan file Blade | Semua ada |
| 13 | Migrasi database dev | `php artisan migrate:status` | Semua migrasi berstatus `Ran` |

## 4. Temuan

### F1 — DomPDF kehabisan memori pada laporan besar (Sedang)
- **Bukti:** `storage/logs/laravel.log` baris ±34.448–34.663 — 5 kejadian `Allowed memory size of 536870912 bytes exhausted`, 9 November 2026 pukul 20:51–23:04, pada `vendor/dompdf/dompdf/src/Css/Style.php:2286` dan `Cellmap.php:458`.
- **Kode terkait:** `app/Services/ReportService.php:373` (`generateMonthly`) dan `:427` (`streamPdf`); pola serupa di `BastService` dan `LabelService`.
- **Dampak:** permintaan PDF gagal total (fatal error) untuk dataset besar; pengguna tidak menerima berkas. `docs/KNOWN_ISSUES.md` #19 baru mencatat "DomPDF dapat lambat", belum mencatat kegagalan memori.
- **Rekomendasi:** batasi periode/cakupan sebelum render, sediakan jalur Excel untuk data besar, naikkan `memory_limit` proses web/queue, atau pindahkan pembuatan PDF ke job antrean. Tambahkan tes regresi dengan dataset besar (mis. 1.000+ unit).

### F2 — Sisa kode Breeze merujuk route yang sudah tidak ada (Rendah)
View/partial berikut tidak dirender oleh route mana pun, tetapi berisi tautan ke route yang tidak terdaftar:
- `resources/views/welcome.blade.php:41` → `route('register')` (registrasi mandiri sengaja dinonaktifkan; `/` dialihkan ke `/dashboard`).
- `resources/views/auth/forgot-password.blade.php:9` → `route('password.email')`.
- `resources/views/auth/reset-password.blade.php:2` → `route('password.store')`.
- `resources/views/auth/verify-email.blade.php:13` → `route('verification.send')`.
- `resources/views/profile/partials/delete-user-form.blade.php:18` → `route('profile.destroy')` (partial ini tidak di-include oleh `resources/views/profile/edit.blade.php`).

Controller Breeze yang juga tidak terpakai: `PasswordResetLinkController`, `NewPasswordController`, `EmailVerificationPromptController`, `EmailVerificationNotificationController`, `VerifyEmailController`.
- **Dampak:** tidak menimbulkan error saat ini karena tidak ada route yang merendernya. Namun bila fitur reset password/verifikasi diaktifkan kembali, halaman akan gagal dengan `RouteNotFoundException`.
- **Rekomendasi:** hapus view/controller sisa tersebut, atau daftarkan route-nya secara sengaja beserta tesnya.

### F3 — Artefak dan skrip debug ikut ter-commit (Rendah)
- `check_sfcreate.php` (berisi `var_dump`), `config_cred_check.sh`.
- `tmp/` (74 file ter-track: `barrier-*.flag`, `c1.json`, `c2.json`, artefak E2E) dan `test-results/.last-run.json`.
- **Dampak:** memperbesar repositori, membingungkan kontributor, dan berisiko membocorkan detail lingkungan.
- **Rekomendasi:** keluarkan dari index Git dan tambahkan ke `.gitignore` (perlu persetujuan karena menghapus file dari repositori).

### F4 — Dokumentasi pengujian sudah usang (Rendah)
- `docs/TESTING.md` menyebut 330 tes dan satu kegagalan yang diketahui (`RegistrationTest::test_sapaan_dashboard_memakai_nama_lengkap_dan_gelar`). Hasil terbaru: **452 tes lulus, 0 gagal**, termasuk `RegistrationTest`.
- **Rekomendasi:** perbarui angka, tanggal, dan catatan kegagalan pada dokumen tersebut.

### F5 — Log aplikasi tidak dirotasi (Rendah)
- `storage/logs/laravel.log` berukuran ±8,6 MB dan memuat error historis (termasuk error dari sesi pengembangan sebelumnya seperti `MissingAppKeyException` dan `ParseError` saat file sedang ditulis).
- **Dampak:** disk tumbuh tanpa batas dan investigasi error menjadi sulit.
- **Rekomendasi:** gunakan channel `daily` dengan retensi, atau jadwalkan pemangkasan log.

### F6 — Konfigurasi pengembangan belum aman untuk produksi (Rendah, pengingat)
- `.env` saat ini `APP_ENV=local`, `APP_DEBUG=true`, database MySQL lokal, serta akun demo dengan password mudah ditebak (lihat `docs/KNOWN_ISSUES.md` #25).
- **Rekomendasi:** sebelum go-live: `APP_ENV=production`, `APP_DEBUG=false`, ganti/hapus akun demo, aktifkan HTTPS, jalankan worker queue + scheduler.

## 5. Yang belum diverifikasi

- **E2E browser penuh (Playwright)** tidak dijalankan ulang; skrip tersedia di `tmp/e2e/` dan membutuhkan server + database MySQL aktif. Test suite fitur sudah mencakup alur utama.
- **Uji beban/konkurensi** dan pengujian cetak fisik label/QR tidak dijalankan (butuh perangkat dan media nyata).
- **Analisis statis (PHPStan/Larastan)** tidak tersedia — paket tidak ada di `composer.json`, sehingga pemeriksaan tipe statis tidak dilakukan.
- **Build Docker** tidak diuji (butuh host dengan Docker).
- **PDF dataset besar** tidak diuji otomatis, sehingga F1 baru terbukti dari log runtime.

## 6. Rekomendasi prioritas

1. Tangani F1 (memori DomPDF) sebelum laporan dipakai pada data penuh sekolah.
2. Bersihkan sisa kode Breeze (F2) agar tidak menjadi jebakan saat fitur auth diubah.
3. Rapikan artefak repositori (F3).
4. Perbarui `docs/TESTING.md` dan `docs/KNOWN_ISSUES.md` (F4, F1).
5. Jalankan E2E Playwright dan uji beban di staging sebelum go-live.

## 7. Lampiran perintah

```bash
php artisan test --debug
vendor/bin/pint --test --format agent
php -l (seluruh file di app config database routes tests)
php artisan view:cache
php artisan route:list --except-vendor
npm run build
DB_CONNECTION=sqlite DB_DATABASE=':memory:' php artisan migrate:fresh --seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan optimize:clear
composer validate --no-check-publish
php artisan schedule:list
php artisan migrate:status
php artisan test --compact tests/Feature/ReportImportAndArchiveTest.php
vendor/bin/pint --dirty --format agent
```

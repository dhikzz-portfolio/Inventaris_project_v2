# Testing Report

Tanggal eksekusi terakhir: **20 Oktober 2026**
Lingkungan: Windows 11, PHP 8.3.32, Laravel 13, MySQL 8.4.3, Node 24, Chrome headless.

## 1. Perintah

```bash
# Seluruh test (SQLite in-memory, queue sync)
php artisan test

# Format ringkas
php artisan test --compact

# Build frontend
npm run build

# Cek style
vendor/bin/pint --test

# Cache produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 2. Hasil otomatis

```
Tests:    91 passed (407 assertions)
Duration: 11.95s
```

Tidak ada test yang di-skip. Dua test `AdminPagesAccessibilityTest` yang sebelumnya di-skip karena mengandalkan seeder sekarang menjalankan `$this->seed()` sendiri sehingga selalu dieksekusi, dan path-nya disesuaikan dengan route sesungguhnya (`/pengguna`, `/pengaturan`, `/pengadaan`, `/inventaris-keluar/persetujuan`).

### Cakupan utama

| Area                              | Test                                                                                                    |
| --------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Autentikasi & rate limit          | `Auth/*`, `PwaAndSecurityTest`                                                                          |
| Role & otorisasi                  | `AdminPagesAccessibilityTest`, `MasterDataTest`, `CriticalBusinessRulesTest` (R8), `SecurityAccessTest` |
| Katalog & performa                | `InventoryCatalogTest` (unique ID/register, pencarian < 0,5 dtk)                                        |
| Peminjaman                        | `LoanFeatureTest`, `InventoryFlowTest`, `LoanLifecycleTest`                                             |
| Anti-bentrok                      | R4, `test_anti_double_booking_pada_stok_terbatas`                                                       |
| Approval berjenjang + TTD         | R6, `test_approval_berjenjang_dengan_ttd_digital_level_2`                                               |
| Serah terima & pembatalan         | `LoanLifecycleTest`                                                                                     |
| Pengembalian parsial/rusak/hilang | R1, R2, R3, `ReturnFormLivewireTest`, `LoanLifecycleTest`                                               |
| Mutasi & penghapusan              | `MovementAndWriteOffTest`, R7                                                                           |
| QR, label, scan                   | `LabelAndQrTest`, R5                                                                                    |
| BAST & PDF                        | `EventAndBastTest`, `InventoryFlowTest`                                                                 |
| Laporan PDF/Excel                 | `DashboardAndReportingTest`, `SecurityAccessTest`                                                       |
| Notifikasi & job                  | `CheckOverdueLoans`, `CheckLowStock`, `CheckDueSoonLoans`, `GenerateMonthlyReport`                      |
| Audit log immutable               | R9                                                                                                      |
| File privat                       | `SecurityAccessTest`                                                                                    |

## 3. Verifikasi browser (Playwright + Chrome headless)

Alur end-to-end yang dijalankan pada aplikasi nyata (MySQL, bukan test DB):

1. Login `guru.budi` → buka **Ajukan Peminjaman**.
2. Cari "Meja Kerja" → tambah ke keranjang (Livewire).
3. Isi tujuan dan rentang tanggal → kirim → redirect ke detail pinjaman baru (status **Menunggu Persetujuan**).
4. Login `admin.sarpras` → buka pinjaman → **Setujui** (status **Disetujui**).
5. Klik **Serahkan Barang** (status **Sedang Dipinjam**, timestamp petugas tercatat).
6. Buka form pengembalian → submit semua barang → status **Selesai**.
7. Katalog menampilkan barang kembali tersedia.
8. Scan QR fallback manual `MEJA-G1-01-01` → redirect ke detail barang.

Hasil: seluruh langkah berhasil, **0 error JavaScript** setelah perbaikan Alpine/Livewire dan selector label.

Pemeriksaan 20 halaman sebagai Wakasek: dashboard, katalog, detail barang, daftar/detail pinjaman, form pinjam, kegiatan, laporan, label, scan, master, mutasi, inventaris keluar, approval, persetujuan, pengembalian, pengaturan, pengguna, notifikasi, pengadaan — semua **HTTP 200**.

Pemeriksaan mobile 390×844: bottom navigation tampil, pencarian dan keranjang Livewire berfungsi.

## 4. Pengukuran performa

Diukur dengan Chrome headless pada server lokal `php artisan serve`, setelah cache view panas:

| Halaman/aksi                      | Hasil                    |
| --------------------------------- | ------------------------ |
| Dashboard (warm)                  | TTFB 264 ms; load 335 ms |
| Dashboard (cold pertama)          | load 689 ms              |
| Katalog barang                    | TTFB 265 ms; load 317 ms |
| Daftar pinjaman                   | load 303 ms              |
| Detail pinjaman                   | load 302 ms              |
| Laporan                           | load 309 ms              |
| Label                             | load 291 ms              |
| Pencarian Livewire (request POST) | **234 ms**               |

Target PRD: dashboard/katalog < 1 detik dan pencarian < 0,5 detik. Pada lingkungan uji di atas target terpenuhi. Angka ini **bukan jaminan** untuk perangkat klien atau jaringan sekolah; ukur ulang di server produksi.

Optimasi yang dilakukan:

- Agregat `withSum` pada katalog untuk menghilangkan N+1 stok.
- Index `(status,start_date,due_date)` pada `loans`, index pencarian `items`.
- Pagination katalog 12 item.
- Search debounce 400 ms di katalog dan 300 ms di form pinjam.
- Test performa melakukan warm-up kompilasi Blade sebelum mengukur, sehingga yang diukur adalah request pencarian seperti dialami pengguna (bukan cold compile).

## 5. Pemeriksaan ekspor & dokumen

- PDF label satuan/massal: `Content-Type: application/pdf`, file dihasilkan.
- BAST peminjaman: PDF dibuat ulang saat diunduh dan memuat data aktual.
- Laporan PDF: 7.002 byte untuk data demo.
- Laporan Excel: 13.095 byte, berisi **5 sheet** (Barang, Peminjaman, Kerusakan, Mutasi Lokasi, Penghapusan) — diverifikasi dengan `unzip -l`.
- File tersimpan di `storage/app/private` dan diakses lewat route ber-otorisasi.

## 6. Perubahan pengujian yang disengaja

- Test pengembalian rusak tanpa foto sekarang mengharapkan `ValidationException` (bukan `HttpException` 422) karena service mengembalikan error validasi yang dapat ditampilkan di form Livewire. Assertion tetap memastikan proses gagal dan error `rows` muncul.
- Test penyimpanan file memakai `Storage::fake('local')` karena file internal dipindah dari disk publik ke privat.
- Test performa katalog menambahkan warm-up sebelum pengukuran (lihat bagian 4).

Tidak ada test yang dihapus, di-skip, atau dilemahkan untuk membuat hasil lulus.

## 7. Keterbatasan pengujian

- Test otomatis memakai SQLite in-memory; perilaku locking MySQL diuji terbatas pada alur nyata, bukan stress test konkurensi tinggi.
- Kamera QR tidak dapat diuji headless; fallback input manual diuji. Izin kamera browser memerlukan HTTPS/localhost.
- Queue worker dan scheduler tidak dijalankan sebagai service persisten; job diuji langsung dan melalui `queue:work --stop-when-empty`.
- Deployment server nyata tidak tersedia.
- Belum ada load test (k6/JMeter) untuk ratusan pengguna simultan.

## 8. Verifikasi operasional

| Perintah                                                      | Hasil                                                                                    |
| ------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `php artisan config:cache`                                    | Berhasil                                                                                 |
| `php artisan route:cache`                                     | Berhasil (route closure dihapus, PWA memakai controller)                                 |
| `php artisan view:cache`                                      | Berhasil                                                                                 |
| `php artisan schedule:list`                                   | 4 job terjadwal: overdue 07:00, due soon 07:30, low stock 07:15, laporan tanggal 1 01:00 |
| Dispatch `CheckDueSoonLoans` + `queue:work --stop-when-empty` | Job DONE 71,73 ms, `failed_jobs=0`                                                       |

> Catatan: jangan menjalankan `php artisan test` saat config sedang di-cache, karena `phpunit.xml` tidak dapat menimpa konfigurasi production. Jalankan `php artisan optimize:clear` terlebih dahulu.

### Pengaman wajib di `tests/TestCase.php`

`tests/TestCase.php` sekarang **menolak menjalankan test** kecuali koneksi database benar-benar SQLite in-memory (`sqlite` + `:memory:`) dan bukan database MySQL produksi/lokal. Pengaman ini dibuat setelah kejadian nyata: saat config ter-cache, `phpunit.xml` tidak berlaku sehingga test mengosongkan data MySQL lokal (efek `migrate:fresh`/truncate). Tanpa pengaman ini, kegagalan konfigurasi bisa menghapus data pengguna.

Jika muncul pesan `TEST ABORTED: ... SQLite in-memory ...`, jalankan `php artisan optimize:clear` lalu ulangi perintah test.

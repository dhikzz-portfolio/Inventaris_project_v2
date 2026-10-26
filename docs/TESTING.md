# Testing Report

Tanggal eksekusi terakhir: **26 Oktober 2026**
Lingkungan: Windows 11, PHP 8.3.32, Laravel 13, MySQL 8.4.3, Node 24, Chrome headless.

> Hasil ini mencakup revisi besar V2 (26 Oktober 2026): registrasi Gmail + role Peminjam, scan universal, gedung A–G, penghapusan `asset_code`, pengadaan multi-lokasi, mutasi multi-unit, impor/hapus arsip laporan, status kegiatan, surat peminjaman per kategori + pratinjau cetak, dan UI pengguna berbasis kartu. Rincian: `REVISION_V2.md`.

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
Tests:    246 passed (1171 assertions)
Duration: ~178s
```

Tidak ada test yang di-skip. Angka ini mencakup seluruh revisi sebelumnya ditambah revisi besar V2: registrasi Gmail, scan universal, gedung A–G, pengadaan multi-lokasi, mutasi multi-unit, impor/hapus laporan, status kegiatan, surat peminjaman, dan UI pengguna.

Test baru pada revisi V2:

- `RegistrationTest` (8 tes) — registrasi lengkap, Gmail ditolak, domain kapital dinormalisasi, duplikat, hash password, role selalu Peminjam, eskalasi role diabaikan, kelas wajib siswa, sapaan dashboard, profil tidak dapat mengubah role.
- `UniversalScanTest` — semua role dapat scan, hasil tampil di halaman scan, kode invalid/missing, masking data internal, scan tidak mengubah data, payload QR memuat rilis skema + ID unit + kode + metadata lokasi tanpa rahasia, serta stiker ID internal katalog lama diarahkan tanpa redirect.
- `MultiLocationProcurementTest` — alokasi 20+15+5, total lebih/kurang ditolak, unit dan kode unik per lokasi.
- `ReportImportAndArchiveTest` — pratinjau, format/duplikat ditolak, impor atomik, hapus arsip tidak menghapus unit, audit log.
- `EventActivityTest` — guru/staf mencatat kegiatan, siswa ditolak, status tersimpan & berubah, kepemilikan, tidak mengubah peminjaman.
- `LoanLetterTest` — surat biasa tanpa kegiatan, surat kegiatan memuat kegiatan, tanggal tanpa jam, identitas per kategori, kode unit, otorisasi.
- `UserManagementUiTest` — panel kartu tambah/edit, field kondisional, role hanya Super Admin.

Test lama yang diperbarui: `BuildingMasterDataTest`, `InventoryCatalogTest`, `MovementAndWriteOffTest`, `LabelAndQrTest` (penghapusan `asset_code` dan perilaku baru).

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
| Penghapusan mengarsip unit fisik  | `MovementAndWriteOffTest::test_persetujuan_penghapusan_mengarsipkan_unit_fisik_dan_sinkron_dengan_katalog` |
| Cakupan mutasi/penghapusan        | `MovementAndWriteOffTest::test_ketua_laboran_tidak_dapat_mengajukan_penghapusan_barang_di_luar_laboratorium` |
| Finansial (selisih/buku)         | `ItemQuantityConsistencyTest` (query selisih, selisih sementara tanpa stok ganda)                         |
| QR, label, scan                   | `LabelAndQrTest`, R5                                                                                    |
| Label massal per unit & cakupan   | `LabelAndQrTest::test_cetak_label_massal_per_unit_menghasilkan_pdf`, `test_label_massal_tidak_mencetak_unit_di_luar_cakupan_laboran`, `test_cetak_massal_dengan_select_all_hanya_mencetak_unit_sesuai_filter_dan_cakupan` |
| Ringkasan laporan per unit fisik  | `ReportTest::test_ringkasan_laporan_menghitung_unit_fisik_bukan_baris_katalog`                          |
| BAST & PDF                        | `EventAndBastTest`, `InventoryFlowTest`                                                                 |
| Laporan PDF/Excel                 | `DashboardAndReportingTest`, `SecurityAccessTest`                                                       |
| Cakupan laporan Ketua Laboran     | `ReportTest::test_ketua_laboran_hanya_mendapat_data_laporan_laboratorium`                               |
| Persetujuan otomatis Laboran/K3   | `LaboranApprovalTest::test_laboran_mendapat_persetujuan_otomatis_untuk_barang_biasa_di_lab_sendiri`      |
| Barang nilai tinggi + auto level 1| `LaboranApprovalTest::test_barang_nilai_tinggi_di_lab_sendiri_tetap_menunggu_persetujuan_wakasek`        |
| Lintas lingkup tidak auto-approve | `LaboranApprovalTest::test_pengajuan_lintas_lingkup_tidak_disetujui_otomatis`                           |
| Katalog duplikat ditolak          | `LaboranApprovalTest::test_katalog_yang_sama_tidak_dapat_diajukan_dua_kali`                             |
| Hapus/arsip katalog               | `CatalogDeletionTest` (5 test)                                                                           |
| Batas & pengulangan serah terima   | `LoanUnitFlowTest::test_serah_terima_menolak_unit_melebihi_jumlah_yang_dipinjam`, `test_request_serah_terima_berulang_tidak_membuat_penugasan_ganda` |
| Arsip laporan seluruh sekolah     | `ReportTest::test_arsip_laporan_seluruh_sekolah_hanya_dapat_diakses_super_admin`                        |
| Privasi data unit untuk Peminjam  | `SecurityAccessTest::test_peminjam_tidak_melihat_nilai_perolehan_catatan_dan_riwayat_mutasi_unit`       |
| Dashboard sesuai cakupan role     | `DashboardAndReportingTest::test_dashboard_pengelola_menghitung_unit_sesuai_cakupan_akses`              |
| Menu Laboran tanpa Mutasi         | `DashboardAndReportingTest::test_menu_laboran_tidak_menautkan_mutasi_yang_bukan_kewenangannya`          |
| Katalog legacy & pengadaan lab    | `SecurityAccessTest::test_katalog_di_luar_cakupan_tidak_dapat_dibuka_lewat_url_legacy`, `test_ketua_laboran_tidak_dapat_mengajukan_pengadaan_ke_ruang_non_lab` |
| Katalog & kode inventaris 2.1     | `InventoryCoreTest` (AC-01…AC-11, kapasitas 99, `inventory:verify-codes`)                               |
| Ubah kondisi/status unit          | `InventoryCoreTest::test_laboran_dapat_mengubah_kondisi_unit_dalam_cakupan_dan_tercatat_audit`, `test_status_unit_hanya_dapat_diubah_pengelola`, `test_laboran_tidak_dapat_mengubah_unit_di_luar_cakupan` |
| Notifikasi & job                  | `CheckOverdueLoans`, `CheckDueSoonLoans`, `GenerateMonthlyReport`                                       |
| Audit log immutable               | R9                                                                                                      |
| File privat                       | `SecurityAccessTest`                                                                                    |

## 3. Verifikasi browser (Playwright + Chrome headless)

Alur end-to-end yang dijalankan pada aplikasi nyata (MySQL, bukan test DB):

1. Login `guru.budi` → buka **Ajukan Peminjaman**.
2. Cari "Meja Kerja" → tambah ke keranjang (Livewire).
3. Isi tujuan dan rentang tanggal → kirim → redirect ke detail pinjaman baru (status **Menunggu Persetujuan**).
4. Login `ketua.laboran` → buka pinjaman → **Setujui** (status **Disetujui**).
5. Klik **Serahkan Barang** (status **Sedang Dipinjam**, timestamp petugas tercatat).
6. Buka form pengembalian → submit semua barang → status **Selesai**.
7. Katalog menampilkan barang kembali tersedia.
8. Scan QR fallback manual `g1-01-01` (format kode lama; setelah revisi 2.1 memakai kode per unit `G1-02-12`) → redirect ke detail barang.

Hasil: seluruh langkah berhasil, **0 error JavaScript** setelah perbaikan Alpine/Livewire dan selector label.

Verifikasi katalog/unit revisi 2.1 (22 Oktober 2026, server lokal + Chrome headless):

1. Login `wakasek` → `/catalogs` menampilkan kartu katalog; tautan detail berfungsi.
2. Detail katalog menampilkan total unit; kode unit berformat `G1-02-12`.
3. Pencarian kode exact menyisakan satu unit; input tidak valid menampilkan pesan format dan empty state.
4. Filter gedung hanya menampilkan unit gedung tersebut; menghapus filter mengembalikan daftar.
5. Detail unit menampilkan identitas, lokasi, riwayat kode/pergerakan, dan tombol cetak label.
6. Login `laboran.tp`: unit laboratorium di luar cakupannya melalui URL langsung menghasilkan **404** (bukan 403); katalog tak dikenal juga 404.
7. Viewport 390×844: bottom navigation tampil.
8. Login `ketua.laboran` (Ketua Laboran) → `/laporan` menampilkan cakupan **"Seluruh laboratorium"**; ekspor PDF merespons `200 application/pdf`.

Hasil: **PASS**, tanpa error JavaScript dan tanpa request gagal pada halaman katalog/unit. Pemeriksaan isi PDF bahwa barang non-laboratorium tidak ikut diekspor divalidasi di level data melalui `ReportTest` (bukan ekstraksi teks PDF).

### 3.1 Audit UI/UX & visual regression (22 Oktober 2026)

- Desain lama (sidebar slate gelap, aksen amber, kartu `rounded-xl`, toolbar filter, tabel detail) ditemukan di source Git HEAD dan dipertahankan pada halaman katalog/unit baru; tidak ada file mockup/screenshot desain di repository.
- Tailwind CSS ditemukan memakai v4 tetapi stylesheet masih memakai direktif v3, sehingga utility tidak dihasilkan dan halaman tampil tanpa style. Setelah perbaikan, build CSS berisi utility lengkap (±78 KB) dan seluruh halaman mengikuti design system lama.
- 19 halaman utama (dashboard, katalog, detail unit, peminjaman, kegiatan, laporan, label, scan, master, mutasi, inventaris keluar, persetujuan, pengembalian, pengguna, pengaturan, pengadaan, notifikasi, profil) merespons **200**, sidebar dan heading memakai style yang benar, tanpa error JavaScript.
- Mobile 390×844 pada dashboard, katalog, peminjaman, form pinjam, dan pengguna: **tidak ada overflow horizontal** (0 px) dan bottom navigation tampil.
- Tema gelap: class `dark` aktif, latar body `#0b1220`, kartu lebih terang, placeholder foto tidak lagi memakai gradient terang.
- Dashboard 4 role diperiksa: Super Admin 42 katalog/125 unit, Ketua Laboran 30/75 (tanpa ruang umum), Laboran TP 6/16 (2 ruang), Peminjam mode personal — angka berasal dari database dan scope backend.
- Detail unit diperiksa per role: Laboran hanya melihat form kondisi + catatan (tanpa pilihan status); Ketua Laboran melihat kondisi + status. Perubahan disimpan lewat `PATCH /units/{unit}` dan dicatat di audit log.

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
- Laporan Excel: berisi **5 sheet** (Unit, Peminjaman, Kerusakan, Mutasi Lokasi, Penghapusan) — sheet Unit satu baris per unit fisik (kode inventaris, katalog, lokasi, kondisi, status).
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

| Perintah                                                      | Hasil                                                                   |
| ------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `php artisan config:cache`                                    | Berhasil                                                                |
| `php artisan route:cache`                                     | Berhasil (route closure dihapus, PWA memakai controller)                |
| `php artisan view:cache`                                      | Berhasil                                                                |
| `php artisan schedule:list`                                   | 3 job terjadwal: overdue 07:00, due soon 07:30, laporan tanggal 1 01:00 |
| Dispatch `CheckDueSoonLoans` + `queue:work --stop-when-empty` | Job DONE 71,73 ms, `failed_jobs=0`                                      |

> Catatan: jangan menjalankan `php artisan test` saat config sedang di-cache, karena `phpunit.xml` tidak dapat menimpa konfigurasi production. Jalankan `php artisan optimize:clear` terlebih dahulu.

### Pengaman wajib di `tests/TestCase.php`

`tests/TestCase.php` sekarang **menolak menjalankan test** kecuali koneksi database benar-benar SQLite in-memory (`sqlite` + `:memory:`) dan bukan database MySQL produksi/lokal. Pengaman ini dibuat setelah kejadian nyata: saat config ter-cache, `phpunit.xml` tidak berlaku sehingga test mengosongkan data MySQL lokal (efek `migrate:fresh`/truncate). Tanpa pengaman ini, kegagalan konfigurasi bisa menghapus data pengguna.

Jika muncul pesan `TEST ABORTED: ... SQLite in-memory ...`, jalankan `php artisan optimize:clear` lalu ulangi perintah test.

---

## 9. Pengujian revisi 2.1 (SUDAH DIEKSEKUSI)

> Untuk revisi besar V2 (26 Oktober 2026), lihat bagian 10 di bawah.

Rencana disusun: 21 Oktober 2026. Dieksekusi: **22 Oktober 2026**.

> Acceptance criteria 2.1 diuji di `tests/Feature/InventoryCoreTest.php` (SQLite in-memory) dan diverifikasi di browser. Seluruh test lulus; lihat bagian 2 untuk hasil lengkap. Bagian 1–8 di atas memuat hasil baseline model batch dan sudah diperbarui pada bagian 2.

### 9.1 Test otomatis yang harus dibuat

| ID    | Skenario                                                                                                    | Jenis test           | File yang disarankan            |
| ----- | ----------------------------------------------------------------------------------------------------------- | -------------------- | ------------------------------- || AC-01 | 23 unit katalog sama → satu katalog, `jumlah_unit = 23`                                                     | Feature              | `InventoryCoreTest` → LULUS     |
| AC-02 | Setiap unit punya baris dan kode sendiri; `unique(inventory_code)`                                          | Feature              | `InventoryCoreTest` → LULUS     |
| AC-03 | `G1-02-12` diparse menjadi Gedung G/lantai 1/ruang 02/unit 12; format salah ditolak                         | Unit                 | `InventoryCoreTest` → LULUS     |
| AC-04 | Dua unit tidak boleh memakai kode yang sama (termasuk percobaan bersamaan)                                  | Feature + konkurensi | `InventoryCoreTest` → LULUS     |
| AC-05 | Kursi dan meja di ruangan sama mendapat nomor berbeda                                                       | Feature              | `InventoryCoreTest` → LULUS     |
| AC-06 | Detail katalog Kursi hanya berisi unit kursi                                                                | Feature (Livewire)   | `InventoryCoreTest` → LULUS     |
| AC-07 | Pencarian `F1-03-15` pada detail Kursi mengembalikan unit yang tepat                                        | Feature (Livewire)   | `InventoryCoreTest` → LULUS     |
| AC-08 | Filter gedung/lantai/ruangan memakai lokasi aktif unit                                                      | Feature (Livewire)   | `InventoryCoreTest` → LULUS     |
| AC-09 | Jumlah katalog = hitungan unit; unit `DIHAPUS` tidak dihitung; unit di luar scope tidak dihitung            | Feature              | `InventoryCoreTest` → LULUS     |
| AC-10 | Role Laboran/K3 tidak dapat membaca unit lab lain lewat daftar, kode, URL, ekspor, atau QR (404)            | Feature (security)   | `InventoryCoreTest` + `SecurityAccessTest` → LULUS |
| AC-11 | Mutasi menutup kode lama, menerbitkan kode baru, mencatat movement, dan kode lama resolve ke unit yang sama | Feature              | `InventoryCoreTest` → LULUS     |
| AC-12 | Dokumen/ERD/AC konsisten (pemeriksaan statis pada `docs/`)                                                   | Pemeriksaan dokumen  | manual → LULUS (dokumen diselaraskan 22 Oktober 2026) |

### 9.2 Skenario pinggir yang wajib diuji

- Format: `g1-02-12` diterima setelah normalisasi; `G1/02/12`, `G1-2-12`, `G1-02-120` ditolak.
- Kode tidak cocok lokasi (mis. unit di `F1-03` diberi kode `G1-03-05`) ditolak.
- Ruangan berisi 99 nomor → pembuatan unit ke-100 ditolak `RUANGAN_PENUH`; nomor yang sudah pensiun tetap dihitung.
- Mutasi unit yang sedang `DIPINJAM` ditolak.
- Unit pindah lalu kembali ke ruangan asal mendapat nomor baru, bukan kode lama.
- Scan kode pensiun menampilkan unit aktif + keterangan kode lama; kode tak dikenal → pesan tidak ditemukan.
- Pencarian kode di luar cakupan menghasilkan pesan kosong yang sama dengan kode tidak ditemukan (tidak membocorkan data).
- Pagination dan filter tetap konsisten setelah pindah halaman (query string dipertahankan).
- Master gedung: kode huruf/satu karakter, duplikat ditolak; gedung dengan unit/fisik tidak boleh diubah kodenya diam-diam (harus mutasi dulu); gedung/ruangan yang dipakai tidak boleh dihapus.
- Formulir ruangan tidak meminta nama gedung/manual kode; kode lokasi & kode unit diturunkan dari master gedung.
- Serah terima dua kali ditolak dengan pesan jelaskan bukan exception.
- Persetujuan Wakasek memenuhi seluruh level tanpa menunggu Admin tambahan; sebaliknya, pengguna biasa tidak bisa memanfaatkan jalur khusus Wakasek.

### 9.3 Perintah yang dijalankan setelah implementasi

```bash
php artisan test --compact --filter=InventoryCodeTest
php artisan test --compact --filter=CatalogUnitGroupingTest
php artisan test --compact --filter=CatalogDetailTest
php artisan test --compact --filter=CatalogSearchFilterTest
php artisan test --compact --filter=CatalogCountTest
php artisan test --compact --filter=UnitAccessScopeTest
php artisan test --compact --filter=UnitMovementCodeTest
php artisan test --compact --filter=BuildingMasterDataTest
php artisan test --compact --filter=LoanApprovalHandoverTest
php artisan test --compact --filter=ItemQuantityConsistencyTest
php artisan test --compact --filter=LoanUnitFlowTest
php artisan inventory:verify-codes
vendor/bin/pint --dirty --format agent
```

### 9.4 Kriteria bukti selesai

1. Seluruh test AC-01…AC-11 (revisi 2.1) lulus di SQLite in-memory. Pengujian revisi lanjutan 22 Oktober: `BuildingMasterDataTest` (12 tes), `LoanApprovalHandoverTest` (18 tes), `ItemQuantityConsistencyTest` (2 tes), plus `LoanUnitFlowTest` dan `InventoryCoreTest` yang diperbarui semuanya lulus. Total saat eksekusi: **183 tes, 887 asersi**.
2. Pemeriksaan browser: katalog → detail → pencarian kode → filter lokasi → detail unit → pembatasan role → mobile, tanpa error JavaScript (PASS).
3. Rekonsiliasi pasca-migrasi 0 selisih (`inventory:verify-codes` diuji pada data seeder dan ledger rusak).
4. Hasil pengukuran pencarian < 0,5 detik pada data uji minimal 5.000 unit: **belum diukur ulang pada 5.000 unit**; pengukuran sebelumnya pada data demo memenuhi target.
5. `PROJECT_STATUS.md` diperbarui menjadi VERIFIED setelah butir 1–3 terpenuhi; butir 4 dicatat sebagai keterbatasan.

## 10. Pengujian revisi besar V2 (26 Oktober 2026)

Dijalankan dengan `php artisan test --compact` pada SQLite in-memory, locale `id`, timezone `Asia/Jakarta`.

| Kelompok | Test | Bukti |
| --- | --- | --- |
| A. Autentikasi | `RegistrationTest` | registrasi lengkap, Gmail ditolak, domain kapital diterima, duplikat, hash, role Peminjam, eskalasi role, sapaan, profil |
| B. Scan | `UniversalScanTest` | akses semua role, hasil di halaman scan, invalid/missing, masking internal, tidak mengubah data, kompatibilitas stiker katalog lama |
| C. Master data | `BuildingMasterDataTest`, `InventoryCatalogTest` | validasi wajib, gedung A–G, seeder idempoten, kode lokasi/unit |
| D. Pengadaan | `MultiLocationProcurementTest` | 20+15+5 valid, total lebih/kurang ditolak, kode otomatis & unik |
| E. Label & QR | `LabelAndQrTest` | label per unit, header nama, kode unit, QR unit, tanpa `asset_code` |
| F. Mutasi | `MovementAndWriteOffTest` | unit dipinjam ditolak, cakupan, riwayat; multi-select divalidasi backend |
| G. Laporan | `ReportImportAndArchiveTest` | pratinjau, format/duplikat, atomik, hapus arsip, audit |
| H. Dokumen | `LoanLetterTest`, `EventAndBastTest` | surat biasa vs kegiatan, tanggal tanpa jam, identitas, kode unit, otorisasi |
| I. Regresi role | seluruh suite | 246 tes lulus untuk Super Admin, Ketua Laboran, Laboran, siswa, guru, staf |

Batasan yang tidak diuji: kamera fisik pada perangkat nyata (diuji melalui jalur manual dan ketersediaan `html5-qrcode`), verifikasi email (SMTP belum dikonfigurasi), dan konkurensi MySQL nyata.

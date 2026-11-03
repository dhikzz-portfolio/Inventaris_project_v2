# Project Status

Tanggal: **3 November 2026**
Versi: v2 + revisi **2.1**, **revisi lanjutan 22 Oktober 2026**, **revisi besar 24 Oktober 2026**, **revisi besar V2 26 Oktober 2026**, **revisi validasi pengembalian per unit 28 Oktober 2026**, **revisi serah terima/validasi/rincian barang 29 Oktober 2026**, **revisi V3 2 November 2026** (sinkronisasi label/QR ke spesifikasi unit, pengadaan dari katalog lama, field pengguna per kategori, password dua tahap, PIC kegiatan, hapus kegiatan terbatas, ringkasan peminjaman adaptif), **revisi V4 2 November 2026** (laporan penghapusan lintas katalog, anti-duplikasi unit, sinkronisasi stok, backfill CSV, invalidasi sesi, otorisasi Super Admin, QR aman, konkurensi MySQL, Playwright E2E, responsif 8 breakpoint), dan **revisi V5 2 November 2026** (alur pengadaan tiga tahap Laboran → Ketua Laboran → Wakasek, penerimaan barang sebelum inventaris, laporan bulanan ber-cakupan role + pengadaan/kerusakan, pelaporan kerusakan rusak ringan/berat, Master Data Ketua Laboran read-only, role Super Admin Pengguna). Rincian: `REVISION_V2.md`, `REVISION_V3.md`, `DECISIONS.md` butir 38–42, `PENDING_DECISIONS.md`, dan `TESTING.md` bagian 12–16.

Revisi V6 (3 November 2026): mutasi multi-lokasi dalam satu transaksi (`asset_unit_movements.movement_no`), pencarian nama/spesifikasi pada cetak label, notifikasi berbasis role/cakupan dengan penjaga tautan, serta notifikasi laporan kerusakan dan administrasi akun. Rincian: `DECISIONS.md` butir 40–42 dan `TESTING.md` bagian "Revisi V6".

Revisi V7 (3 November 2026): peminjaman atas nama diri sendiri/orang lain dengan pemisahan `requested_by` dan identitas peminjam, rincian unit konsisten pada PDF/Excel dan pratinjau Excel, contoh file + panduan impor, galeri multi-foto kerusakan pada detail unit, serta administrasi akun yang hanya dapat diakses Super Admin Pengguna beserta bootstrap akun awal yang aman. Rincian: `REVISION_V7.md` dan `TESTING.md` bagian "Revisi V7".

> **Status keputusan:** empat kebijakan bisnis masih **PENDING CLARIFICATION** — lihat `docs/PENDING_DECISIONS.md`. Implementasi memakai opsi paling aman dan tidak mengaktifkan alur persetujuan yang belum disepakati.
Database pengembangan: `inventaris_sekolah_v2` (MySQL 8.4)

## 1. Ringkasan status

| Aspek                                                       | Status                                                                                     |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Katalog jenis barang + unit inventaris fisik                 | **IMPLEMENTED & VERIFIED**                                                                 |
| Mesin kode inventaris `G1-02-12`                             | **IMPLEMENTED & VERIFIED**                                                                 |
| Detail katalog, pencarian kode, filter, sorting, pagination  | **IMPLEMENTED & VERIFIED**                                                                 |
| Cakupan akses katalog/unit (Super Admin, Ketua Laboran, Laboran/K3) | **IMPLEMENTED & VERIFIED** untuk katalog, unit, pencarian, dan URL langsung |
| Label per unit + mutasi unit (kode baru & riwayat)           | **IMPLEMENTED & VERIFIED**                                                                 |
| Privasi data unit untuk Peminjam (nilai, catatan, riwayat)   | **IMPLEMENTED & VERIFIED**                                                                 |
| Audit log perubahan cakupan akses ruangan                    | **IMPLEMENTED & VERIFIED**                                                                 |
| UI/UX desain lama + responsif + tema gelap                   | **IMPLEMENTED & VERIFIED** — 24 halaman × 8 breakpoint (320–1920 px), 193 pemeriksaan, 0 overflow dokumen |
| Dashboard per role (katalog, unit, cakupan lab)              | **IMPLEMENTED & VERIFIED**                                                                 |
| Penugasan unit fisik pada peminjaman (`loan_item_units`)      | **IMPLEMENTED & VERIFIED** — unit ditugaskan saat serah terima, dikonfirmasi terpisah saat kembali, status `DIPINJAM`/`AKTIF` + kondisi mengikuti pengembalian |
| Alur serah terima (tanpa error 422 palsu)                    | **IMPLEMENTED & VERIFIED** — status transaksi menutup rantai persetujuan; pesan blokir tampil di UI |
| Persetujuan Wakasek tanpa Admin tambahan                      | **IMPLEMENTED & VERIFIED** — satu persetujuan sah Wakasek cukup; alur role lain tetap berjenjang |
| Tanda tangan digital opsional + status dokumen terpisah       | **IMPLEMENTED & VERIFIED** — `DocumentStatus` terpisah dari status transaksi               |
| Master data gedung + formulir ruangan sederhana               | **IMPLEMENTED & VERIFIED** — dropdown gedung, lantai, nomor; kode lokasi dari master gedung |
| Role final: Wakasek, Ketua Laboran, Laboran/K3, Peminjam     | **IMPLEMENTED & VERIFIED** — `ADMIN_SARPRAS` dimigrasikan ke `KETUA_LABORAN`              |
| Seeder lima jurusan + akun demo semua role                   | **IMPLEMENTED & VERIFIED** — RPL, DKV, KLN, TP, TKP; 5 Laboran; siswa/guru/staf            |
| Persetujuan otomatis Laboran/K3 dalam lingkup sendiri        | **IMPLEMENTED & VERIFIED** — nilai tinggi & lintas lingkup dikecualikan                     |
| Katalog terintegrasi di halaman peminjam                     | **IMPLEMENTED & VERIFIED** — pencarian/filter + pencegahan duplikat                         |
| Hapus/arsip katalog yang aman                                | **IMPLEMENTED & VERIFIED** — hapus permanen tanpa relasi, arsip bila ada unit/transaksi     |
| Batas unit pada serah terima (UI + backend)                  | **IMPLEMENTED & VERIFIED** — unit berlebih disabled/ditolak, request berulang ditolak       |
| Pilih semua + pencarian/filter serah terima                  | **IMPLEMENTED & VERIFIED** — pilih semua berhenti pada kuota per baris, filter tidak menghapus pilihan |
| Scan QR kontekstual serah terima                             | **IMPLEMENTED & VERIFIED** — endpoint server memverifikasi unit/transaksi/kelayakan/duplikasi/kuota; scan tidak menyerahkan |
| Pencarian, filter, dan scan QR validasi pengembalian         | **IMPLEMENTED & VERIFIED** — QR hanya menyorot & memilih unit, konfirmasi tetap aksi petugas |
| Rincian barang per unit + popup foto bukti                   | **IMPLEMENTED & VERIFIED** — kartu per unit, filter lengkap, pagination 24, foto terikat unit dan diakses sesuai peran |
| Penghapusan per unit + katalog                               | **IMPLEMENTED & VERIFIED** — `write_off_units`, snapshot identitas, locking, unique aktif per unit, konfirmasi katalog |
| Cakupan laporan/ekspor untuk Ketua Laboran                   | **IMPLEMENTED & VERIFIED** — data, PDF, Excel, dan arsip dibatasi laboratorium             |
| Audit sinkronisasi fitur vs PRD 2.1                          | **IMPLEMENTED & VERIFIED** — matriks di `feature-sync-audit.md`; label massal per unit, penghapusan mengarsip unit, laporan per unit |
| Registrasi mandiri Gmail + role Peminjam otomatis             | **IMPLEMENTED & VERIFIED** — validasi backend + `pattern` frontend, email dinormalisasi, role tidak dapat dikirim dari browser |
| Welcome screen nama lengkap/gelar                            | **IMPLEMENTED & VERIFIED** — `displayName()` (username tidak digunakan)                                                          |
| Scan universal + hasil langsung di halaman scan               | **IMPLEMENTED & VERIFIED** — kamera `html5-qrcode`, input manual, masking data internal, semua role login                   |
| Master gedung A–G                                            | **IMPLEMENTED & VERIFIED** — seeder idempoten; H/I dipertahankan untuk data demo                                          |
| Kode aset terpisah dihapus                                   | **IMPLEMENTED & VERIFIED** — migration `asset_code`, seluruh tampilan/label/QR/laporan memakai kode unit                     |
| Pengadaan multi-lokasi                                       | **IMPLEMENTED & VERIFIED** — alokasi per ruangan, total wajib sama, unit otomatis per lokasi                               |
| Mutasi multi-unit + pencarian/filter                         | **IMPLEMENTED & VERIFIED** — `movements.index/store`, atomik, unit dipinjam ditolak                                         |
| Impor laporan CSV/XLSX + hapus arsip aman                    | **IMPLEMENTED & VERIFIED** — pratinjau, token, atomik, soft delete arsip; bukan pengganti backup database                   |
| Status kegiatan Belum/Sedang/Sudah Berjalan                  | **IMPLEMENTED & VERIFIED** — tersimpan di `events.status`; guru/staf dapat mencatat                                         |
| Surat peminjaman per kategori + pratinjau cetak              | **IMPLEMENTED & VERIFIED** — bagian kegiatan kondisional, tanggal tanpa jam, kode unit, `@media print`                      |
| UI pengguna card tambah/edit                                 | **IMPLEMENTED & VERIFIED** — form bersama `_form.blade.php`                                                                |
| Label/QR memakai identitas spesifikasi unit                  | **IMPLEMENTED & VERIFIED** — `RevisionV3Test`; katalog hanya fallback unit lama; QR rilis 2 |
| Pengadaan dari katalog lama + validasi kategori              | **IMPLEMENTED & VERIFIED** — nama/kategori terkunci, kategori berbeda ditolak, tidak menduplikasi katalog |
| Field pengguna per kategori + password dua tahap             | **IMPLEMENTED & VERIFIED** — gelar siswa & jurusan guru/staf ditolak backend; verifikasi password lama |
| PIC kegiatan akun atau nama khusus + hapus terbatas          | **IMPLEMENTED & VERIFIED** — `events.pic_user_id`; hanya pembuat; kegiatan dengan peminjaman ditolak |
| Ringkasan peminjaman adaptif                                 | **IMPLEMENTED & VERIFIED** — baris opsional kosong tidak dirender |
| Penghapusan per unit + seluruh katalog                      | **IMPLEMENTED & VERIFIED** — `write_off_units`, snapshot identitas, locking, unique `(asset_unit_id, is_active)`, konfirmasi katalog |
| Laporan penghapusan lintas katalog                          | **IMPLEMENTED & VERIFIED** — PDF/Excel/ringkasan memakai `write_off_units`; transaksi legacy ditandai dan tidak dihitung dua kali |
| Filter unit pada inventaris keluar                          | **IMPLEMENTED & VERIFIED** — 10 parameter AND, pagination, pilih semua sesuai filter |
| Backfill spesifikasi unit lama                              | **IMPLEMENTED & VERIFIED** — dry-run/apply, CSV kandidat+status, idempoten; unit ambigu tidak ditebak |
| Invalidasi seluruh sesi setelah ganti password              | **IMPLEMENTED & VERIFIED** — `AuthenticateSession`; sesi aktif tetap login, hash lama ditolak |
| Otorisasi hapus kegiatan                                    | **IMPLEMENTED & VERIFIED** — pembuat atau Super Admin; PIC bukan pemilik; kegiatan dengan peminjaman selalu ditolak |
| QR rilis 1/2 + payload dimanipulasi                         | **IMPLEMENTED & VERIFIED** — versi tak dikenal ditolak, identitas/lokasi selalu dari database, mismatch diperingatkan |
| Pengujian MySQL + konkuren                                  | **VERIFIED (terbatas)** — MySQL 8.4.3, 6 pembuat paralel unik, 3 penghapusan unit sama (1 sukses/2 ditolak), 3 unit berbeda stok konsisten |
| Pengujian browser                                           | **VERIFIED** — Playwright E2E penuh pengadaan → label → scan → mutasi → pinjam → serah terima → kembali → hapus; 0 console error |
| Audit responsif                                             | **VERIFIED** — 24 halaman × 8 breakpoint + modal, 193 pemeriksaan PASS |
| Cetak fisik label/QR                                        | **BELUM DIVERIFIKASI** — PDF/QR digital valid; printer/pemindai tidak tersedia |
| Verifikasi email (SMTP)                                      | **BELUM** — kolom & event siap; SMTP/MustVerifyEmail belum dikonfigurasi                                                    |
| Deployment produksi                                          | **BLOCKED** — tidak ada akses server/domain pada lingkungan ini                            |

Pengujian suite penuh per revisi V7: **378 passed, 0 skipped, 2053 assertions** (SQLite in-memory). Riwayat sebelum revisi V5: **330 passed, 0 skipped, 1682 assertions** (SQLite in-memory, locale `id`, timezone `Asia/Jakarta`). Revisi V5 menambahkan 21 tes baru (alur pengadaan, laporan kerusakan, Super Admin Pengguna, cakupan laporan, dropdown pencarian) dan memperbarui tes lama yang terdampak; hasil suite lengkap ada pada `TESTING.md` §16. `vendor/bin/pint` dijalankan pada file yang diubah dan `npm run build` berhasil. Playwright E2E penuh dan audit responsif dijalankan pada MySQL 8.4.3 dengan database uji terpisah (`inventaris_e2e`); **cetak fisik tetap BLOCKED** dan E2E tidak diulang pada revisi V5.

## 2. Status per modul

| Modul                                   | Status      | Catatan                                                                                          |
| --------------------------------------- | ----------- | ------------------------------------------------------------------------------------------------ |
| Autentikasi & sesi                      | VERIFIED    | Login email/password, rate limit 5 percobaan, logout POST, registrasi Gmail mandiri (role Peminjam), verifikasi email belum aktif |
| Role & permission                       | VERIFIED    | 4 role final (`SUPER_ADMIN`, `KETUA_LABORAN`, `LABORAN`, `PEMINJAM`) + middleware Spatie; kategori peminjam di `borrower_type` |
| Dashboard                               | VERIFIED    | Data unit/katalog nyata, grafik Chart.js, cakupan per role (sekolah/lab/ruangan), personal vs operasional |
| Katalog jenis barang                    | VERIFIED    | Tabel `item_catalogs`; hierarki 3 tingkat (katalog → spesifikasi → unit); jumlah unit dihitung dari unit; pencarian/filter/pagination |
| Spesifikasi merek & tipe/model          | VERIFIED    | Tabel `specifications`; satu katalog banyak spesifikasi, satu spesifikasi banyak unit; nilai `Tanpa Merek`/`Tidak Ditentukan`; halaman spesifikasi + filter gabungan |
| Distribusi per spesifikasi              | VERIFIED    | `distributions` + `distribution_units`; stok per merek/tipe dihitung terpisah, batas stok & unit sekali alokasi ditegakkan |
| Unit inventaris fisik                   | VERIFIED    | Tabel `asset_units`; kondisi/status/catatan dapat diubah sesuai role dan tercatat di audit log       |
| Kode inventaris `G1-02-12`              | VERIFIED    | Validasi pola/lokasi, ledger kode, transaksi + unique index, batas 99, larangan daur ulang         |
| Detail katalog & pencarian kode         | VERIFIED    | Pencarian exact/prefix, pesan format, scope, empty state, pagination 25 unit                       |
| Filter lokasi/kondisi/status/tahun      | VERIFIED    | Filter AND; pilihan filter mengikuti data yang terlihat                                           |
| Master data                             | VERIFIED    | CRUD jurusan/gedung/ruangan/kategori; master gedung A–G (+H/I demo), validasi identitas wajib; nama ruangan opsional; identitas ruangan (`building_id`, `floor`, `room_no`, `is_lab`) |
| Scan universal                          | VERIFIED    | Kamera + manual, hasil di halaman scan, masking data internal, unit di luar cakupan dibatasi |
| Pengadaan multi-lokasi                  | VERIFIED    | Alokasi banyak ruangan, total wajib sama, unit & kode dibuat per lokasi |
| Pengguna & cakupan laboratorium         | VERIFIED    | `user_room_scopes`; perubahan cakupan dicatat di audit log dengan ruangan sebelum/sesudah          |
| Pengadaan (V5)                          | VERIFIED    | Alur tiga tahap: draft Laboran → konfirmasi Ketua Laboran → keputusan Wakasek; unit baru terbit setelah barang diterima & dicatat; penolakan wajib alasan; audit trail status sebelum/sesudah |
| Laporan kerusakan (V5)                  | VERIFIED    | `damage_reports` + `damage_report_logs`; rusak ringan/berat; cakupan per role; tidak menghapus unit/mengurangi stok; status penanganan tanpa alur persetujuan yang belum disepakati |
| Super Admin Pengguna (V5/V7)            | VERIFIED    | Satu-satunya role dengan akses `/pengguna`; menu disembunyikan dari role lain; akses operasional inventaris ditolak backend; proteksi akun Super Admin utama & akun terakhir; bootstrap aman + wajib ganti password |
| Peminjaman atas nama orang lain (V7)    | VERIFIED    | `requested_by` dan peminjam sebenarnya terpisah; snapshot identitas; Peminjam dipaksa `SELF` di backend |
| Rincian unit PDF/Excel + pratinjau (V7) | VERIFIED    | Satu sumber `MonthlyReportExport::tables()`; pratinjau pagination tidak mengubah isi file |
| Panduan & template impor (V7)           | VERIFIED    | CSV contoh sesuai kolom aktual; header salah ditolak; impor atomik dua tahap |
| Foto kerusakan pada detail unit (V7)    | VERIFIED    | Multi-foto per laporan, riwayat kronologis, akses file per cakupan laporan |
| Master Data Ketua Laboran (V5)          | VERIFIED (PENDING) | Read-only: lihat `/master` diperbolehkan, semua mutasi khusus Super Admin. Daftar menu final menunggu keputusan |
| Peminjaman multi-item                   | VERIFIED    | Multi-item; ketersediaan katalog dikurangi pinjaman aktif; status transaksi menutup rantai persetujuan (PENDING → APPROVED) |
| Serah terima & pembatalan               | VERIFIED    | Timestamp petugas, pembebasan jadwal, penugasan unit fisik, penjagaan serah terima ganda           |
| Pengembalian                            | VERIFIED    | Per unit: konfirmasi individual, validasi massal ≥11 unit per katalog, foto bukti per unit, pencarian/filter/QR fokus, mutasi unit, penyelesaian eksplisit; legacy kuantitas akumulatif |
| Mutasi unit                             | VERIFIED    | Multi-select + pencarian/filter, kode baru diterbitkan, kode lama pensiun, riwayat, label perlu dicetak ulang || Penghapusan                                         | VERIFIED    | Per unit/katalog dengan snapshot `write_off_units`, unique aktif per unit, locking, rollback; laporan lintas katalog konsisten; BA masih satu berkas per pengajuan || QR & label                                          | VERIFIED    | Label unit (PDF), **massal per unit terpilih (filter + select-all lintas halaman)**, re-print, scan kode/ID lama |
| BAST & surat                            | VERIFIED    | Per pinjaman & gabungan kegiatan, PDF privat                                                       || Laporan                                             | VERIFIED    | Impor CSV/XLSX (pratinjau + atomik), hapus arsip soft delete; PDF + Excel 5 sheet (sheet **Unit** per unit fisik; ringkasan kondisi dihitung per unit), filter 4 cakupan; Ketua Laboran hanya data laboratorium, arsip seluruh sekolah khusus Super Admin |
| Notifikasi                              | VERIFIED    | In-app + 4 job terjadwal, deduplikasi                                                             |
| Audit log                               | VERIFIED    | Immutable dari Eloquent; mutasi kode dan unit dicatat                                              |
| Tema & mobile                          | VERIFIED    | Desain lama dipertahankan; 24 halaman × 8 breakpoint (320–1920 px), modal dalam viewport, tabel lebar memakai scroll horizontal |
| Pengujian                                           | VERIFIED    | 330 passed, 1682 assertions (SQLite); MySQL konkuren, Playwright E2E penuh, responsif 8 breakpoint |
| Seeder                                  | VERIFIED    | Master data, akun demo, inventaris demo, dan transaksi demo dipisah; aman dijalankan ulang        |
| Dokumentasi                             | VERIFIED    | README + 12 dokumen `docs/`; status implementasi diselaraskan                                      |
| Deployment                              | BLOCKED     | Panduan + Docker siap; server tidak tersedia                                                       |

## 3. Lingkungan yang diverifikasi

- PHP 8.3.32, Composer 2.10.2
- Laravel 13, Livewire 4, Vite 8, Tailwind CSS 4.3
- MySQL 8.4.3 (Laragon), Node 24; pengujian memakai SQLite in-memory
- `php artisan test` (330 tes), `npm run build`, migrasi + seed SQLite dan MySQL terpisah
- Chrome headless (Playwright) untuk E2E penuh pada MySQL, audit 24 halaman × 8 breakpoint, modal, dan skenario role
- `config:cache`, `route:cache`, `view:cache` diperiksa ulang setelah perubahan

## 4. Data saat ini

- **42 katalog**, **125 unit fisik**, 18 ruangan, **5 jurusan** (RPL, DKV, KLN, TP, TKP), 5 kategori, **9 gedung** (A–G resmi; G/H/I dipakai data demo), 2 kegiatan, 4 peminjaman.
- Laboratorium: RPL `H1-01`/`H1-02`; DKV `G2-01`/`G2-02`/`H2-02`; KLN `H2-01`; TP `G1-01`/`G1-05`; TKP `H2-03`/`H2-04`. Inventaris umum berada di `G1-03`, `I1-01`–`I1-07`.
- Akun demo: lihat `README.md` / `DATABASE.md` §7. Password demo wajib diganti sebelum produksi.

## 5. Pekerjaan tersisa

0. **Keputusan bisnis PENDING** — Master Data Ketua Laboran, batas Super Admin Pengguna atas Super Admin utama, persetujuan perbaikan rusak ringan, dan konfirmasi Ketua Laboran untuk rusak berat. Lihat `PENDING_DECISIONS.md`. Sampai keputusan tersedia, pembatasan aman sudah aktif di backend.

1. **Peminjaman per unit**: `loan_item_units` sudah dibuat dan unit ditugaskan saat serah terima (status `DIPINJAM` → `AKTIF` + kondisi saat kembali). Peminjam memilih katalog + jumlah; pemilihan unit fisik oleh peminjam pada saat pengajuan belum didukung (tetap di serah terima sesuai keputusan 28). Yang tersisa: migrasikan katalog lama ke unit fisik lalu menghapus pembukuan kuantitas warisan. (Lihat `KNOWN_ISSUES.md` butir 1 & 8.)
2. **Penugasan unit pada pengembalian/mutasi katalog lama**: transisi dari `item_movements` ke `asset_unit_movements`.
3. **Konfirmasi daftar gedung resmi** (nama & identitas kode satu huruf) dan daftar laboratorium sebelum go-live; master gedung A–G sudah tersedia, data demo masih memakai H/I.
3b. **Aktifkan verifikasi email** (SMTP + `MustVerifyEmail`) bila sekolah menghendaki pembuktian kepemilikan Gmail.
4. **Uji beban** produksi (bukan hanya 6 proses lokal) dan rollback migration pada salinan database.
5. **Validasi cetak fisik** label pada printer/media/pemindai sesungguhnya.
6. Ganti akun demo, siapkan server/HTTPS, jalankan worker + scheduler, UAT.

## 6. Risiko yang dipantau

- Kolom legacy (`items.quantity`, `qty_damaged`, `qty_lost`) masih ada dan disinkronkan dari unit; jangan diisi manual.
- Nomor unit pensiun mengunci kapasitas ruangan (maks. 99 seumur hidup).
- Scanning kode baru dan ID internal lama dapat bertabrakan karena format lama (`g1-01-01`) juga valid sebagai kode baru; scanner memprioritaskan unit.
- Data demo tidak boleh dipakai di produksi.
- Konkurensi diuji dengan 6 proses lokal dan 3 penghapusan paralel; belum load test produksi. Unique index, `lockForUpdate`, dan validasi ulang dalam transaksi sudah diterapkan.
- Docker Compose belum dijalankan di host ber-Docker; deployment belum dilakukan.

## 7. Revisi V6 (3 November 2026)

| Perubahan | Status | Bukti |
|---|---|---|
| Mutasi beberapa lokasi tujuan dalam satu transaksi | **IMPLEMENTED & TESTED** | `MovementAndWriteOffTest` (2 tes baru), `asset_unit_movements.movement_no`, `MovementController::store` + form kelompok tujuan |
| Pencarian nama barang/merek pada cetak label | **IMPLEMENTED & TESTED** | `LabelAndQrTest` (2 tes baru), `LabelController::filteredUnits` |
| Notifikasi berbasis role/cakupan + penjaga tautan | **IMPLEMENTED & TESTED** | `NotificationFeatureTest` (3 tes baru), `DamageReportTest` (1 tes baru), `NotificationLinkGuard`, `NotifyApp` |
| Laporan kerusakan & perubahan akun mengirim notifikasi ke pihak berwenang | **IMPLEMENTED** | `DamageReportService`, `UserController` |
| Perbaikan halaman detail barang yang tidak merender konten | **FIXED** | `resources/views/items/show.blade.php` diselaraskan dengan layout komponen |
| Scope `notArchived()` pada katalog | **FIXED** | `Item::scopeNotArchived()`; dipakai pencarian katalog Livewire |
| Metode pinjaman pada model Item | **FIXED** | `pendingLostQuery()`, `isAvailableStatus()`, `effectiveStatus()`, `availableQuantity()`, `stockSummary()`, `canBeDeletedPermanently()` dipulihkan |
| Regresi suite Feature | **PASS** | 352 tes, 1919 assertions, 0 gagal (dijalankan bertahap); `npm run build` + `view:cache` berhasil |

Keputusan bisnis di `PENDING_DECISIONS.md` **tidak berubah**: pembatasan aman tetap aktif, alur persetujuan yang belum disepakati tidak diaktifkan. Cetak fisik label dan verifikasi email tetap **BELUM DIVERIFIKASI**.

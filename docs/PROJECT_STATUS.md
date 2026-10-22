# Project Status

Tanggal: **22 Oktober 2026**
Versi: v2 + implementasi inti revisi **2.1** + pengetatan cakupan laporan
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
| UI/UX desain lama + responsif + tema gelap                   | **IMPLEMENTED & VERIFIED** — Tailwind v4 menghasilkan utility CSS; 19 halaman diperiksa |
| Dashboard per role (katalog, unit, cakupan lab)              | **IMPLEMENTED & VERIFIED**                                                                 |
| Penugasan unit fisik pada peminjaman (`loan_item_units`)      | **NOT STARTED** — peminjaman masih level katalog/kuantitas                                 |
| Penghapusan per unit                                         | **NOT STARTED** — masih level baris katalog                                                |
| Cakupan laporan/ekspor untuk Ketua Laboran                   | **IMPLEMENTED & VERIFIED** — data, PDF, Excel, dan arsip dibatasi laboratorium             |
| Audit sinkronisasi fitur vs PRD 2.1                          | **IMPLEMENTED & VERIFIED** — matriks di `feature-sync-audit.md`; label massal per unit, penghapusan mengarsip unit, laporan per unit |
| Deployment produksi                                          | **BLOCKED** — tidak ada akses server/domain pada lingkungan ini                            |

Pengujian terakhir: **130 passed, 0 skipped, 613 assertions** (SQLite in-memory), `npm run build` berhasil, dan migrasi + seed berhasil pada database SQLite terpisah. Verifikasi browser (Playwright/Chrome) pada alur katalog → detail → pencarian → filter → detail unit, pembatasan role, dan navigasi mobile: **PASS** tanpa error JavaScript.

## 2. Status per modul

| Modul                                   | Status      | Catatan                                                                                          |
| --------------------------------------- | ----------- | ------------------------------------------------------------------------------------------------ |
| Autentikasi & sesi                      | VERIFIED    | Username/password, rate limit 5 percobaan, logout POST                                            |
| Role & permission                       | VERIFIED    | 4 role + middleware Spatie; cakupan laboratorium baru dan cakupan jurusan lama tetap ada          |
| Dashboard                               | VERIFIED    | Data unit/katalog nyata, grafik Chart.js, cakupan per role (sekolah/lab/ruangan), personal vs operasional |
| Katalog jenis barang                    | VERIFIED    | Tabel `item_catalogs`; jumlah unit dihitung dari unit; pencarian/filter/pagination                 |
| Unit inventaris fisik                   | VERIFIED    | Tabel `asset_units`; kondisi/status/catatan dapat diubah sesuai role dan tercatat di audit log       |
| Kode inventaris `G1-02-12`              | VERIFIED    | Validasi pola/lokasi, ledger kode, transaksi + unique index, batas 99, larangan daur ulang         |
| Detail katalog & pencarian kode         | VERIFIED    | Pencarian exact/prefix, pesan format, scope, empty state, pagination 25 unit                       |
| Filter lokasi/kondisi/status/tahun      | VERIFIED    | Filter AND; pilihan filter mengikuti data yang terlihat                                           |
| Master data                             | VERIFIED    | CRUD jurusan/ruangan/kategori + identitas ruangan (`building_letter`, `room_no`, `is_lab`)          |
| Pengguna & cakupan laboratorium         | VERIFIED    | `user_room_scopes`; perubahan cakupan dicatat di audit log dengan ruangan sebelum/sesudah          |
| Pengadaan                               | VERIFIED    | Unit baru diterbitkan saat pengadaan disetujui; pengajuan ditolak tidak memakai kode               |
| Peminjaman multi-item                   | VERIFIED    | Masih level katalog/kuantitas; ketersediaan katalog dikurangi pinjaman aktif                       |
| Serah terima & pembatalan               | VERIFIED    | Timestamp petugas, pembebasan jadwal; penugasan unit fisik belum                                   |
| Pengembalian                            | VERIFIED    | Parsial akumulatif, foto bukti, mutasi katalog, selisih                                            |
| Mutasi unit                             | VERIFIED    | Kode baru diterbitkan, kode lama pensiun, riwayat perpindahan, label perlu dicetak ulang           || Penghapusan                                         | VERIFIED    | Level baris katalog; persetujuan kini **mengarsipkan unit fisik** dan menghitung ulang counter katalog; pemilihan unit per BA belum || QR & label                                          | VERIFIED    | Label unit (PDF), **massal per unit terpilih (filter + select-all lintas halaman)**, re-print, scan kode/ID lama |
| BAST & surat                            | VERIFIED    | Per pinjaman & gabungan kegiatan, PDF privat                                                       || Laporan                                             | VERIFIED    | PDF + Excel 5 sheet (sheet **Unit** per unit fisik; ringkasan kondisi dihitung per unit), filter 4 cakupan; Ketua Laboran hanya data laboratorium, arsip seluruh sekolah khusus Super Admin |
| Notifikasi                              | VERIFIED    | In-app + 4 job terjadwal, deduplikasi                                                             |
| Audit log                               | VERIFIED    | Immutable dari Eloquent; mutasi kode dan unit dicatat                                              |
| Tema & mobile                          | VERIFIED    | Desain lama dipertahankan; bottom nav, dark mode, PWA; tanpa overflow horizontal di 390px           || Pengujian                                           | VERIFIED    | 130 passed, 613 assertions (SQLite in-memory)                                                      |
| Dokumentasi                             | VERIFIED    | README + 12 dokumen `docs/`; status implementasi diselaraskan                                      |
| Deployment                              | BLOCKED     | Panduan + Docker siap; server tidak tersedia                                                       |

## 3. Lingkungan yang diverifikasi

- PHP 8.3.32, Composer 2.10.2
- Laravel 13, Livewire 4, Vite 8, Tailwind CSS 4.3
- MySQL 8.4.3 (Laragon), Node 24; pengujian memakai SQLite in-memory
- `php artisan test` (130 tes), `npm run build` (CSS ±78 KB dengan utility lengkap), migrasi + seed SQLite terpisah
- Chrome headless (Playwright) untuk verifikasi UI: 19 halaman, alur katalog/unit, laporan, 4 role, mobile 390px, dan tema gelap
- `config:cache`, `route:cache`, `view:cache` diperiksa ulang setelah perubahan

## 4. Data saat ini

- 10 katalog barang demo, **40 unit fisik**, 6 ruangan, 3 jurusan, 4 kategori, 2 kegiatan, 4 peminjaman, 1 laporan arsip.
- Kode unit demo: `G1-01-xx` (Lab Komputer 1), `G2-02-xx` (Lab Jaringan), `H1-01-xx` (Lab RPL), `H2-02-xx` (Studio), `G1-03-xx` (Ruang Guru).
- Pemetaan gedung demo: `G1` → G, `G2` → H, `G3` → I. **Wajib dikonfirmasi sebelum migrasi data sekolah.**
- Akun demo: lihat `README.md` / `DATABASE.md` §7.

## 5. Pekerjaan tersisa

1. **Peminjaman per unit**: buat `loan_item_units`, tugaskan unit saat serah terima, perbarui kondisi/status unit saat kembali, dan tampilkan riwayat unit. (Di luar acceptance criteria inti 2.1; lihat `KNOWN_ISSUES.md`.)
2. **Penghapusan per unit**: ubah `write_offs` ke `asset_unit_id` + `batch_no`; sesuaikan BA/approval.
3. **Penugasan unit pada pengembalian/mutasi katalog lama**: transisi dari `item_movements` ke `asset_unit_movements`.
4. **Konfirmasi pemetaan gedung** dan daftar laboratorium resmi sebelum go-live.
5. **Uji beban** penerbitan kode pada MySQL dengan konkurensi nyata.
6. Ganti akun demo, siapkan server/HTTPS, jalankan worker + scheduler, UAT.

## 6. Risiko yang dipantau

- Kolom legacy (`items.quantity`, `qty_damaged`, `qty_lost`) masih ada dan disinkronkan dari unit; jangan diisi manual.
- Nomor unit pensiun mengunci kapasitas ruangan (maks. 99 seumur hidup).
- Scanning kode baru dan ID internal lama dapat bertabrakan karena format lama (`g1-01-01`) juga valid sebagai kode baru; scanner memprioritaskan unit.
- Data demo tidak boleh dipakai di produksi.
- Konkurensi tinggi belum di-load-test; unique index + lock ruangan sudah diterapkan.
- Docker Compose belum dijalankan di host ber-Docker; deployment belum dilakukan.

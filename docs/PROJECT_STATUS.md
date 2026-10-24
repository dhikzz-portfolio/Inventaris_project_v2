# Project Status

Tanggal: **24 Oktober 2026**
Versi: v2 + revisi **2.1**, **revisi lanjutan 22 Oktober 2026**, dan **revisi besar 24 Oktober 2026** (role, seeder lima jurusan, persetujuan otomatis, katalog peminjam, arsip katalog, batas penyerahan)
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
| Penugasan unit fisik pada peminjaman (`loan_item_units`)      | **IMPLEMENTED & VERIFIED** — unit ditugaskan saat serah terima, status `DIPINJAM`/`AKTIF` + kondisi mengikuti pengembalian |
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
| Penghapusan per unit                                         | **BELUM** — masih level baris katalog                                                     |
| Cakupan laporan/ekspor untuk Ketua Laboran                   | **IMPLEMENTED & VERIFIED** — data, PDF, Excel, dan arsip dibatasi laboratorium             |
| Audit sinkronisasi fitur vs PRD 2.1                          | **IMPLEMENTED & VERIFIED** — matriks di `feature-sync-audit.md`; label massal per unit, penghapusan mengarsip unit, laporan per unit |
| Deployment produksi                                          | **BLOCKED** — tidak ada akses server/domain pada lingkungan ini                            |

Pengujian terakhir (24 Oktober 2026): **194 passed, 0 skipped, 932 assertions** (SQLite in-memory). `php artisan view:cache`, `route:list`, dan seed berulang berhasil. Verifikasi browser (Playwright/Chrome) pada rilis sebelumnya tetap berlaku untuk alur katalog/unit/navigasi; perubahan UI revisi ini diverifikasi melalui render Blade, Livewire test, dan HTTP test (bukan screenshot baru).

## 2. Status per modul

| Modul                                   | Status      | Catatan                                                                                          |
| --------------------------------------- | ----------- | ------------------------------------------------------------------------------------------------ |
| Autentikasi & sesi                      | VERIFIED    | Username/password, rate limit 5 percobaan, logout POST                                            |
| Role & permission                       | VERIFIED    | 4 role final (`SUPER_ADMIN`, `KETUA_LABORAN`, `LABORAN`, `PEMINJAM`) + middleware Spatie; kategori peminjam di `borrower_type` |
| Dashboard                               | VERIFIED    | Data unit/katalog nyata, grafik Chart.js, cakupan per role (sekolah/lab/ruangan), personal vs operasional |
| Katalog jenis barang                    | VERIFIED    | Tabel `item_catalogs`; jumlah unit dihitung dari unit; pencarian/filter/pagination                 |
| Unit inventaris fisik                   | VERIFIED    | Tabel `asset_units`; kondisi/status/catatan dapat diubah sesuai role dan tercatat di audit log       |
| Kode inventaris `G1-02-12`              | VERIFIED    | Validasi pola/lokasi, ledger kode, transaksi + unique index, batas 99, larangan daur ulang         |
| Detail katalog & pencarian kode         | VERIFIED    | Pencarian exact/prefix, pesan format, scope, empty state, pagination 25 unit                       |
| Filter lokasi/kondisi/status/tahun      | VERIFIED    | Filter AND; pilihan filter mengikuti data yang terlihat                                           |
| Master data                             | VERIFIED    | CRUD jurusan/gedung/ruangan/kategori; master gedung (`buildings`) + identitas ruangan (`building_id`, `building_letter`, `floor`, `room_no`, `is_lab`) |
| Pengguna & cakupan laboratorium         | VERIFIED    | `user_room_scopes`; perubahan cakupan dicatat di audit log dengan ruangan sebelum/sesudah          |
| Pengadaan                               | VERIFIED    | Unit baru diterbitkan saat pengadaan disetujui; pengajuan ditolak tidak memakai kode               |
| Peminjaman multi-item                   | VERIFIED    | Multi-item; ketersediaan katalog dikurangi pinjaman aktif; status transaksi menutup rantai persetujuan (PENDING → APPROVED) |
| Serah terima & pembatalan               | VERIFIED    | Timestamp petugas, pembebasan jadwal, penugasan unit fisik, penjagaan serah terima ganda           |
| Pengembalian                            | VERIFIED    | Parsial akumulatif, foto bukti, mutasi katalog, selisih                                            |
| Mutasi unit                             | VERIFIED    | Kode baru diterbitkan, kode lama pensiun, riwayat perpindahan, label perlu dicetak ulang           || Penghapusan                                         | VERIFIED    | Level baris katalog; persetujuan kini **mengarsipkan unit fisik** dan menghitung ulang counter katalog; pemilihan unit per BA belum || QR & label                                          | VERIFIED    | Label unit (PDF), **massal per unit terpilih (filter + select-all lintas halaman)**, re-print, scan kode/ID lama |
| BAST & surat                            | VERIFIED    | Per pinjaman & gabungan kegiatan, PDF privat                                                       || Laporan                                             | VERIFIED    | PDF + Excel 5 sheet (sheet **Unit** per unit fisik; ringkasan kondisi dihitung per unit), filter 4 cakupan; Ketua Laboran hanya data laboratorium, arsip seluruh sekolah khusus Super Admin |
| Notifikasi                              | VERIFIED    | In-app + 4 job terjadwal, deduplikasi                                                             |
| Audit log                               | VERIFIED    | Immutable dari Eloquent; mutasi kode dan unit dicatat                                              |
| Tema & mobile                          | VERIFIED    | Desain lama dipertahankan; bottom nav, dark mode, PWA; tanpa overflow horizontal di 390px           || Pengujian                                           | VERIFIED    | 194 passed, 932 assertions (SQLite in-memory)                                                      |
| Seeder                                  | VERIFIED    | Master data, akun demo, inventaris demo, dan transaksi demo dipisah; aman dijalankan ulang        |
| Dokumentasi                             | VERIFIED    | README + 12 dokumen `docs/`; status implementasi diselaraskan                                      |
| Deployment                              | BLOCKED     | Panduan + Docker siap; server tidak tersedia                                                       |

## 3. Lingkungan yang diverifikasi

- PHP 8.3.32, Composer 2.10.2
- Laravel 13, Livewire 4, Vite 8, Tailwind CSS 4.3
- MySQL 8.4.3 (Laragon), Node 24; pengujian memakai SQLite in-memory
- `php artisan test` (194 tes), `npm run build` (CSS ±78 KB dengan utility lengkap), migrasi + seed SQLite terpisah
- Chrome headless (Playwright) untuk verifikasi UI: 19 halaman, alur katalog/unit, laporan, 4 role, mobile 390px, dan tema gelap
- `config:cache`, `route:cache`, `view:cache` diperiksa ulang setelah perubahan

## 4. Data saat ini

- **42 katalog**, **125 unit fisik**, 18 ruangan, **5 jurusan** (RPL, DKV, KLN, TP, TKP), 5 kategori, 3 gedung, 2 kegiatan, 4 peminjaman.
- Laboratorium: RPL `H1-01`/`H1-02`; DKV `G2-01`/`G2-02`/`H2-02`; KLN `H2-01`; TP `G1-01`/`G1-05`; TKP `H2-03`/`H2-04`. Inventaris umum berada di `G1-03`, `I1-01`–`I1-07`.
- Akun demo: lihat `README.md` / `DATABASE.md` §7. Password demo wajib diganti sebelum produksi.

## 5. Pekerjaan tersisa

1. **Peminjaman per unit**: `loan_item_units` sudah dibuat dan unit ditugaskan saat serah terima (status `DIPINJAM` → `AKTIF` + kondisi saat kembali). Peminjam memilih katalog + jumlah; pemilihan unit fisik oleh peminjam pada saat pengajuan belum didukung (tetap di serah terima sesuai keputusan 28). Yang tersisa: migrasikan katalog lama ke unit fisik lalu menghapus pembukuan kuantitas warisan. (Lihat `KNOWN_ISSUES.md` butir 1 & 8.)
2. **Penghapusan per unit**: ubah `write_offs` ke `asset_unit_id` + `batch_no`; sesuaikan BA/approval.
3. **Penugasan unit pada pengembalian/mutasi katalog lama**: transisi dari `item_movements` ke `asset_unit_movements`.
4. **Konfirmasi daftar gedung resmi** (nama & identitas kode satu huruf) dan daftar laboratorium sebelum go-live; master gedung sudah siap menyimpan hasil konfirmasi tersebut.
5. **Uji beban** penerbitan kode pada MySQL dengan konkurensi nyata.
6. Ganti akun demo, siapkan server/HTTPS, jalankan worker + scheduler, UAT.

## 6. Risiko yang dipantau

- Kolom legacy (`items.quantity`, `qty_damaged`, `qty_lost`) masih ada dan disinkronkan dari unit; jangan diisi manual.
- Nomor unit pensiun mengunci kapasitas ruangan (maks. 99 seumur hidup).
- Scanning kode baru dan ID internal lama dapat bertabrakan karena format lama (`g1-01-01`) juga valid sebagai kode baru; scanner memprioritaskan unit.
- Data demo tidak boleh dipakai di produksi.
- Konkurensi tinggi belum di-load-test; unique index + lock ruangan sudah diterapkan.
- Docker Compose belum dijalankan di host ber-Docker; deployment belum dilakukan.

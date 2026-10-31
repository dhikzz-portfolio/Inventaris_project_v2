# Project Status

Tanggal: **29 Oktober 2026**
Versi: v2 + revisi **2.1**, **revisi lanjutan 22 Oktober 2026**, **revisi besar 24 Oktober 2026** (role, seeder lima jurusan, persetujuan otomatis, katalog peminjam, arsip katalog, batas penyerahan), **revisi besar V2 26 Oktober 2026** (registrasi Gmail, scan universal, gedung A–G, pengadaan multi-lokasi, mutasi multi-unit, impor/hapus laporan, status kegiatan, surat peminjaman, UI pengguna), **revisi validasi pengembalian per unit 28 Oktober 2026**, dan **revisi serah terima/validasi/rincian barang 29 Oktober 2026**. Rincian: `REVISION_V2.md`, `DECISIONS.md` butir 38–39, dan `TESTING.md` bagian 12.
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
| Penghapusan per unit                                         | **BELUM** — masih level baris katalog                                                     |
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
| Verifikasi email (SMTP)                                      | **BELUM** — kolom & event siap; SMTP/MustVerifyEmail belum dikonfigurasi                                                    |
| Deployment produksi                                          | **BLOCKED** — tidak ada akses server/domain pada lingkungan ini                            |

Pengujian terakhir (29 Oktober 2026): **282 passed, 0 skipped, 1384 assertions** (SQLite in-memory, locale `id`, timezone `Asia/Jakarta`, dijalankan dengan `memory_limit=1G`). `vendor/bin/pint --dirty` dijalankan. Verifikasi browser (Playwright/Chrome) pada rilis sebelumnya tetap berlaku untuk alur katalog/unit/navigasi; perubahan UI revisi V2 diverifikasi melalui render Blade, Livewire test, dan HTTP test (bukan screenshot baru).

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
| Pengadaan                               | VERIFIED    | Unit baru diterbitkan saat pengadaan disetujui; pengajuan ditolak tidak memakai kode               |
| Peminjaman multi-item                   | VERIFIED    | Multi-item; ketersediaan katalog dikurangi pinjaman aktif; status transaksi menutup rantai persetujuan (PENDING → APPROVED) |
| Serah terima & pembatalan               | VERIFIED    | Timestamp petugas, pembebasan jadwal, penugasan unit fisik, penjagaan serah terima ganda           |
| Pengembalian                            | VERIFIED    | Per unit: konfirmasi individual, validasi massal ≥11 unit per katalog, foto bukti per unit, pencarian/filter/QR fokus, mutasi unit, penyelesaian eksplisit; legacy kuantitas akumulatif |
| Mutasi unit                             | VERIFIED    | Multi-select + pencarian/filter, kode baru diterbitkan, kode lama pensiun, riwayat, label perlu dicetak ulang || Penghapusan                                         | VERIFIED    | Level baris katalog; persetujuan kini **mengarsipkan unit fisik** dan menghitung ulang counter katalog; pemilihan unit per BA belum || QR & label                                          | VERIFIED    | Label unit (PDF), **massal per unit terpilih (filter + select-all lintas halaman)**, re-print, scan kode/ID lama |
| BAST & surat                            | VERIFIED    | Per pinjaman & gabungan kegiatan, PDF privat                                                       || Laporan                                             | VERIFIED    | Impor CSV/XLSX (pratinjau + atomik), hapus arsip soft delete; PDF + Excel 5 sheet (sheet **Unit** per unit fisik; ringkasan kondisi dihitung per unit), filter 4 cakupan; Ketua Laboran hanya data laboratorium, arsip seluruh sekolah khusus Super Admin |
| Notifikasi                              | VERIFIED    | In-app + 4 job terjadwal, deduplikasi                                                             |
| Audit log                               | VERIFIED    | Immutable dari Eloquent; mutasi kode dan unit dicatat                                              |
| Tema & mobile                          | VERIFIED    | Desain lama dipertahankan; bottom nav, dark mode, PWA; tanpa overflow horizontal di 390px           || Pengujian                                           | VERIFIED    | 282 passed, 1384 assertions (SQLite in-memory)                                                      |
| Seeder                                  | VERIFIED    | Master data, akun demo, inventaris demo, dan transaksi demo dipisah; aman dijalankan ulang        |
| Dokumentasi                             | VERIFIED    | README + 12 dokumen `docs/`; status implementasi diselaraskan                                      |
| Deployment                              | BLOCKED     | Panduan + Docker siap; server tidak tersedia                                                       |

## 3. Lingkungan yang diverifikasi

- PHP 8.3.32, Composer 2.10.2
- Laravel 13, Livewire 4, Vite 8, Tailwind CSS 4.3
- MySQL 8.4.3 (Laragon), Node 24; pengujian memakai SQLite in-memory
- `php artisan test` (249 tes), `npm run build` (CSS ±78 KB dengan utility lengkap), migrasi + seed SQLite terpisah
- Chrome headless (Playwright) untuk verifikasi UI: 19 halaman, alur katalog/unit, laporan, 4 role, mobile 390px, dan tema gelap
- `config:cache`, `route:cache`, `view:cache` diperiksa ulang setelah perubahan

## 4. Data saat ini

- **42 katalog**, **125 unit fisik**, 18 ruangan, **5 jurusan** (RPL, DKV, KLN, TP, TKP), 5 kategori, **9 gedung** (A–G resmi; G/H/I dipakai data demo), 2 kegiatan, 4 peminjaman.
- Laboratorium: RPL `H1-01`/`H1-02`; DKV `G2-01`/`G2-02`/`H2-02`; KLN `H2-01`; TP `G1-01`/`G1-05`; TKP `H2-03`/`H2-04`. Inventaris umum berada di `G1-03`, `I1-01`–`I1-07`.
- Akun demo: lihat `README.md` / `DATABASE.md` §7. Password demo wajib diganti sebelum produksi.

## 5. Pekerjaan tersisa

1. **Peminjaman per unit**: `loan_item_units` sudah dibuat dan unit ditugaskan saat serah terima (status `DIPINJAM` → `AKTIF` + kondisi saat kembali). Peminjam memilih katalog + jumlah; pemilihan unit fisik oleh peminjam pada saat pengajuan belum didukung (tetap di serah terima sesuai keputusan 28). Yang tersisa: migrasikan katalog lama ke unit fisik lalu menghapus pembukuan kuantitas warisan. (Lihat `KNOWN_ISSUES.md` butir 1 & 8.)
2. **Penghapusan per unit**: ubah `write_offs` ke `asset_unit_id` + `batch_no`; sesuaikan BA/approval.
3. **Penugasan unit pada pengembalian/mutasi katalog lama**: transisi dari `item_movements` ke `asset_unit_movements`.
4. **Konfirmasi daftar gedung resmi** (nama & identitas kode satu huruf) dan daftar laboratorium sebelum go-live; master gedung A–G sudah tersedia, data demo masih memakai H/I.
4b. **Aktifkan verifikasi email** (SMTP + `MustVerifyEmail`) bila sekolah menghendaki pembuktian kepemilikan Gmail.
5. **Uji beban** penerbitan kode pada MySQL dengan konkurensi nyata.
6. Ganti akun demo, siapkan server/HTTPS, jalankan worker + scheduler, UAT.

## 6. Risiko yang dipantau

- Kolom legacy (`items.quantity`, `qty_damaged`, `qty_lost`) masih ada dan disinkronkan dari unit; jangan diisi manual.
- Nomor unit pensiun mengunci kapasitas ruangan (maks. 99 seumur hidup).
- Scanning kode baru dan ID internal lama dapat bertabrakan karena format lama (`g1-01-01`) juga valid sebagai kode baru; scanner memprioritaskan unit.
- Data demo tidak boleh dipakai di produksi.
- Konkurensi tinggi belum di-load-test; unique index + lock ruangan sudah diterapkan.
- Docker Compose belum dijalankan di host ber-Docker; deployment belum dilakukan.

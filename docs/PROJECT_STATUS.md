# Project Status

Tanggal: **20 Oktober 2026**
Versi: v2 (direktori `project_inventaris_v2`)
Database pengembangan: `inventaris_sekolah_v2` (MySQL 8.4)

## 1. Ringkasan status

**IMPLEMENTED & VERIFIED untuk seluruh modul inti.**
**Deployment produksi: BLOCKED** (tidak ada akses server/domain pada lingkungan ini).

Aplikasi berjalan lokal, terhubung ke MySQL, seluruh alur inti diuji otomatis dan diverifikasi melalui browser Chrome headless.

## 2. Status per modul

| Modul                     | Status   | Catatan                                                |
| ------------------------- | -------- | ------------------------------------------------------ |
| Autentikasi & sesi        | VERIFIED | Username/password, rate limit 5 percobaan, logout POST |
| Role & permission         | VERIFIED | 4 role + middleware Spatie + cakupan jurusan           |
| Dashboard                 | VERIFIED | Data aktual, grafik Chart.js, personal vs operasional  |
| Katalog & detail aset     | VERIFIED | Search debounce, filter, pagination, stok tersedia     |
| Master data               | VERIFIED | CRUD jurusan/ruangan/kategori + proteksi relasi        |
| Pengadaan                 | VERIFIED | Pengajuan + approval Wakasek                           |
| Peminjaman multi-item     | VERIFIED | Keranjang, kalender, anti-bentrok, approval berjenjang |
| Serah terima & pembatalan | VERIFIED | Timestamp petugas, pembebasan jadwal                   |
| Pengembalian              | VERIFIED | Parsial akumulatif, foto bukti, mutasi, selisih        |
| Mutasi lokasi             | VERIFIED | Atomik + update ID + riwayat                           |
| Penghapusan               | VERIFIED | Berita Acara + approval + stok                         |
| QR & label                | VERIFIED | PDF satuan/massal, re-print, scan kamera/manual        |
| BAST & surat              | VERIFIED | Per pinjaman & gabungan kegiatan, PDF privat           |
| Laporan                   | VERIFIED | PDF + Excel 5 sheet, filter 4 cakupan                  |
| Notifikasi                | VERIFIED | In-app + 4 job terjadwal, deduplikasi                  |
| Audit log                 | VERIFIED | Immutable dari Eloquent                                |
| Tema & mobile             | VERIFIED | Bottom nav, dark mode, PWA manifest                    |
| Pengujian                 | VERIFIED | 91 passed, 0 skipped, 407 assertions                   |
| Dokumentasi               | VERIFIED | README + 9 dokumen `docs/`                             |
| Deployment                | BLOCKED  | Panduan + Docker siap; server tidak tersedia           |

## 3. Lingkungan yang diverifikasi

- PHP 8.3.32, Composer 2.10.2
- Laravel 13, Livewire 4, Vite 8, Tailwind CSS 3
- MySQL 8.4.3 (Laragon), Node 24
- Chrome headless (Playwright) untuk verifikasi UI/E2E
- `config:cache`, `route:cache`, `view:cache` berhasil
- Queue worker: job `CheckDueSoonLoans` DONE, `failed_jobs=0`
- Scheduler: 4 job terdaftar

## 4. Data saat ini

- 12 barang demo, 6 ruangan, 3 jurusan, 5 kategori, 2 kegiatan, 4 peminjaman, 1 laporan arsip (PDF + Excel, Oktober 2026).
- Akun demo: lihat `README.md` / `DATABASE.md`.

## 5. Langkah berikutnya

1. Ganti password/akun demo dan isi pengaturan instansi sebenarnya.
2. Siapkan server + domain + HTTPS, ikuti `DEPLOYMENT.md`.
3. Jalankan worker & scheduler sebagai service.
4. Lakukan UAT dengan laboran dan Wakasek Sarpras.
5. Backup otomatis dan uji restore.
6. (Opsional) tambah `asset_units` untuk QR per unit dan integrasi e-signature tersertifikasi.

## 6. Risiko yang dipantau

- Data demo tidak boleh dipakai di produksi.
- Konkurensi tinggi belum di-load-test.
- Docker Compose belum dijalankan di host ber-Docker.
- Kamera QR memerlukan HTTPS.

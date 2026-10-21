# Sistem Manajemen Inventaris & Aset Sekolah

Aplikasi web terpusat untuk mengelola inventaris dan aset sekolah: katalog aset, pengadaan, mutasi lokasi, peminjaman multi-item dengan anti-bentrok, approval berjenjang, pengembalian tervalidasi, label QR, BAST, laporan PDF/Excel, notifikasi, dan audit trail.

Dibangun dengan **Laravel 13 + Livewire 4 + Blade + Alpine.js (bawaan Livewire) + Tailwind CSS + Chart.js**, database **MySQL/MariaDB**, dan Role/Permission Spatie.

---

## Fitur utama

- **Autentikasi username/password** (bukan email), 4 role: `SUPER_ADMIN`, `ADMIN_SARPRAS`, `LABORAN`, `PEMINJAM`.
- **Dashboard berbeda per role** dengan statistik aktual dan grafik Chart.js.
- **Katalog aset** dengan pencarian debounce, filter kategori/ruangan/status, pagination, stok tersedia per unit, dan riwayat lengkap.
- **Identitas inventaris ganda**: ID internal/spasial, kode lokasi/instansi, kode barang, nomor register.
- **Pengadaan** dengan approval Wakasek; **penghapusan** dengan Berita Acara + approval dan riwayat tetap tersimpan.
- **Mutasi ruangan atomik** yang memperbarui ID internal dan mencatat riwayat.
- **Peminjaman multi-item**: keranjang, kalender ketersediaan, validasi bentrok jadwal di server + transaksi/lock database.
- **Approval berjenjang**: Laboran level 1; Wakasek level 2 untuk barang bernilai tinggi (wajib tanda tangan).
- **Siklus jelas**: Menunggu → Disetujui → **Serah terima** → Dipinjam → Selesai; bisa Ditolak/Dibatalkan.
- **Pengembalian parsial akumulatif**, foto bukti kerusakan, deteksi kehilangan, mutasi jika ruangan berbeda, penutupan selisih oleh Wakasek.
- **QR Code** berisi URL detail, scanner kamera (`html5-qrcode`) + fallback input manual.
- **Label stiker** satuan & massal (PDF), preview, dan re-print.
- **Surat Peminjaman / BAST** PDF per pinjaman dan gabungan kegiatan.
- **Laporan bulanan** PDF + **Excel 5 sheet**, filter sekolah/kategori/ruangan/jurusan, arsip snapshot.
- **Notifikasi in-app** + job terjadwal (jatuh tempo, terlambat, laporan bulanan) dengan retry & deduplikasi.
- **Keamanan**: CSRF, rate limit login, otorisasi route + query + cakupan jurusan, file internal privat, upload tervalidasi, audit log immutable.
- **UI mobile-first**: bottom navigation, target sentuh besar, tema terang/gelap, PWA manifest.

---

## Stack

| Lapisan | Teknologi |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Eloquent, Form Request, Service layer |
| Frontend | Blade, Livewire 4, Alpine.js, Tailwind CSS, Chart.js, Material Icons |
| Database | MySQL 8 / MariaDB 10.11 (dev: MySQL 8.4; test: SQLite in-memory) |
| Otorisasi | `spatie/laravel-permission` |
| Audit | `spatie/laravel-activitylog` (immutable) |
| Dokumen | `barryvdh/laravel-dompdf`, `maatwebsite/excel`, `simplesoftwareio/simple-qrcode` |
| Queue/Cache | Database (default), Redis (opsional/Docker) |

---

## Mulai cepat

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# Buat database MySQL: inventaris_sekolah
# Sesuaikan DB_* di .env

php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`.

### Akun demo (lokal saja)

| Username | Password | Role |
| --- | --- | --- |
| `wakasek` | `wakasek123` | SUPER_ADMIN |
| `admin.sarpras` | `admin123` | ADMIN_SARPRAS |
| `laboran.tkj` | `laboran123` | LABORAN (TKJ) |
| `laboran.rpl` | `laboran123` | LABORAN (RPL) |
| `guru.budi` | `guru123` | PEMINJAM |
| `guru.sari` | `guru123` | PEMINJAM |

> **Ganti seluruh akun demo sebelum produksi.** Panduan membuat admin pertama ada di `docs/INSTALLATION.md`.

---

## Menjalankan worker & scheduler

```bash
php artisan queue:work --tries=3
php artisan schedule:work        # atau cron: * * * * * php artisan schedule:run
```

Tanpa worker, notifikasi dan laporan otomatis tidak diproses.

---

## Pengujian

```bash
php artisan optimize:clear
php artisan test
npm run build
vendor/bin/pint --test
```

Hasil terakhir: **91 passed, 0 skipped, 407 assertions** (SQLite in-memory). Verifikasi browser end-to-end juga dilakukan: ajukan → setujui → serah terima → kembalikan → selesai.

Detail: [`docs/TESTING.md`](docs/TESTING.md).

---

## Dokumentasi

| Dokumen | Isi |
| --- | --- |
| [`docs/PROJECT_BRIEF.md`](docs/PROJECT_BRIEF.md) | Latar belakang, tujuan, cakupan, peran |
| [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md) | Fase & status implementasi |
| [`docs/DECISIONS.md`](docs/DECISIONS.md) | Keputusan arsitektur dan trade-off |
| [`docs/DATABASE.md`](docs/DATABASE.md) | Kamus tabel, relasi, model kuantitas |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Instalasi, akun admin aman, troubleshooting |
| [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) | Panduan per role |
| [`docs/TESTING.md`](docs/TESTING.md) | Hasil tes, performa, verifikasi browser |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Nginx/VPS, Docker, queue, backup |
| [`docs/KNOWN_ISSUES.md`](docs/KNOWN_ISSUES.md) | Keterbatasan & pekerjaan lanjutan |
| [`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) | Status terakhir proyek |

---

## Status

**IMPLEMENTED & VERIFIED** untuk seluruh modul inti di lingkungan lokal.
**Belum dideploy** ke server produksi — lihat `docs/DEPLOYMENT.md` dan `docs/KNOWN_ISSUES.md` untuk langkah dan keterbatasan.

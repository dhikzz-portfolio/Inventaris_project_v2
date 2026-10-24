# Sistem Manajemen Inventaris & Aset Sekolah

Aplikasi web terpusat untuk mengelola inventaris dan aset sekolah: katalog jenis barang, unit inventaris fisik, pengadaan, mutasi lokasi, peminjaman multi-item dengan anti-bentrok, approval berjenjang, pengembalian tervalidasi, label QR, BAST, laporan PDF/Excel, notifikasi, dan audit trail.

Dibangun dengan **Laravel 13 + Livewire 4 + Blade + Alpine.js (bawaan Livewire) + Tailwind CSS + Chart.js**, database **MySQL/MariaDB**, dan Role/Permission Spatie.

---

## Fitur utama

> Fitur bertanda **(2.1)** telah diimplementasikan dan diverifikasi untuk katalog, unit fisik, kode inventaris, mutasi, cakupan akses, label, dan laporan. Status lengkap serta pekerjaan lanjutan ada di `docs/PROJECT_STATUS.md` dan `docs/KNOWN_ISSUES.md`.

- **Autentikasi username/password** (bukan email), 4 role final: `SUPER_ADMIN` (Wakasek Sarpras), `KETUA_LABORAN` (Ketua Laboran), `LABORAN` (Laboran/K3), `PEMINJAM`. Kategori siswa/guru/staf disimpan terpisah di `users.borrower_type`.
- **Dashboard berbeda per role** dengan statistik aktual dan grafik Chart.js.
- **Katalog aset** dengan pencarian debounce, filter kategori/ruangan/status, pagination, stok tersedia per unit, dan riwayat lengkap.
- **(2.1) Katalog jenis barang terpisah dari unit fisik**: satu entri "Kursi" dengan jumlah 23, sementara setiap kursi memiliki baris unit, lokasi, kondisi, dan kode sendiri.
- **(2.1) Kode inventaris `G1-02-12`** (Gedung G, lantai 1, ruang 02, unit 12), diterbitkan otomatis per ruangan dan divalidasi terhadap lokasi serta keunikan.
- **(2.1) Detail katalog & pencarian unit**: daftar unit per katalog, pencarian kode lengkap/awalan, filter gedung/lantai/ruangan/kondisi/status/tahun, sorting kode, pagination, dan pembatasan cakupan akses.
- **Identitas inventaris**: kode lokasi/instansi, kode barang, nomor register. **(2.1)** kode inventaris per unit (`G1-02-12`) menggantikan ID internal per baris batch.
- **Pengadaan** dengan approval Wakasek; **penghapusan** dengan Berita Acara + approval dan riwayat tetap tersimpan.
- **Mutasi ruangan atomik** yang mencatat riwayat; **(2.1)** unit mendapat kode baru, kode lama disimpan dan dapat ditelusuri.
- **Peminjaman multi-katalog**: katalog terintegrasi di halaman peminjaman akun peminjam, pencarian/filter, kalender ketersediaan, validasi bentrok jadwal di server + transaksi/lock database; katalog duplikat ditolak.
- **Approval berjenjang**: Laboran/K3 level 1 (otomatis untuk barang biasa di laboratorium sendiri); Wakasek level 2 untuk barang bernilai tinggi. Tanda tangan digital opsional.
- **Hapus/arsip katalog aman**: hapus permanen hanya tanpa unit/transaksi; selain itu diarsipkan tanpa menghapus unit atau riwayat.
- **Serah terima per unit dengan batas**: unit berlebih disabled di UI dan ditolak backend; serah terima ganda dan unit di luar transaksi ditolak.
- **Siklus jelas**: Menunggu → Disetujui → **Serah terima** → Dipinjam → Selesai; bisa Ditolak/Dibatalkan.
- **Pengembalian parsial akumulatif**, foto bukti kerusakan, deteksi kehilangan, mutasi jika ruangan berbeda, penutupan selisih oleh Wakasek.
- **QR Code** berisi URL detail, scanner kamera (`html5-qrcode`) + fallback input manual.
- **Label stiker** satuan & massal (PDF), preview, dan re-print; **(2.1)** label diterbitkan per unit.
- **Surat Peminjaman / BAST** PDF per pinjaman dan gabungan kegiatan.
- **Laporan bulanan** PDF + **Excel 5 sheet**, filter sekolah/kategori/ruangan/jurusan, arsip snapshot.
- **Notifikasi in-app** + job terjadwal (jatuh tempo, terlambat, laporan bulanan) dengan retry & deduplikasi.
- **Keamanan**: CSRF, rate limit login, otorisasi route + query + cakupan data, file internal privat, upload tervalidasi, audit log immutable. **(2.1)** cakupan katalog/unit berbasis laboratorium/ruangan.
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
| `ketua.laboran` | `ketua123` | KETUA_LABORAN |
| `laboran.rpl` | `laboran123` | LABORAN (RPL) |
| `laboran.dkv` | `laboran123` | LABORAN (DKV) |
| `laboran.kln` | `laboran123` | LABORAN (KLN) |
| `laboran.tp` | `laboran123` | LABORAN (TP) |
| `laboran.tkp` | `laboran123` | LABORAN (TKP) |
| `guru.budi` | `guru123` | PEMINJAM (GURU) |
| `guru.sari` | `guru123` | PEMINJAM (GURU) |
| `siswa.raka` | `siswa123` | PEMINJAM (SISWA) |
| `siswa.nadia` | `siswa123` | PEMINJAM (SISWA) |
| `staf.tata` | `staf123` | PEMINJAM (STAF) |
| `staf.perpus` | `staf123` | PEMINJAM (STAF) |

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
| [`docs/PROJECT_BRIEF.md`](docs/PROJECT_BRIEF.md) | PRD: latar belakang, peran, katalog/unit, kode inventaris, pencarian/filter, acceptance criteria |
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

**Modul inti + revisi 2.1 (katalog + unit fisik, kode `G1-02-12`)**: IMPLEMENTED & VERIFIED di lingkungan lokal (194 tes, 932 asersi, build frontend, verifikasi browser desktop/mobile dan tema gelap).

**Revisi besar 24 Oktober 2026** (role, seeder, persetujuan otomatis, katalog peminjam, hapus katalog aman, batas penyerahan unit): IMPLEMENTED & VERIFIED.

**Belum dikerjakan**: pemilihan unit fisik oleh peminjam saat pengajuan (unit tetap ditugaskan petugas saat serah terima — lihat `docs/KNOWN_ISSUES.md` butir 10), penghapusan per unit (`asset_unit_id` + `batch_no` — butir 2), penyesuaian seluruh katalog lama ke unit fisik, konfirmasi daftar gedung resmi produksi (butir 5), dan deployment ke server produksi — lihat `docs/DEPLOYMENT.md`.

**Belum dideploy** ke server produksi — lihat `docs/DEPLOYMENT.md`.

# Instalasi & Menjalankan Aplikasi

## 1. Persyaratan

| Komponen        | Versi minimum                                                                                 |
| --------------- | --------------------------------------------------------------------------------------------- |
| PHP             | 8.3 (ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `intl`, `fileinfo`, `sodium`) |
| Composer        | 2.6+                                                                                          |
| Node.js         | 20+ (build asset)                                                                             |
| MySQL / MariaDB | MySQL 8.0+ / MariaDB 10.11+                                                                   |
| Web server      | Apache/Nginx (produksi), `artisan serve` (lokal)                                              |

Redis opsional. Default aplikasi memakai driver `database` untuk cache/session/queue.

## 2. Langkah lokal (Windows/Laragon atau Linux)

```bash
# 1. Masuk ke direktori proyek
cd project_inventaris_v2

# 2. Dependensi
composer install
npm install

# 3. Konfigurasi
cp .env.example .env
php artisan key:generate
```

Atur `.env`:

```env
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventaris_sekolah
DB_USERNAME=root
DB_PASSWORD=
```

Buat database:

```sql
CREATE DATABASE inventaris_sekolah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# 4. Skema + data demo
php artisan migrate --seed

# 5. Storage link (foto barang & logo)
php artisan storage:link

# 6. Build asset
npm run build

# 7. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## 3. Akun demo

| Username        | Password     | Role          |
| --------------- | ------------ | ------------- |
| `wakasek`       | `wakasek123` | SUPER_ADMIN   |
| `admin.sarpras` | `admin123`   | ADMIN_SARPRAS |
| `laboran.tkj`   | `laboran123` | LABORAN (TKJ) |
| `laboran.rpl`   | `laboran123` | LABORAN (RPL) |
| `guru.budi`     | `guru123`    | PEMINJAM      |
| `guru.sari`     | `guru123`    | PEMINJAM      |

> Akun demo **hanya untuk lokal**. Sebelum produksi, ubah password, hapus akun yang tidak dipakai, dan jangan menjalankan `--seed` di database produksi.

## 4. Membuat admin pertama secara aman (tanpa seeder demo)

```bash
php artisan tinker
```

```php
$u = App\Models\User::create([
    'name' => 'Administrator Sekolah',
    'username' => 'admin',
    'password' => Illuminate\Support\Facades\Hash::make('Ganti-Dengan-Password-Kuat'),
    'role' => 'SUPER_ADMIN',
]);
$u->syncRoles(['SUPER_ADMIN']);
```

Jalankan `php artisan migrate --force` (tanpa `--seed`) pada instalasi produksi. Data awal yang diperlukan:

1. Login sebagai admin.
2. Buka **Pengaturan Instansi** — isi nama sekolah, kode lokasi, alamat, kepala sekolah, unggah logo.
3. Buka **Master Data** — tambah jurusan, ruangan, kategori.
4. Baru tambahkan barang melalui **Pengadaan**.

## 5. Worker & scheduler

Notifikasi dan laporan bergantung pada queue dan scheduler:

```bash
# Terminal terpisah — worker
php artisan queue:work --tries=3 --timeout=120

# Jalankan scheduler setiap menit (cron Linux)
* * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
```

Windows Task Scheduler: buat task per menit yang menjalankan
`php C:\path\ke\proyek\artisan schedule:run`.

Uji cepat:

```bash
php artisan schedule:list
php artisan queue:work --stop-when-empty
```

## 6. Menjalankan test

```bash
php artisan optimize:clear   # penting: jangan test saat config ter-cache
php artisan test
```

Test memakai SQLite in-memory dan tidak menyentuh database MySQL.

## 7. Troubleshooting

| Gejala                            | Penyebab & solusi                                                          |
| --------------------------------- | -------------------------------------------------------------------------- |
| `could not find driver`           | Ekstensi `pdo_mysql` belum aktif di `php.ini`.                             |
| Halaman blank / 500               | Periksa `storage/logs/laravel.log`, jalankan `php artisan optimize:clear`. |
| Foto/label tidak tampil           | Belum `php artisan storage:link`, atau `APP_URL` salah.                    |
| PDF error font/logo               | Pastikan ekstensi `gd` aktif dan logo berukuran wajar (< 2 MB).            |
| Notifikasi tidak muncul           | Worker queue belum berjalan; cek `jobs`/`failed_jobs`.                     |
| Pencarian tidak merespons         | Build asset lama; jalankan `npm run build` dan hard-refresh.               |
| Kamera QR tidak aktif             | Browser memerlukan HTTPS atau `localhost`; gunakan input manual.           |
| 419 Page Expired                  | Sesi kedaluwarsa / cookie diblokir; login ulang.                           |
| Test gagal setelah `config:cache` | Jalankan `php artisan optimize:clear` sebelum test.                        |

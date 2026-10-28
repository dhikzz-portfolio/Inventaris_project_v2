# Daftar Dependensi & Lisensi Perangkat Lunak (LICENSES.md)

Seluruh dependensi yang digunakan pada Sistem Manajemen Inventaris & Aset Sekolah adalah perangkat lunak sumber terbuka (Open Source) bebas biaya tanpa lisensi berbayar / komersial.

---

## 1. Dependensi Backend (PHP / Composer)

| Paket                            | Versi | Lisensi      | Deskripsi & Kegunaan                               |
| -------------------------------- | ----- | ------------ | -------------------------------------------------- |
| `php`                            | ^8.3  | PHP License  | Runtime bahasa pemrograman utama                   |
| `laravel/framework`              | ^13.0 | MIT          | Kerangka kerja web MVC inti                        |
| `laravel/breeze`                 | ^2.4  | MIT          | Scaffolding autentikasi (login email)           |
| `livewire/livewire`              | ^4.4  | MIT          | Komponen UI reaktif tanpa SPA                      |
| `spatie/laravel-permission`      | ^8.3  | MIT          | Manajemen role (4 role) & permission               |
| `spatie/laravel-activitylog`     | ^4.12 | MIT          | Audit trail & log aktivitas sensitif               |
| `barryvdh/laravel-dompdf`        | ^3.1  | MIT / LGPL   | Generator PDF (Label stiker, BAST, Laporan)        |
| `maatwebsite/excel`              | ^4.0  | MIT          | Ekspor laporan bulanan ke format spreadsheet Excel |
| `simplesoftwareio/simple-qrcode` | ^4.2  | MIT          | Generator QR Code untuk stiker aset                |
| `laravel/tinker`                 | ^3.0  | MIT          | REPL debugging Laravel                             |
| `fakerphp/faker`                 | ^1.23 | MIT          | Generator dummy data pengujian                     |
| `laravel/pint`                   | ^1.27 | MIT          | Formatter gaya kode PHP PSR-12 / Laravel           |
| `phpunit/phpunit`                | ^12.5 | BSD-3-Clause | Framework unit & feature testing otomatis          |

## 2. Dependensi Frontend (JavaScript / NPM)

| Paket           | Versi | Lisensi    | Deskripsi & Kegunaan                             |
| --------------- | ----- | ---------- | ------------------------------------------------ |
| `alpinejs`      | ^3.14 | MIT        | Kerangka reaktif ringan sisi klien               |
| `tailwindcss`   | ^3.4  | MIT        | Kerangka utilitas styling CSS responsif          |
| `chart.js`      | ^4.4  | MIT        | Visualisasi data & grafik dashboard              |
| `html5-qrcode`  | ^2.3  | Apache-2.0 | Pemindai QR Code via browser kamera mobile       |
| `signature_pad` | ^5.0  | MIT        | Tangkapan tanda tangan digital pada layar sentuh |
| `axios`         | ^1.7  | MIT        | HTTP client untuk panggilan asinkron             |
| `vite`          | ^6.0  | MIT        | Frontend build tool & HMR bundler                |

## 3. Ekstensi & Layanan Pendukung

- **Database**: MariaDB 10.11 / MySQL 8.0 (GPL v2)
- **Cache & Queue**: Redis 7 (BSD-3-Clause / RSAL)
- **Web Server / Container**: Nginx / Alpine Linux (2-clause BSD / MIT)
- **Font & Icon**: Google Material Icons (Apache-2.0)

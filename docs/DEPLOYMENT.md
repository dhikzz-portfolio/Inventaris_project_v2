# Deployment Guide

## 1. Status deployment

**Belum dideploy ke server produksi.** Lingkungan kerja ini tidak memiliki akses/kredensial server. Yang telah disiapkan dan diverifikasi:

- Build frontend produksi (`npm run build`).
- `config:cache`, `route:cache`, `view:cache` berhasil.
- Queue worker diuji (`queue:work --stop-when-empty`, job DONE, `failed_jobs=0`).
- Scheduler menampilkan 4 job aktif.
- Konfigurasi Docker Compose + Dockerfile disiapkan.
- Seluruh tes lulus.

Jangan mengklaim aplikasi online sebelum domain, HTTPS, database produksi, dan worker benar-benar berjalan.

## 2. Checklist sebelum go-live

- [ ] Salin `.env.example` → `.env`, isi kredensial produksi.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://inventaris.sekolah.sch.id`.
- [ ] `php artisan key:generate` (jangan pernah commit `APP_KEY`).
- [ ] Database MySQL/MariaDB dibuat, user aplikasi hanya diberi hak pada DB tersebut.
- [ ] `php artisan migrate --force` (tanpa `--seed` demo).
- [ ] Buat admin pertama dengan password kuat, hapus/ganti akun demo.
- [ ] `php artisan storage:link`.
- [ ] Isi **Pengaturan Instansi** (nama, kode lokasi, logo, kepala sekolah).
- [ ] `npm ci && npm run build`.
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- [ ] Worker queue berjalan sebagai service.
- [ ] Scheduler berjalan setiap menit.
- [ ] HTTPS aktif dan cookie `secure`.
- [ ] Backup database + `storage/app/private` + `storage/app/public` dijadwalkan.
- [ ] Monitoring log dan `failed_jobs`.

## 3. Opsi A — VPS + Nginx + PHP-FPM (disarankan)

### Struktur

```
/var/www/inventaris          # kode aplikasi
  public/                    # document root
  storage/app/private        # dokumen internal (JANGAN diekspos)
  storage/app/public         # foto barang/logo (symlink dari public/storage)
```

### Perintah

```bash
sudo apt install nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-gd php8.3-zip php8.3-intl php8.3-curl unzip git mysql-server
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
git clone <repo> /var/www/inventaris && cd /var/www/inventaris
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env
php artisan migrate --force
php artisan storage:link
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Nginx

```nginx
server {
    listen 80;
    server_name inventaris.sekolah.sch.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name inventaris.sekolah.sch.id;

    ssl_certificate     /etc/letsencrypt/live/inventaris.sekolah.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/inventaris.sekolah.sch.id/privkey.pem;

    root /var/www/inventaris/public;
    index index.php;

    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location ^~ /storage/ { expires 30d; access_log off; }
}
```

Pastikan direktori `storage/app/private` **tidak** memiliki symlink di `public/`.

### Supervisor (queue worker)

`/etc/supervisor/conf.d/inventaris-worker.conf`:

```ini
[program:inventaris-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/inventaris/artisan queue:work --tries=3 --timeout=120 --sleep=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/inventaris/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start inventaris-worker:*
```

### Cron scheduler

```cron
* * * * * cd /var/www/inventaris && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Opsi B — Docker Compose

Prasyarat: Docker + Docker Compose.

```bash
cp .env.example .env
# isi APP_KEY (php artisan key:generate --show) dan kredensial DB
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
```

Layanan: `app` (Nginx+PHP-FPM), `db` (MariaDB 10.11), `redis`, `queue`, `scheduler`.
Semua container berbagi `./storage` sehingga file BAST/laporan yang dibuat worker dapat diunduh lewat app.

Catatan:

- Jangan memakai password default `secret123` di produksi.
- Aplikasi tidak otomatis menjalankan migrasi/seed saat start; jalankan manual.
- Untuk HTTPS, letakkan reverse proxy (Traefik/Nginx/Caddy) di depan port `APP_PORT`.
- `docker compose down -v` akan menghapus volume database — hindari di produksi.

## 5. Backup & pemulihan

```bash
# Harian
mysqldump -u inventaris_user -p --single-transaction inventaris_sekolah \
  | gzip > /backup/db-$(date +%F).sql.gz

tar -czf /backup/storage-$(date +%F).tar.gz \
  /var/www/inventaris/storage/app/private \
  /var/www/inventaris/storage/app/public

# Retensi 30 hari
find /backup -type f -mtime +30 -delete
```

Pemulihan:

```bash
gunzip -c /backup/db-2026-10-20.sql.gz | mysql -u inventaris_user -p inventaris_sekolah
tar -xzf /backup/storage-2026-10-20.tar.gz -C /
php artisan optimize:clear
```

Uji restore minimal sekali sebelum go-live.

## 6. Setelah deploy

```bash
php artisan about
php artisan schedule:list
php artisan queue:monitor default:100
tail -f storage/logs/laravel.log
```

Login, buat satu peminjaman uji, setujui, serah terima, kembalikan, lalu cek laporan dan notifikasi. Hapus data uji jika perlu (jangan menghapus data nyata).

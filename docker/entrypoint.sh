#!/bin/sh
set -e

# Pastikan direktori Nginx dan temp upload/fastcgi tersedia dan dimiliki www-data
mkdir -p /var/lib/nginx/tmp/client_body \
         /var/lib/nginx/tmp/fastcgi \
         /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/scgi \
         /var/lib/nginx/tmp/uwsgi \
         /var/log/nginx

chown -R www-data:www-data /var/lib/nginx /var/log/nginx
chmod -R 775 /var/lib/nginx /var/log/nginx

# Pastikan direktori storage dan cache tersedia
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/app/public/institution \
         /var/www/html/storage/app/public/items \
         /var/www/html/storage/app/private/livewire-tmp \
         /var/www/html/storage/app/private/damage \
         /var/www/html/storage/app/private/signatures \
         /var/www/html/storage/app/private/write-offs \
         /var/www/html/storage/app/private/reports \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Atur ownership dan permission untuk user www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Buat symbolic link public/storage jika belum ada
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --force || true
fi

# Pastikan aset Livewire tersedia di public/vendor/livewire
if [ ! -f /var/www/html/public/vendor/livewire/livewire.min.js ]; then
    php artisan livewire:publish --assets || true
fi

# Jalankan perintah yang dioperasikan ke container
exec "$@"

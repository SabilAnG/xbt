#!/bin/sh
set -e

cd /var/www/html

# Volume yang baru dibuat bisa kosong; pastikan strukturnya ada dan dimiliki
# www-data sebelum php-fpm mulai melayani permintaan.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         public/uploads
chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true

# Cache konfigurasi dibangun di sini, bukan saat build image: nilainya berasal
# dari .env yang baru tersedia saat runtime.
php artisan config:clear >/dev/null 2>&1 || true
php artisan view:clear   >/dev/null 2>&1 || true

php artisan config:cache >/dev/null 2>&1 || echo "config:cache gagal (lanjut tanpa cache)"
php artisan route:cache  >/dev/null 2>&1 || echo "route:cache gagal (lanjut tanpa cache)"
php artisan view:cache   >/dev/null 2>&1 || echo "view:cache gagal (lanjut tanpa cache)"
php artisan event:cache  >/dev/null 2>&1 || true

# Cache milik root setelah artisan berjalan sebagai root — kembalikan.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Migrasi TIDAK dijalankan otomatis. Perubahan skema harus disengaja:
#   docker compose -f compose.prod.yaml exec app php artisan migrate --force

exec "$@"

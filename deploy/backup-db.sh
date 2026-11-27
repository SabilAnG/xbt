#!/usr/bin/env sh
# Backup database produksi, terenkripsi.
#
#   cd /opt/knalpot && sh deploy/backup-db.sh [label]
#
# Hasil: backup/<label>-YYYYmmdd-HHMM.sql.gz.enc (AES-256, kunci = BACKUP_PASSWORD
# di .env). Tanpa password itu file tidak bisa dibuka, jadi kalau folder backup
# ikut bocor/tersalin isinya tetap aman. Simpan BACKUP_PASSWORD juga di tempat
# lain (pengelola password) — hilang password = backup tidak bisa dipulihkan.
#
# Backup lebih dari 14 hari dihapus otomatis. Jadwal harian (crontab -e):
#   30 2 * * * cd /opt/knalpot && sh deploy/backup-db.sh harian >> backup/backup.log 2>&1
set -eu

cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

if [ -z "${BACKUP_PASSWORD:-}" ]; then
    echo "BACKUP_PASSWORD belum diisi di .env — backup dibatalkan (tidak membuat file tanpa enkripsi)." >&2
    exit 1
fi

label="${1:-manual}"
mkdir -p backup
chmod 700 backup
umask 077
file="backup/${label}-$(date +%Y%m%d-%H%M).sql.gz.enc"

docker compose -f compose.prod.yaml exec -T db \
    mysqldump -u root -p"$DB_ROOT_PASSWORD" --single-transaction --routines "$DB_DATABASE" \
    | gzip -9 \
    | openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSWORD -out "$file"

# Pastikan file bisa dibuka lagi dan isinya lengkap sebelum dianggap berhasil.
tables=$(openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSWORD -in "$file" | gunzip | grep -c "CREATE TABLE" || true)
if [ "$tables" -lt 10 ]; then
    echo "Backup $file tidak valid (hanya $tables tabel)." >&2
    exit 1
fi

find backup -name '*.sql.gz.enc' -mtime +14 -delete
# Backup lama tanpa enkripsi dari versi sebelumnya juga dibuang.
find backup -name '*.sql' -mtime +1 -delete

echo "OK $file ($tables tabel)"

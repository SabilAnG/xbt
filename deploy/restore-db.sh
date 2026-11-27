#!/usr/bin/env sh
# Pulihkan database produksi dari backup terenkripsi (hasil deploy/backup-db.sh).
#
#   cd /opt/knalpot && sh deploy/restore-db.sh backup/harian-20261201-0230.sql.gz.enc
#
# MENIMPA isi database saat ini. Sebelum menimpa, kondisi sekarang dibackup
# dulu dengan label "pra-restore".
set -eu

cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

file="${1:?Pakai: sh deploy/restore-db.sh backup/<file>.sql.gz.enc}"
[ -f "$file" ] || { echo "File $file tidak ada." >&2; exit 1; }

printf 'Timpa database %s dengan %s? Ketik YA: ' "$DB_DATABASE" "$file"
read -r answer
[ "$answer" = "YA" ] || { echo "Dibatalkan."; exit 1; }

sh deploy/backup-db.sh pra-restore

openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSWORD -in "$file" \
    | gunzip \
    | docker compose -f compose.prod.yaml exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"

echo "Selesai dipulihkan dari $file"

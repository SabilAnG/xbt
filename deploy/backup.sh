#!/usr/bin/env bash
#
# Backup database pusat BERIKUT seluruh database partner.
#
#   ./backup.sh                  -> backup/pusat-<stempel>.sql, backup/partner-<slug>-<stempel>.sql
#   ./backup.sh pra-deploy       -> awalan berkasnya diganti
#
# Dijalankan di server, dari /opt/knalpot.
#
# Ada karena skrip lama hanya mengambil $DB_DATABASE. Setiap partner menyimpan
# datanya di database sendiri, jadi backup yang hanya menyentuh database pusat
# meninggalkan seluruh toko partner tanpa titik pulih — dan itu baru ketahuan
# saat ada yang perlu dipulihkan.

set -euo pipefail

cd "$(dirname "$0")/.."

AWALAN="${1:-backup}"
STEMPEL="$(date +%Y%m%d-%H%M)"
DC="docker compose -f compose.prod.yaml"

set -a && . ./.env && set +a
mkdir -p backup

dump() {
    local db="$1" keluar="$2"

    $DC exec -T db mysqldump -u root -p"$DB_ROOT_PASSWORD" \
        --single-transaction --routines "$db" > "$keluar"

    # Berkas yang ada tapi terpotong lebih berbahaya daripada tidak ada sama
    # sekali: ia terlihat seperti titik pulih sampai dicoba dipakai.
    if ! tail -1 "$keluar" | grep -q "Dump completed"; then
        echo "GAGAL: $db — dump tidak selesai, berkas dibuang." >&2
        rm -f "$keluar"
        return 1
    fi

    echo "  $(basename "$keluar")  $(grep -c 'CREATE TABLE' "$keluar") tabel  $(du -h "$keluar" | cut -f1)"
}

echo "Database pusat:"
dump "$DB_DATABASE" "backup/${AWALAN}-pusat-${STEMPEL}.sql"

echo "Database partner:"

# Slug dibaca dari tabel tenants supaya nama berkasnya bisa dikenali orang,
# bukan deretan UUID.
PARTNER=$($DC exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" -N -B "$DB_DATABASE" \
    -e "SELECT id, slug FROM tenants;" 2>/dev/null || true)

if [[ -z "$PARTNER" ]]; then
    echo "  (belum ada partner)"
    exit 0
fi

gagal=0
while IFS=$'\t' read -r id slug; do
    [[ -z "$id" ]] && continue
    dump "tenant${id}" "backup/${AWALAN}-partner-${slug}-${STEMPEL}.sql" || gagal=1
done <<< "$PARTNER"

exit "$gagal"

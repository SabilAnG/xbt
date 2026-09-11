#!/usr/bin/env bash
#
# Menerbitkan sertifikat HTTPS untuk satu toko partner.
#
#   ./partner-ssl.sh knalpot-jaya
#
# Dijalankan di server, sekali per partner baru, setelah partner disetujui di
# panel. Memakai tantangan HTTP-01 lewat nginx — tidak perlu kredensial DNS,
# dan perpanjangannya ikut jadwal certbot yang sudah berjalan.
#
# Syaratnya cuma satu: DNS wildcard *.shop.garagehs-speed.com sudah mengarah ke
# server ini, sehingga alamatnya bisa dijangkau Let's Encrypt saat memeriksa.

set -euo pipefail

INDUK="${PARTNER_DOMAIN:-shop.garagehs-speed.com}"
SLUG="${1:-}"

if [[ -z "$SLUG" ]]; then
    echo "Pakai: $0 <slug-partner>" >&2
    echo "Contoh: $0 knalpot-jaya" >&2
    exit 1
fi

ALAMAT="${SLUG}.${INDUK}"

# Diperiksa dulu supaya kegagalannya menjelaskan diri, bukan berupa galat
# certbot yang panjang dan tidak menyebut sebabnya.
if ! getent hosts "$ALAMAT" >/dev/null 2>&1; then
    echo "DNS untuk ${ALAMAT} belum menjawab." >&2
    echo "Pasang record wildcard *.${INDUK} di pengelola DNS lebih dulu." >&2
    exit 1
fi

echo "Menerbitkan sertifikat untuk ${ALAMAT} ..."

certbot --nginx -d "$ALAMAT" \
    --non-interactive --agree-tos --keep-until-expiring \
    --redirect

nginx -t && systemctl reload nginx

echo "Selesai. Toko partner siap di https://${ALAMAT}"

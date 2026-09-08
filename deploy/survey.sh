#!/usr/bin/env bash
# Survei VPS sebelum deploy. HANYA MEMBACA — tidak memasang, mengubah, atau
# menghentikan apa pun. Tujuannya memastikan deploy tidak menabrak situs lain
# yang sudah berjalan di server.
#
#   ssh -i ~/.ssh/knalpot_vps root@147.93.81.184 'bash -s' < deploy/survey.sh

echo "===== SISTEM ====="
hostnamectl 2>/dev/null | head -5 || uname -a
echo "uptime: $(uptime -p 2>/dev/null)"

echo
echo "===== SUMBER DAYA ====="
free -h 2>/dev/null | head -2
df -h / 2>/dev/null | tail -1
echo "cpu: $(nproc 2>/dev/null) core"

echo
echo "===== DOCKER ====="
if command -v docker >/dev/null 2>&1; then
    docker --version
    docker compose version 2>/dev/null || echo "compose plugin: TIDAK ADA"
    echo "--- container berjalan ---"
    docker ps --format '  {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>/dev/null
else
    echo "Docker BELUM terpasang"
fi

echo
echo "===== WEB SERVER / PROXY DI HOST ====="
for s in nginx apache2 caddy traefik; do
    if systemctl is-active --quiet "$s" 2>/dev/null; then
        echo "  $s: AKTIF"
    fi
done
command -v nginx  >/dev/null 2>&1 && echo "  nginx terpasang: $(nginx -v 2>&1)"
command -v caddy  >/dev/null 2>&1 && echo "  caddy terpasang: $(caddy version 2>/dev/null)"
command -v certbot >/dev/null 2>&1 && echo "  certbot terpasang: $(certbot --version 2>&1)"

echo
echo "===== PORT YANG SUDAH DIPAKAI ====="
(ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null) | awk 'NR==1 || /LISTEN/' | head -25

echo
echo "===== VHOST NGINX YANG ADA ====="
ls -1 /etc/nginx/sites-enabled/ 2>/dev/null || echo "  (tidak ada sites-enabled)"
ls -1 /etc/nginx/conf.d/*.conf 2>/dev/null | head -10

echo
echo "===== SERTIFIKAT SSL ====="
ls -1 /etc/letsencrypt/live/ 2>/dev/null || echo "  (belum ada sertifikat Let's Encrypt)"

echo
echo "===== DNS domain menunjuk ke mana ====="
getent hosts shop.garagehs-speed.com 2>/dev/null || echo "  shop.garagehs-speed.com: TIDAK RESOLVE dari server ini"
echo "  IP publik server: $(curl -s --max-time 5 ifconfig.me 2>/dev/null)"

echo
echo "===== SELESAI (tidak ada yang diubah) ====="

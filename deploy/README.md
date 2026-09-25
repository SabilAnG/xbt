# Deploy ke VPS

Target: `shop.garagehs-speed.com` di `147.93.81.184`.

## 0. Akses

Public key deploy sudah dibuat di mesin lokal (`~/.ssh/knalpot_vps`). Pasang di
server sekali saja:

```bash
ssh root@147.93.81.184 "mkdir -p ~/.ssh && echo '<isi knalpot_vps.pub>' >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"
```

Uji:

```bash
ssh -i ~/.ssh/knalpot_vps root@147.93.81.184 'echo OK'
```

## 1. Survei dulu, jangan langsung pasang

VPS ini kemungkinan sudah menjalankan situs lain. Jalankan survei read-only:

```bash
ssh -i ~/.ssh/knalpot_vps root@147.93.81.184 'bash -s' < deploy/survey.sh
```

Yang perlu dipastikan dari hasilnya:

- Docker + plugin compose tersedia
- Port 80/443 dipegang siapa (nginx host? caddy? traefik?)
- `WEB_PORT` yang dipilih belum dipakai
- DNS `shop.garagehs-speed.com` sudah mengarah ke IP server — kalau belum,
  penerbitan sertifikat akan gagal

## 2. Kirim source

```bash
rsync -az --delete \
  --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
  --exclude '.env' --exclude 'storage/logs/*' \
  -e 'ssh -i ~/.ssh/knalpot_vps' \
  ./ root@147.93.81.184:/opt/knalpot/
```

`vendor/` sengaja tidak dikirim — dibangun di server oleh stage `vendor`.

## 3. Konfigurasi

```bash
cd /opt/knalpot
cp .env.production.example .env
nano .env          # isi semua yang bertanda GANTI
```

Wajib diganti: `DB_PASSWORD`, `DB_ROOT_PASSWORD`, dan `APP_URL`.

## 3b. Kredensial composer untuk paket privat

`ihc/dhl-gate` diambil dari repo GitHub **privat**, jadi composer di dalam image
perlu token. Tanpa ini `build` berhenti di `composer install` dengan
"Failed to clone ... could not read Username".

Buat `auth.json` di server sekali saja, dengan PAT GitHub ber-scope `repo`
(read-only sudah cukup):

```bash
mkdir -p ~/.config/composer
cat > ~/.config/composer/auth.json <<'JSON'
{ "github-oauth": { "github.com": "ghp_TOKEN_ANDA" } }
JSON
chmod 600 ~/.config/composer/auth.json
```

Token diteruskan sebagai **build secret**, bukan ARG atau ENV — kalau lewat ARG
ia tertinggal di layer image dan terbaca siapa pun lewat `docker history`.

## 4. Build & jalankan

```bash
docker compose -f compose.prod.yaml build \
    --secret id=composer_auth,src=$HOME/.config/composer/auth.json app web
docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml run --rm app php artisan key:generate --force
docker compose -f compose.prod.yaml restart app
```

## 5. Migrasi & data awal

Migrasi sengaja tidak otomatis — perubahan skema harus disengaja.

```bash
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=SiteContentSeeder --force
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=ProductSeeder --force
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=InventorySeeder --force
```

Buat akun admin dengan password sungguhan:

```bash
docker compose -f compose.prod.yaml exec app php artisan make:filament-user
```

## 6. Reverse proxy + TLS

Container hanya mendengar di `127.0.0.1:${WEB_PORT}`. Sambungkan domain lewat
proxy host. Contoh vhost nginx:

```nginx
server {
    server_name shop.garagehs-speed.com;

    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 64M;
    }
}
```

Lalu terbitkan sertifikat:

```bash
certbot --nginx -d shop.garagehs-speed.com
```

## 7. Verifikasi

```bash
curl -I https://shop.garagehs-speed.com
curl -I https://shop.garagehs-speed.com/admin/login
```

Periksa juga bahwa situs lain di VPS masih hidup.

## Redeploy berikutnya

`rsync` tidak selalu ada di mesin Windows. Cara yang sudah terbukti dipakai:

Tulis tiap perintah dalam satu baris — pemutus baris `\` gampang hilang saat
disalin lewat shell lain.

```bash
# 0. singkatan supaya perintah di bawah tidak kepanjangan
SRV='ssh -i ~/.ssh/knalpot_vps root@147.93.81.184'
DC='docker compose -f compose.prod.yaml'

# 1. backup dulu, selalu
$SRV "cd /opt/knalpot && mkdir -p backup && set -a && . ./.env && set +a && $DC exec -T db mysqldump -u root -p\"\$DB_ROOT_PASSWORD\" --single-transaction --routines \"\$DB_DATABASE\" > backup/pra-deploy-\$(date +%Y%m%d-%H%M).sql"
$SRV 'cd /opt/knalpot && grep -c "CREATE TABLE" backup/*.sql | tail -1'

# 2. kirim source lewat tar
tar -czf - --exclude=.git --exclude=node_modules --exclude=vendor --exclude=.env --exclude='storage/logs/*' --exclude=backup --exclude='public/uploads' --exclude='storage/framework/cache/*' --exclude='storage/framework/views/*' . | $SRV 'cd /opt/knalpot && tar -xzf -'

# 3. bangun ulang, jalankan, migrasi
$SRV "cd /opt/knalpot && $DC build --secret id=composer_auth,src=\$HOME/.config/composer/auth.json app web && $DC up -d && $DC exec -T app php artisan migrate --force"
```

Source tidak di-bind mount ke container produksi — ia disalin ke dalam image.
Jadi setiap perubahan kode HARUS diikuti `build`, dan skrip sekali-jalan perlu
`docker compose -f compose.prod.yaml cp <berkas> app:/var/www/html/<berkas>`
kalau tidak mau menunggu build.

### Awas: SKU dan slug yang bentrok saat seeding

`ProduksiSeeder` mencocokkan bahan lewat SKU dan komponen biaya lewat slug, dan
sengaja TIDAK menimpa baris yang sudah ada — supaya harga dan tarif yang sudah
disesuaikan tidak hilang. Efek sampingnya: kalau kode yang sama sudah dipakai
barang lain, seeder memakai baris lama itu dan formula jadi merujuk bahan yang
salah.

Itu pernah terjadi di server ini: data demo lama memakai SKU `PLAT-12` untuk
plat tanpa dimensi, sehingga HPP terbaca Rp6,2 miliar per knalpot. Sekarang
seeder memperingatkan bila menemukan bentrokan seperti itu. Untuk membereskan
data yang telanjur salah:

```bash
docker compose -f compose.prod.yaml cp deploy/perbaiki-tabrakan-sku.php app:/var/www/html/deploy/perbaiki-tabrakan-sku.php
docker compose -f compose.prod.yaml exec app php deploy/perbaiki-tabrakan-sku.php
```

Skrip itu hanya mengganti kode baris lama dan mengarahkan ulang baris formula —
tidak menghapus apa pun — lalu memeriksa sendiri apakah HPP-nya sudah masuk akal.

### Data awal modul produksi

```bash
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=ProduksiSeeder --force
```

Upload pengguna aman: `public/uploads` dan `storage` ada di named volume, tidak
ikut tertimpa image baru.

## Toko partner

Partner mendaftar lewat tombol "Jadi Partner" di situs, lalu admin menyetujuinya
di menu Partner. Persetujuan itu yang membuat database dan akun adminnya.

**Tidak ada satu pun langkah di server tiap ada partner baru.** Toko hidup di
path, bukan subdomain:

    https://shop.garagehs-speed.com/toko/knalpot-jaya          toko
    https://shop.garagehs-speed.com/admin                      panel

Panelnya alamat yang sama untuk semua orang. Email yang menentukan: kalau email
itu milik seorang partner, database dipindahkan ke miliknya lebih dulu, baru
sandinya diperiksa — di tabel users milik partner itu sendiri.

### Kenapa path, bukan subdomain

Subdomain menuntut tiga hal yang semuanya di luar aplikasi: record DNS wildcard,
vhost nginx, dan sertifikat per partner. Yang ketiga tidak bisa diotomatiskan
tanpa menaruh kredensial API DNS di server — satu kunci yang bisa menulis ulang
seluruh DNS domain, di mesin yang berbagi dengan enam produksi lain.

Path menghapus ketiganya. Sertifikat `shop.garagehs-speed.com` yang sudah ada
melindungi semua toko partner sekaligus.

Berkas `nginx-partner.conf` dan `partner-ssl.sh` ditinggal di repo kalau suatu
saat subdomain diperlukan; kodenya masih mengenali partner dari subdomain juga.

### Yang perlu disiapkan — sekali saja

**1. Hak MySQL.** Database partner dibuat aplikasi saat menyetujui, jadi user
aplikasinya perlu hak di database berawalan `tenant`. Bukan hak global — kalau
kredensialnya bocor, ia tetap tidak bisa menyentuh produksi tetangga.

```bash
ssh root@147.93.81.184 'cd /opt/knalpot && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON \`tenant%\`.* TO \"$DB_USERNAME\"@\"%\"; FLUSH PRIVILEGES;"'
```

**2. Isian `.env`.**

```
SESSION_CONNECTION=mysql
ADMIN_WHATSAPP=62895337161221
```

`SESSION_CONNECTION` menahan sesi di database pusat supaya tidak ikut berpindah.
Tanpa itu sesi harus dibaca sebelum kita tahu partner mana yang dituju — padahal
justru sesi yang menyimpan jawabannya.

`ADMIN_WHATSAPP` adalah tujuan tombol Langganan di halaman partner yang masa
pakainya habis. Sengaja dari `.env`, bukan dari menu Site settings: saat toko
partner yang sedang dibuka, Site settings berisi nomor PARTNER — dan tombol itu
harus menghubungi admin Hypersonic, bukan partner yang sedang menunggak.

## Catatan

- **Jangan pakai `.env` dev di server.** `APP_DEBUG=true` membocorkan isi
  konfigurasi dan stack trace ke publik.
- Wrapper `art` hanya untuk dev. Di image produksi seluruh source sudah dimiliki
  `www-data`, jadi tidak ada masalah kepemilikan.
- MySQL tidak dipublikasikan ke internet. Untuk mengaksesnya, gunakan tunnel:
  `ssh -L 3307:127.0.0.1:3306 ...` lalu `docker compose exec db mysql ...`.

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

## 4. Build & jalankan

```bash
docker compose -f compose.prod.yaml up -d --build
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
$SRV "cd /opt/knalpot && $DC build app web && $DC up -d && $DC exec -T app php artisan migrate --force"
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

## Toko partner (subdomain)

Partner mendaftar lewat tombol "Jadi Partner" di situs, lalu admin menyetujuinya
di menu Partner. Persetujuan itu yang membuat database, akun admin, dan
subdomainnya — sebelum disetujui tidak ada apa pun yang dibuat di server.

### Kenapa di bawah `shop.`, bukan langsung di domain utama

`garagehs-speed.com` **tidak ada di VPS ini** — ia menunjuk `2.57.91.91`, server
lain (Hostinger) yang juga memegang emailnya. Hanya `shop.garagehs-speed.com`
yang mengarah ke `147.93.81.184`.

Memasang wildcard `*.garagehs-speed.com` ke VPS ini akan menangkap setiap
subdomain yang belum punya record sendiri di sana, termasuk yang dipakai webmail.
Karena itu toko partner ditaruh di bawah `shop.`, yang seluruhnya milik VPS ini:

    knalpot-jaya.shop.garagehs-speed.com

Kalau nanti mau yang lebih pendek (`knalpot-jaya.garagehs-speed.com`), pastikan
dulu seluruh subdomain milik Hostinger — `mail`, `webmail`, `autodiscover`, `ftp`,
`cpanel` — sudah punya record A/CNAME sendiri di sana. Record eksplisit menang
atas wildcard, jadi yang sudah terdaftar aman. Sesudah itu ubah satu baris:

    PARTNER_DOMAIN=garagehs-speed.com

### 1. DNS di Hostinger — sekali saja

hPanel → **Domains** → `garagehs-speed.com` → **DNS / Nameservers** → *Manage DNS
records*, lalu tambah:

| Type | Name         | Points to        | TTL   |
| ---- | ------------ | ---------------- | ----- |
| A    | `*.shop`     | `147.93.81.184`  | 14400 |

Simpan, lalu tunggu sebentar dan uji dari mesin mana pun:

```bash
dig +short apa-saja.shop.garagehs-speed.com A     # harus menjawab 147.93.81.184
```

Record `shop` yang sudah ada jangan disentuh — itu situs induknya.

### 2. Vhost nginx — sekali saja

```bash
scp deploy/nginx-partner.conf root@147.93.81.184:/etc/nginx/sites-available/knalpot-partner
ssh root@147.93.81.184 'ln -sf /etc/nginx/sites-available/knalpot-partner /etc/nginx/sites-enabled/ && nginx -t && systemctl reload nginx'
```

Satu vhost melayani semua partner; aplikasi yang mengenali siapa yang dituju dari
Host header. Subdomain yang tidak terdaftar dibalas 404 oleh aplikasi.

### 3. Sertifikat — sekali per partner baru

```bash
ssh root@147.93.81.184 'partner-ssl knalpot-jaya'
```

Memakai tantangan HTTP-01 lewat nginx, jadi **tidak perlu kredensial API DNS**,
dan perpanjangannya ikut jadwal certbot yang sudah berjalan. Syaratnya cuma DNS
wildcard di langkah 1 sudah menjawab.

Sertifikat *wildcard* sengaja tidak dipakai: ia mewajibkan tantangan DNS-01,
yang berarti menaruh kredensial API Hostinger di server — satu kunci yang bisa
mengubah seluruh DNS domain, disimpan di mesin yang berbagi dengan enam
produksi lain. Sertifikat per subdomain menghindari itu dengan harga satu
perintah tiap ada partner baru.

Batas Let's Encrypt: 50 sertifikat per minggu untuk satu domain terdaftar. Kalau
partner bertambah lebih cepat dari itu, barulah wildcard sepadan dengan risikonya.

### 4. Hak MySQL — sekali saja

Database partner dibuat aplikasi saat menyetujui, jadi user aplikasinya perlu hak
di database berawalan `tenant`. Bukan hak global — kalau kredensialnya bocor, ia
tetap tidak bisa menyentuh produksi tetangga di VPS ini.

```bash
ssh root@147.93.81.184 'cd /opt/knalpot && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON \`tenant%\`.* TO \"$DB_USERNAME\"@\"%\"; FLUSH PRIVILEGES;"'
```

### 5. Isian `.env`

```
PARTNER_DOMAIN=shop.garagehs-speed.com
ADMIN_WHATSAPP=62895337161221
```

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

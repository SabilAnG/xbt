# Hypersonic Speed Tech — Laravel rebuild

Laravel 13 + Filament v5, running entirely in Docker. The public pages are a
port of the current hypersonic.id front-end.

## Stack

| Piece    | Version                         |
| -------- | ------------------------------- |
| PHP      | 8.4 (fpm)                       |
| Laravel  | 13.30                           |
| Filament | 5.7 (admin panel)               |
| MySQL    | 8.4                             |
| Mailpit  | latest (catches outgoing mail)  |

## Ports

Chosen to avoid the services already running on this machine (3306, 8080, 5173
were taken).

| URL                       | What                     |
| ------------------------- | ------------------------ |
| http://localhost:8000     | the site                 |
| http://localhost:8000/admin | Filament admin         |
| http://localhost:8025     | Mailpit inbox            |
| `127.0.0.1:3307`          | MySQL (user/pass `knalpot` / `secret`) |

Admin login: `admin@hypersonic.id` / `password` — **change this before the site
goes anywhere public.**

## Running it

```bash
docker compose up -d          # start site + db + mailpit
docker compose down           # stop
docker compose logs -f app    # tail php-fpm
```

Artisan and Composer run inside the container:

```bash
docker compose exec app php artisan migrate
docker compose exec app composer require some/package
```

Vite (only needed if you start editing `resources/js` or `resources/css`):

```bash
docker compose --profile dev up node
```

## Why vendor/ is not on disk

A Windows bind mount writes about **35 files/second**; a named Docker volume
does **~2100/s**. `composer install` hit Composer's 300 s process timeout while
unzipping into the bind mount, so `vendor/` and `node_modules/` live in named
volumes instead (see `compose.yaml`).

The trade-off: those folders are invisible to Windows, so an IDE cannot index
them for autocomplete. To get a readable copy on the host:

```bash
docker compose cp app:/var/www/html/vendor ./vendor
```

Delete that copy before running Composer again — it is only a snapshot.

`storage/` and `bootstrap/cache` stay on the bind mount so logs are readable.
The mount arrives owned by `root` while php-fpm's workers run as `www-data`, so
`docker/php/entrypoint.sh` chowns both trees at boot. Ownership, not just
permissions: Laravel's view compiler calls `touch()` with an explicit mtime, and
setting the mtime of a file you do not own fails with `EPERM` even at mode 777.

## Why code changes need a restart

The same slow bind mount makes page loads expensive: PHP re-reads and re-checks
thousands of files per request across it. Measured inside the container, the
bind mount walks about **125 files/second** where the VM's own filesystem does
**~16,000/s**.

Two settings in `docker/php/php.ini` cut most of that cost:

- `opcache.max_accelerated_files = 30000`. The default 10,000 is smaller than
  `vendor/` alone (17,016 PHP files), so opcache filled up and evicted itself on
  a loop, re-reading over the slow mount every time.
- `opcache.validate_timestamps = 0`. PHP stops stat-ing every file on every
  request — the single most expensive thing it did.

Together with cached config, routes, events and views this took the front page
from ~12 s to a median of **5.9 s** (8 samples, 5.0–9.1 s) and `/admin` from
~30 s to a median of **11.2 s** (4 samples, 10.4–15.0 s). Roughly 2× and 3×.
Still slow — the bind mount is the ceiling. Moving the code into a WSL2
filesystem is the fix that removes it.

**The cost: edited PHP is not picked up until the container restarts.**

```bash
docker compose restart app      # after editing any .php file
docker compose exec app art view:cache   # after editing any .blade.php
```

Set `opcache.validate_timestamps = 1` and rebuild (`docker compose build app`)
to go back to edit-and-refresh while working on something churn-heavy.

### The admin panel theme is compiled

`resources/css/filament/admin/theme.css` is a real Filament theme built by Vite,
registered with `->viteTheme(...)`. The panel no longer loads the published
`public/css/filament/` stylesheet at all — that one file is the whole panel.

**Editing it changes nothing until you build:**

```bash
docker compose --profile dev run --rm --no-deps node npm run build
```

The build needs `vendor/` — the theme imports Filament's own CSS and Tailwind
scans Filament's classes — so the `node` service mounts the same `vendor` volume
as `app`. The copy of `vendor/` on the Windows side is only a snapshot and does
not contain it.

`public/build` is gitignored, so **production builds it inside the image**: the
`assets` stage in `docker/php/Dockerfile.prod` runs `npm ci && npm run build` and
its output is copied into both the runtime and the nginx stages. Without that
stage Filament cannot find the Vite manifest and the whole panel answers 500 —
not merely unstyled. `TemaPanelTest` keeps that stage honest.

### Clear the config cache before testing

`bootstrap/cache/config.php` bakes in the values from `.env`, which means the
`DB_CONNECTION=sqlite` override in `phpunit.xml` is ignored while it exists —
the suite would run `RefreshDatabase` against the **real MySQL database** and
wipe it. Always test through the Composer script, which clears it first:

```bash
docker compose exec app art composer test
docker compose exec app art config:cache   # put it back afterwards
```

## Layout of the port

```
resources/views/
├── layouts/app.blade.php      shared <head>, wrapper and script tags
├── partials/header.blade.php  nav (identical on every page)
├── partials/footer.blade.php
└── pages/                     one view per route
    ├── products.blade.php     listing, loops over the products table
    └── products/show.blade.php  detail page, one view for every product
public/assets/                 CSS, JS, icon fonts and imagery
public/storage/                product photos
```

The header and footer were byte-identical across all 13 source pages, so they
are single partials. Per-page `<style>` blocks ride on `@push('head')` — the
tracking page carries 9 KB of its own CSS and each product page 1.8 KB.

## Products

Products live in the database and are edited at `/admin/products`
(name, slug, fitment line, price, description, ordering, gallery).

```bash
docker compose exec app php artisan db:seed --class=ProductSeeder
```

Re-seeding is safe — rows are matched on `slug` and updated in place.
`database/data/products.json` holds the catalogue captured from the live site.

Image paths are stored relative to `public/`, which is why `config/filesystems.php`
defines a `site` disk rooted there: the photos carried over from the old site
(`storage/<id>/conversions/...`) and anything uploaded through Filament both
resolve through plain `asset()`.

Two things worth knowing before editing a product:

- Saving a description through the rich editor rewrites its HTML in the editor's
  own format, so it will no longer match the imported markup byte for byte.
- `Product` deliberately has no `getRouteKeyName()` override. Setting it to
  `slug` also changes how Filament resolves records and breaks
  `/admin/products/{id}/edit`; the public route binds the slug explicitly.

## Editing the site

The admin panel is grouped into **Website** and **Shop**.

| Page | What it controls |
| ---- | ---------------- |
| Website → Site settings | Website name, tagline, logo, favicon, contact email/phone, the four WhatsApp numbers, social links |
| Website → Page content | 156 text and image blocks, grouped by page and filterable |
| Shop → Products | Catalogue, gallery, pricing, ordering |
| Shop → Orders | Orders, shipments and the tracking timeline |

```bash
docker compose exec app php artisan db:seed --class=SiteContentSeeder
```

Re-seeding never overwrites edited copy — an existing block keeps its value and
only its label, page and type are refreshed.

### How editable content works

Templates call `content('home.text.4', 'the original wording')`. The second
argument is the text the page shipped with, so nothing breaks if a block is
missing, and the site renders identically before anyone touches it. Images work
the same way through `content_image()`, and paths stay relative to `public/`.

Two details worth knowing:

- Blocks whose text contains `&`, quotes or angle brackets are typed `html` and
  printed **unescaped** — otherwise Blade would turn `&` into `&amp;` and the
  markup would stop matching the live site. Ten blocks are in that category.
  HTML typed into those fields renders as markup.
- Coverage is 99% of simple text elements (125 of 126). Text that sits inside an
  element with nested markup — a paragraph broken by `<br>` or containing a link
  — is still literal in the Blade file and is not exposed in the admin.

## Order tracking

`/tracking` posts to `TrackingController@track`, which answers from the `orders`
table. Responses keep the shape the page's JavaScript expects:

```
{ status, message, data: { order_ref_number, shipments: [
    { provider, tracking_number, status, status_description,
      status_updated_at, last_checked, events: [...] } ] } }
```

Events come back newest-first, and the progress bar fills at five events. There
is no courier API behind this — shipments and their events are entered by hand
in Shop → Orders.

## Performance

`docker/php/make-webp.php` writes a `.webp` sibling next to every large image
(`header1.png` → `header1.png.webp`) and nginx serves it to browsers that
advertise WebP support, so the HTML keeps pointing at the original file:

```bash
docker compose exec app php docker/php/make-webp.php 150 82
```

That took the images from **62.2 MB to 3.9 MB (-94%)** — the hero slider alone
dropped from 33 MB to about 2 MB, which is what made the opening loader linger.
Browsers without WebP still get the original PNG/JPEG. Re-run the command after
adding large images; it skips anything already converted.

Static assets are served with `Cache-Control: public, max-age=2592000` and CSS/JS
are gzipped, matching what the live site does through Cloudflare.

## Inventory (Operasional / Aset / Master Data)

```bash
docker compose exec app php artisan db:seed --class=InventorySeeder
```

**Master Data** seeds the starting lists: kategori barang (full set, silincer,
leheran, sparepart), jenis barang (racing, standar, standar racing), brand and
type motor, plus jenis pengeluaran (Jasa, Barang, Operasional, Gaji) with their
categories — "Bayar Las" sits under "Jasa", exactly as asked.

**Operasional** holds Pembelian, Penjualan, Stok Barang and Pengeluaran.
**Aset** holds Dompet, Stok Opname and Rekap & Laporan.

### The one rule that makes the numbers trustworthy

Stock and cash are never typed in. They are derived from two ledgers —
`stock_movements` and `wallet_transactions` — and `items.stock` /
`wallets.current_balance` are only caches of those. Everything that writes to
those ledgers goes through `App\Services\PostingService`, so there is a single
place to audit.

A document is a `draft` until it is posted, and a draft touches nothing:

| Posting | Efek |
| ------- | ---- |
| Pembelian | stok bertambah, kas berkurang, harga pokok barang diperbarui |
| Penjualan | stok berkurang, kas bertambah, modal dibekukan ke nota |
| Pengeluaran | kas berkurang |
| Stok opname | hanya baris yang selisih yang mengoreksi stok |

Selling more than you hold is refused before anything is written, so a nota can
never be half-posted. "Batalkan" deletes that document's ledger rows and
recalculates from scratch rather than writing reversing entries — the result
stays correct even when later documents have already been posted.

Harga pokok memakai **biaya pembelian terakhir** (last cost), bukan rata-rata
bergerak. Saat penjualan dibukukan, nilainya dibekukan ke `sale_items.unit_cost`
supaya laba historis tidak berubah ketika harga beli naik di kemudian hari.

### Rekap & Laporan

Filter by date range and document type, then **Cetak laporan** (browser print,
with a stylesheet that drops the navigation) or **Export CSV** (UTF-8 with BOM so
Excel opens it cleanly). Only posted documents are counted, so the report always
matches what actually moved stock and cash. It shows pembelian, penjualan and
pengeluaran with subtotals, plus laba kotor and laba bersih.

## Fidelity check

`/` and the twelve other public pages render byte-identical to hypersonic.id
once per-request noise (CSRF token, Cloudflare's email obfuscation, absolute vs
root-relative URLs) is normalised away, and all 85 static assets match the live
site by MD5.

## Still to do

- **Courier API** — tracking data is entered by hand; no live carrier feed.
- **Sales WhatsApp numbers in `config/site.php` are placeholders**
  (`6281234567890` and friends) — those came from the live markup as-is.
- **The originals are still 10–13 MB each.** WebP siblings cover every browser
  worth counting, but the PNGs remain on disk as the fallback and in git.
  Re-exporting the sources at sane dimensions would shrink the repo itself.
- **Copy inside mixed-markup elements is not editable** — see the coverage note
  under "How editable content works".
- **Three workshop images 404 on the live site** (`storage/workshop1..3.jpg`), so
  they could not be mirrored.

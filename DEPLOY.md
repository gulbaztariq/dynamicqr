# Deploying Dynamic QR

This package is ready to upload. PHP dependencies (`vendor/`) and the compiled
CSS/JS (`public/build/`) are already included, so **you do not need Composer or
Node on the server**.

You do need shell access once (cPanel "Terminal", SSH, or your host's PHP CLI)
to generate the app key and create the database tables.

**Requirements:** PHP 8.3+ with the `pdo_mysql`, `mbstring`, `openssl`, `gd`,
`zip`, `xml`, `curl` and `fileinfo` extensions (all standard on shared hosting).

---

## 1. Upload

Unzip the package on the server. Where it goes depends on your setup.

### If you can set the document root (VPS, or cPanel addon domain)

Put the whole folder outside the web root and point the domain's document root
at the `public/` folder inside it. This is the correct and safest layout.

### If you cannot change the document root (typical cPanel main domain)

Upload the whole folder to something like `/home/youruser/dynamicqr`, then move
**only the contents of `public/`** into `public_html/`, and edit
`public_html/index.php` — change the two `__DIR__.'/../'` paths to point at
where you put the app:

```php
require __DIR__.'/../dynamicqr/vendor/autoload.php';
$app = require_once __DIR__.'/../dynamicqr/bootstrap/app.php';
```

Never upload the project folder itself into `public_html` without doing this —
it would expose `.env` and your database credentials to the internet.

---

## 2. Create the database

In cPanel → MySQL Databases, create a database and a user, and give that user
all privileges on it. Note the database name, username and password.

---

## 3. Configure

Copy `.env.example` to `.env` and edit it:

```ini
APP_NAME="Your Brand"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com     # ← see the warning below

APP_TIMEZONE=Asia/Karachi           # so "scans today" matches your working day

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

QR_IP_SALT=                         # paste a long random string here, once
```

> **`APP_URL` is baked into every QR image you generate.** Set it to your real
> https domain *before* you generate any codes for printing. If you change it
> later, codes already printed stop working.

> **`QR_IP_SALT`** should be set once to a long random string and then never
> changed — it is what lets the system tell a repeat scan from a new visitor.

---

## 4. Install

From the project folder (the one containing `artisan`):

```bash
php artisan key:generate
php artisan dqr:install
```

`dqr:install` creates your database tables and then asks for the name, email and
password of your super admin account. That is the account you sign in with.

> Do **not** run `php artisan db:seed` on a live site. That loads demo customers
> with a publicly known password; it is for local testing only.

---

## 5. Permissions

`storage/` and `bootstrap/cache/` must be writable by PHP:

```bash
chmod -R 775 storage bootstrap/cache
```

On most shared hosting `755` is enough and `775` may be refused — try `755`
first if your host complains.

---

## 6. Cache for speed (optional but recommended)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run these any time you edit `.env`. In particular, `QR_REDIRECT_PREFIX` is
baked into cached routes.

---

## You're live

Sign in at `https://your-domain.com/login` with the account you just created.

First things to do:

1. **Customers → Add customer** — create a customer, and set "How many QR codes?"
   if they have already bought hardware.
2. **QR codes** — type a number in the box at the top to generate a batch of
   unassigned stock you can hand out later.
3. **Export URLs** (Excel or CSV) and the **PNG/SVG ZIP** give you everything a
   print shop needs.

---

## Afterwards

**Backups.** Your QR codes table is the valuable part — if you lose it, every
printed product stops working. Back the database up regularly.

**HTTPS.** Use it. Scans go through your domain, and some phone cameras warn on
plain http.

**Cloudflare (optional).** Putting the site behind Cloudflare gives you visitor
country data for free and absorbs scan traffic spikes.

**Queue worker (only if you enable `QR_GEO_LOOKUP`).** Everything else runs
synchronously and needs no worker:

```bash
php artisan queue:work --daemon
```

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| "No application encryption key has been specified" | You skipped `php artisan key:generate` |
| Blank white page | `storage/` is not writable, or `APP_DEBUG=false` is hiding an error — check `storage/logs/laravel.log` |
| Scans redirect to the wrong domain | `APP_URL` was wrong when the codes were generated; those codes are fixed to the old URL |
| CSS missing / page looks unstyled | `public/build/` did not upload, or `index.php` paths are wrong |
| Changes to `.env` have no effect | You ran `config:cache` — run `php artisan config:clear` |

# Dynamic QR

A Laravel 13 system for selling NFC / QR review products, built around one idea:

> **The printed code never changes. Where it sends people always can.**

Every QR image encodes a permanent short link such as `https://your-domain.com/q/AB12CD3`.
Scanning it hits your server, the scan is recorded, and the visitor is bounced to
whatever destination that code currently points at. Change the destination in the
dashboard and the very next scan goes somewhere new — no reprint, no re-sticker.

---

## The two ways stock reaches a customer

Both workflows the business actually runs are first-class:

**1. Somebody buys 10 standees.**
Create the customer and their codes in one step — *Customers → Add customer*, set
"How many QR codes?" to 10. Their account is created, 10 codes are generated,
labelled and assigned, and they appear in that customer's dashboard immediately.

**2. You pre-print 100 and hand them out later.**
*Batches → Generate QR codes*, leave the customer blank. You get 100 unassigned
codes, a ZIP of printable artwork and a print-ready A4 sheet. When somebody walks
in, go to *QR codes*, tick any 5, and use the bulk bar: **Assign to customer**.
They show up in that customer's dashboard straight away.

Either way the super admin can repoint any code at any time, and can lock an
individual code so the customer cannot change it themselves.

---

## What's included

### Super admin console (`/admin`)
- **Overview** — scans over time, inventory health, device and country mix,
  top codes, customers needing follow-up, recent activity
- **QR codes** — filter by status, customer or batch; bulk assign / unassign /
  pause / resume / delete; CSV export; ZIP of artwork for the current filter
- **Batches** — every print run, with a grid preview, ZIP download (PNG or SVG)
  and a printable A4 sheet at 2–6 codes per row
- **Customers** — create (optionally with their codes), edit, suspend, delete,
  reset password, per-customer analytics, CSV export
- **Analytics** — everything above, filterable to one customer, exportable
- **Activity log** — a full audit trail of who repointed which printed code, when,
  and from which IP

### Customer dashboard (`/dashboard`)
- Headline scan numbers with honest period-over-period comparison
- Scans over time, devices, countries, browsers, traffic sources, busiest hours
- A clear prompt for any code that still has no destination
- Per-code page: artwork preview, PNG/SVG download, copyable short link,
  destination editor, pause/resume, full destination history, per-code analytics
- CSV export of their own scan data

### Scan tracking
Recorded per scan: device type, OS, browser (including in-app browsers like
Instagram, Facebook and WeChat), country, referrer, and the destination the
visitor was actually sent to.

- **Bots are excluded from reported numbers.** WhatsApp, Facebook, Slack and
  search-engine link previews are detected and flagged, so a customer's scan count
  reflects people rather than crawlers.
- **Raw IPs are never stored.** Only a salted HMAC, used solely to tell a repeat
  scan from a new visitor.
- **Country comes free behind Cloudflare** via the `CF-IPCountry` header. An
  optional queued lookup (`QR_GEO_LOOKUP=true`) covers other hosting.

---

## Getting started

Requires PHP 8.3+, Composer and Node 20+.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite works out of the box; see .env for MySQL.
touch database/database.sqlite
php artisan migrate --seed

npm run build
php artisan serve
```

### Demo logins

The seeder creates a working demo with ~90 days of realistic scan history,
three customers, and 100 unassigned codes sitting in the pool.

| Role        | Email                 | Password   |
|-------------|-----------------------|------------|
| Super admin | `admin@example.com`   | `password` |
| Customer    | `bilal@example.com`   | `password` |
| Customer    | `ayesha@example.com`  | `password` |
| Customer    | `hassan@example.com`  | `password` |

**Change these before deploying anywhere public.**

---

## Configuration

All settings live in `.env` (documented inline) and `config/qr.php`.

The ones that matter most:

| Setting | Why it matters |
|---|---|
| `APP_URL` | Baked into every printed QR image. **Set this correctly before generating codes for print.** |
| `APP_TIMEZONE` | "Scans today" is grouped by this. Set it to where the business operates, e.g. `Asia/Karachi`. |
| `QR_REDIRECT_PREFIX` | The `/q/` in the printed link. Changing it invalidates codes already printed. |
| `QR_IP_SALT` | Set once to a long random string. Changing it later resets unique-visitor continuity. |
| `QR_CODE_LENGTH` | 7 characters gives ~27 billion combinations from an unambiguous alphabet (no `0`/`O`, no `1`/`I`/`L`). |

### Production notes

- Run `php artisan queue:work` if you enable `QR_GEO_LOOKUP`; nothing else needs a queue.
- Put the app behind Cloudflare if you can — free country data and scan caching at the edge.
- `php artisan config:cache route:cache view:cache` as usual. Note that
  `QR_REDIRECT_PREFIX` is baked into cached routes, so re-cache after changing it.
- The redirect is deliberately **302 + `no-store`**. A 301 would be cached by
  visitors' browsers and destination changes would silently never reach them.

---

## Architecture

| Path | What lives there |
|---|---|
| `app/Http/Controllers/RedirectController.php` | The public scan endpoint. Kept minimal and failure-tolerant. |
| `app/Services/QrCodeService.php` | Generation, assignment, destination changes — all audited. |
| `app/Services/ScanRecorder.php` | Per-scan write path. Never allowed to break a redirect. |
| `app/Services/AnalyticsService.php` | Every number on every dashboard. Takes a base query so one implementation serves global, per-customer and per-code views. |
| `app/Services/QrImageService.php` | PNG/SVG rendering (`endroid/qr-code`). |
| `app/Services/UserAgentParser.php` | Dependency-free device/OS/browser/bot detection. |
| `app/Policies/` | A customer can only ever see and edit their own codes. |
| `resources/js/charts.js` | Chart.js setup. Colours are a validated, colourblind-safe categorical order. |

### Data model

- `qr_codes` — the permanent `code`, its current `target_url`, owner and batch.
  Soft-deleted, so history survives.
- `qr_scans` — one row per scan, with the owner denormalised for fast reporting.
- `qr_code_activities` — the audit log (created / assigned / repointed / paused…).
- `qr_batches` — print runs.

---

## Tests

```bash
php artisan test
```

76 tests cover the redirect engine (302-not-301, no-store, case-insensitive
matching, bot exclusion, IP hashing, unique counting), both fulfilment workflows,
tenant isolation, the open-redirect guard, and that every page renders with data.

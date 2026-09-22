# Dynamic QR — working notes

Laravel 13 app for selling NFC/QR review products. See `README.md` for setup and
feature overview; this file covers conventions and the things that are easy to
break.

## The one invariant

A QR code's `code` is printed onto physical products. **It must never change and
must never be reused.** Everything else about a code is editable.

Consequences worth remembering:

- The scan redirect is **302 with `no-store`**. Never make it 301 or cacheable —
  browsers would cache it and customers' destination changes would silently stop
  reaching people who already scanned.
- `QR_REDIRECT_PREFIX` and `APP_URL` are baked into printed artwork. Changing
  either invalidates codes already in the field.
- Deleting a `QrCode` is a soft delete, and `ShortCodeGenerator` checks
  `withTrashed()` so a deleted code's characters are never handed out again.

## Layout

- `app/Services/` holds the domain logic. Controllers stay thin and delegate.
  - `QrCodeService` — the only place codes get created, assigned or repointed.
    Every mutation writes a `qr_code_activities` row; keep it that way.
  - `ScanRecorder` — runs inside the redirect request. Wrapped in a try/catch on
    purpose: a visitor standing in front of a standee must always land somewhere,
    even if analytics fails.
  - `AnalyticsService` — takes an Eloquent builder so the same code serves the
    global, per-customer and per-code dashboards. Bots are excluded at `base()`.
- `app/Policies/` — customers only ever see and edit their own codes. A code with
  `user_can_edit = false` is staff-only.
- `resources/views/components/ui/` — the design system. Prefer composing these
  over writing new one-off markup.

## Conventions

- **Roles**: `UserRole` enum. `isStaff()` = admin area access; `isSuperAdmin()` =
  staff management and destructive actions.
- **Portability**: SQLite (dev/tests) and MySQL (production) both have to work.
  Date/hour grouping goes through `AnalyticsService::dateExpression()` /
  `hourExpression()`. Don't use `HAVING` on a `withCount()` subquery — it has no
  `GROUP BY` and SQLite rejects it; use `whereHas()` alongside the count.
- **Privacy**: raw visitor IPs are never persisted. If you add a field to
  `qr_scans`, keep it that way.
- **Destination URLs** are validated with `App\Rules\SafeRedirectUrl` (http/https
  only, never back at this dashboard). Any new place that accepts a target URL
  must use it, or a printed product becomes an open redirect.

## Charts

`resources/js/charts.js` owns all Chart.js configuration; Blade only emits
`<canvas data-chart="…" data-chart-config="…">`.

The series colours are a validated, colourblind-safe categorical order
(blue → orange → aqua). Rules if you touch them: assign by entity in fixed order,
never cycle, cap pie/donut forms at three slots plus a neutral "Other", and keep
every chart's legend showing values (several slots sit under 3:1 against white,
so visible labels are the required relief). Never add a second y-axis.

## Tests

`php artisan test` — all green is the expected state. PHPUnit 12, so data
providers use the `#[DataProvider]` attribute, not the `@dataProvider` annotation.

When changing anything in the redirect path, run `tests/Feature/Qr/RedirectTest.php`
first — it encodes the invariants above.

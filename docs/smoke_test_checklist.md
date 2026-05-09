# Komodo v0.0.2 — smoke test checklist

Short manual passes before trusting live DB behavior. Tick items as you verify.

## Routing and URLs

- [ ] `http://localhost/komodo/` (or your base URL) redirects to `.../komodo/public/`.
- [ ] Dashboard loads when `page` is omitted (default landing).
- [ ] Each whitelist route responds:  
  `?page=dashboard` · `companies` · `company` (with `company_id`) · `dataset` · `events` · `market-data` · `research-quality` · `data-gaps` · `pipeline`.

## Errors and UX

- [ ] Unknown `page` (e.g. `?page=totally-unknown`) shows the in-app **Page not found** content and HTTP **404**.
- [ ] Sidebar **aria-current / current-page styling** matches the active route.
- [ ] Narrow viewport (~320–400px wide): sidebar stacks, links remain tappable/readable without horizontal overflow.

## Offline vs live

- [ ] With **`app/config/local.php` absent** (or equivalent “no credentials” setup): pages load without fatal errors; banners describe offline / setup; numeric cells show placeholders or “—” as designed.
- [ ] Valid **`local.php`** + reachable MariaDB: “live” telemetry; tables/views show **real `COUNT(*)`** where queries succeed.
- [ ] **`local.php` present but wrong credentials** (or DB unreachable): **unavailable** messaging in banners; **no raw PDO/stack traces** leaked in the HTML.
- [ ] Intentionally break one whitelisted metric (revoke SELECT on one object): **Degraded / partial metrics** messaging where applicable — still **no PHP warnings or notices** in page output (`display_errors` off in production PHP is still recommended).

## PHP hygiene

- [ ] Reload each page key once with error reporting visible during dev (`php.ini` / XAMPP) or scrutinize rendered output — **expect no warnings**.

---

_Last updated as part of the v0.0.2 read-only research portal; adjust host paths if not using `/komodo/`._

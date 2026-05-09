# Komodo

**Cybersecurity–finance research portal** (read-only)

Komodo is a read-only research portal for the MariaDB database `gecko_research_database_prod`, for local use with XAMPP on Windows. It supports event-study **preparation** and data **readiness** review — not trading or investment advice.

## Current milestone: Komodo v0.0.2

- **Read-only portal:** SELECT-only web app, safe `?page=` routing, no import buttons or edit forms.
- **Next phase:** benchmark / **index price import** via **external** scripts, then security prices — see [`docs/pre_import_checklist.md`](docs/pre_import_checklist.md).
- **Milestone note:** [`docs/komodo_v0_0_2_milestone.md`](docs/komodo_v0_0_2_milestone.md)
- **Developer checks:** [`docs/developer_checks.md`](docs/developer_checks.md) — includes `tools/lint_all.bat`, `komodo_smoke.php`, `komodo_db_check.php`, `komodo_security_scan.php`. One-shot: `tools/check_all.bat`.
- **Manual smoke pass:** [`docs/smoke_test_checklist.md`](docs/smoke_test_checklist.md)

## App entry and configuration

- App shell: [`public/index.php`](public/index.php) — multi-page UI via **`?page=`** (default `dashboard`); live **`COUNT(*)`** when MariaDB connects; graceful offline/degraded fallback.
- PDO: [`app/config/database.php`](app/config/database.php)
- Queries: whitelist-only counts and scoped reads under `app/lib/`

### Local database configuration (PDO)

1. Copy [`app/config/local.example.php`](app/config/local.example.php) to **`app/config/local.php`** (gitignored).
2. Set `host`, `port`, `database`, `username`, `password`, and `charset` for `gecko_research_database_prod`.
3. **Offline / degraded:** missing `local.php`, bad credentials, or unreachable DB still loads the UI with banners; no raw PDO traces in normal output.
4. **Writes:** Komodo does not perform INSERT, UPDATE, DELETE, or DDL from the web app.

Styles: [`assets/css/style.css`](assets/css/style.css). No Composer, no SPA framework, no JavaScript required for core pages.

## Local URL

With XAMPP serving from `htdocs`:

- `http://localhost/komodo/` — redirects to `public/` (root [`index.php`](index.php)).
- Example: `http://localhost/komodo/public/index.php?page=market-data`

Paths assume `/komodo/public/` so `public/` can become the document root later without restructuring.

## Repository layout (high level)

| Path | Purpose |
|------|---------|
| `public/` | Web entry (`index.php`) |
| `app/` | Config, pages, lib (local config ignored) |
| `assets/` | CSS |
| `docs/` | Milestone notes, checklists, developer checks |
| `sql/` | Reference SQL snippets only — no automatic execution |

## License

MIT — see [LICENSE](LICENSE).

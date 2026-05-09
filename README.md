# Komodo

**Cybersecurity Event Study Research Platform**

Komodo is a read-only research dashboard for the MariaDB database `gecko_research_database_prod`. It is developed for local use with XAMPP on Windows.

## Current status

- App shell: [`public/index.php`](public/index.php) — multi-page read-only UI via **`?page=`** (default `dashboard`); live **`COUNT(*)`** when MariaDB connects; graceful fallback when not.
- PDO + status: [`app/config/database.php`](app/config/database.php) (`komodo_get_database_status()`, `get_pdo()`).
- Queries: [`app/lib/dashboard_queries.php`](app/lib/dashboard_queries.php) — whitelist-only, per-object safe counts.

### Local database configuration (PDO)

1. Copy [`app/config/local.example.php`](app/config/local.example.php) to **`app/config/local.php`** (gitignored).
2. Set `host`, `port`, `database`, `username`, `password`, and `charset` for `gecko_research_database_prod`.
3. The dashboard uses [`app/config/database.php`](app/config/database.php) (`komodo_get_database_status()`, `get_pdo()`) and never commits credentials.
4. **Offline / degraded mode:** if `local.php` is missing, invalid, or MariaDB is unreachable, the page still loads with clear banners. Reference counts may display as labeled offline placeholders until a connection succeeds.
5. **Read-only:** Komodo performs **SELECT** (`COUNT(*)`) reads only — no INSERT, UPDATE, DELETE, ALTER, DDL, or data-editing UI.

Styles: [`assets/css/style.css`](assets/css/style.css). No Composer, no frontend framework, minimal JavaScript.

**CLI/developer checks:** see [`docs/developer_checks.md`](docs/developer_checks.md) (`tools/komodo_smoke.php`, `komodo_db_check.php`, `komodo_security_scan.php`, `tools/lint_all.bat`).

## Local URL

With XAMPP serving from `htdocs`, open:

- `http://localhost/komodo/` — redirects to `public/` (root [`index.php`](index.php)).
- App: `http://localhost/komodo/public/` (default dashboard) or e.g. `http://localhost/komodo/public/index.php?page=dataset`

Paths are relative to `/komodo/public/` so that `public/` can become the document root later without restructuring the app folders.

## Repository layout (high level)

| Path | Purpose |
|------|---------|
| `public/` | Web entry (`index.php`) |
| `app/` | Future PHP includes, config (local config ignored) |
| `assets/` | CSS and static JS |
| `docs/` | Documentation |
| `sql/` | Reference SQL snippets only — no automatic execution |

## License

MIT — see [LICENSE](LICENSE).

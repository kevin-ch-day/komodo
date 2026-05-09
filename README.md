# Komodo

**Cybersecurity Event Study Research Platform**

Komodo is a read-only research dashboard for the MariaDB database `gecko_research_database_prod`. It is developed for local use with XAMPP on Windows.

## Current status (v0.1)

- Single static dashboard: [`public/index.php`](public/index.php)
- Placeholder metrics only — **no database connection**
- Styles: [`assets/css/style.css`](assets/css/style.css)
- No Composer, no frontend framework, minimal JavaScript

## Local URL

With XAMPP serving from `htdocs`, open:

`http://localhost/komodo/public/`

Paths are relative to `/komodo/public/` so that `public/` can become the document root later without restructuring the app folders.

## Configuration (later)

When database access is added, credentials will live in a **gitignored** file such as `app/config/local.php`. Do not commit passwords or `.env` files.

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

# Komodo v0.2 — Design note and implementation plan

This document describes what Komodo is, the current baseline (v0.1), live database facts used for dashboards, and a **minimal, read-only v0.2** plan that replaces placeholder counts with PDO-driven queries—**pending approval**. No PHP changes should be merged until agreed.

---

## 1. What Komodo is

Komodo is a **local PHP / XAMPP read-only research dashboard** for the MariaDB database **`gecko_research_database_prod`**. It supports a **cybersecurity–finance event-study** workflow: tracking dataset scale, QA and readiness signals, market-data import posture, and (later) event-study and analysis outputs—all without exposing write paths from the web layer in early versions.

Constraints that remain in force:

- Single-machine / localhost-focused development today.
- No authentication, forms, or admin editors in scope for v0.2.
- No Composer packages and no Laravel, React, Vue, or similar frameworks.

---

## 2. Current v0.1 status

| Area | Status |
|------|--------|
| Entry | Single page: `public/index.php` |
| Data | **Static placeholder** counts and labels only |
| Database | **No connection** |
| Routing | None (one URL) |
| Writes | None (by design) |

The dashboard sections (dataset summary, event readiness, market import, gaps, research quality flags, future ML placeholder) are already structured in markup; v0.2 will **exchange static numbers for live counts** while preserving layout and safety rules.

---

## 3. Current database facts

These are the **expected baseline row counts** for tables and views used to validate v0.2 queries after connection. They are not hardcoded in application logic long term; they are reference numbers for QA.

### Important base tables

| Table | Approx. rows |
|-------|----------------|
| companies | 68 |
| securities | 69 |
| cyber_events | 50 |
| cyber_event_dates | 101 |
| cyber_event_features | 50 |
| cyber_event_impacts | 50 |
| cyber_event_securities | 50 |
| market_calendar | 5,113 |
| security_daily_prices | 0 |
| index_daily_prices | 0 |
| cyber_event_sources | 0 |
| event_study_runs | 0 |
| event_study_results | 0 |

### Important analytical views

| View | Approx. rows |
|------|----------------|
| vw_event_study_event_readiness | 50 |
| vw_security_price_import_targets | 69 |
| vw_market_data_import_plan | 69 |
| vw_us_trading_days | 3,520 |
| vw_event_window_boundaries | 350 |
| vw_event_same_ticker_window_overlaps | 13 |
| vw_event_nearby_cyber_clusters | 7 |
| vw_event_contamination_flags | 50 |
| vw_event_impact_quality_flags | 50 |
| vw_event_research_readiness_flags | 50 |

**Policy:** Do **not** create new database views from Komodo. The app should **consume** these views (and base tables) with read-only SQL. Business logic for readiness and QA stays in the database layer as already designed.

---

## 4. v0.2 goal

**Replace static dashboard counts with live, read-only database counts using PDO.**

Scope for v0.2 is intentionally narrow:

- Same single page (`public/index.php`), same sections.
- Each metric that is today a static number becomes a **`SELECT COUNT(*)`** (or equivalent single-scalar read) against the appropriate table or view.
- No new routes, no detail pages, no CSV export, no ML screens.

Out of scope for v0.2 (unless explicitly approved later):

- Paginated tables of events, securities, or import plans.
- Filters, search, or sorting backed by the DB on this page.

---

## 5. Safety rules

The web app must remain a **read-only consumer** of MariaDB.

| Rule | Detail |
|------|--------|
| SQL | **SELECT-only** from Komodo code paths. No `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `CREATE`, `TRUNCATE`, `REPLACE`, grants, or other mutating statements. |
| UX | No forms that submit mutations; no “admin” actions. |
| Auth | Not in v0.2. Localhost trust model only; revisit before any network exposure. |
| Dependencies | **No Composer.** Plain PHP includes only. |
| Framework | None. |
| Secrets | **Do not** hardcode passwords (or full DSN secrets) in committed files. |
| Local config | Real credentials live in **`app/config/local.php`**, which **must remain gitignored** (already listed in `.gitignore`). |
| Template for others | **`app/config/local.example.php`** — committed template with placeholders only; **no real credentials.** |

Operational note: MariaDB users used by Komodo should be granted **`SELECT`** (and optionally `SHOW` usage as needed for introspection—not required for counts) on `gecko_research_database_prod` only, not global admin rights.

---

## 6. Proposed v0.2 files (minimal plan — **do not create until approved**)

| File | Role |
|------|------|
| `app/config/local.example.php` | Example structure: e.g. return an array `['driver' => 'mysql', 'host', 'dbname', 'user', 'pass', 'charset']` — all placeholder values; copy to `local.php`. |
| `app/config/database.php` | Loads `local.php` if present; builds a **PDO** DSN + options (`ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE` => associative, emulate prepares off if appropriate); exposes something like `get_pdo()` that returns `?PDO` (null when not configured). **No passwords in this file.** |
| `app/lib/dashboard_queries.php` *(preferred over `app/pages/` for v0.2)* | Pure functions: given `PDO`, run prepared `SELECT COUNT(*)` per table/view; return an array of scalars or a small DTO array. **No HTML.** Centralizes the list of allowed table/view names so `index.php` does not scatter SQL strings. |
| `public/index.php` | Require bootstrap + query module; if PDO is null or connection/query fails, set a **user-visible warning** and use **null-safe defaults** (or omit numbers) so the page still renders. |
| `README.md` | Document: copy `local.example.php` → `local.php`, set DB name `gecko_research_database_prod`, local URL, and that the dashboard degrades gracefully without config. |

**Why `app/lib/dashboard_queries.php` instead of `app/pages/dashboard_data.php`:**  
`pages/` suggests routable templates; v0.2 has one page. A small **`lib/`** (or `includes/`) query module keeps `index.php` thin and avoids implying extra routes. If the project prefers `app/pages/` for all PHP outside `public/`, the same contents can live in `app/pages/dashboard_data.php`—functionally equivalent.

**Files explicitly not added in v0.2:** routing front controller, Composer autoload, `.env` loader, generic ORM, write APIs.

---

## 7. Fallback behavior

The dashboard must **never** fail with an uncaught exception or raw stack trace in normal use.

| Condition | Behavior |
|-----------|----------|
| `app/config/local.php` missing | Page loads; prominent message: **“Database connection not configured.”** Placeholder or empty counts for numeric cells (or “—”)—product decision at implementation time; must be consistent and readable. |
| `local.php` present but connection fails (wrong password, server down, etc.) | Page loads; message: **“Database unavailable.”** Same degradation for numbers. |
| Connection OK but a single count query fails | Prefer: show warning for that section or global “partial data” message; do not white-screen. Log optional only if a project-wide logging approach exists (not required in v0.2). |

Implementation sketch (for when coding is approved):

- Wrap `new PDO(...)` in try/catch; catch `PDOException`, set a string `$db_status` for the banner.
- Run counts in a try/catch or validate each query; on failure, leave that metric as `null` and flag degraded state.
- **Never** echo exception messages to the browser in production-style paths; use generic user text.

---

## 8. Next step after v0.2

Once live counts on the home dashboard are stable:

- **Event readiness** — tabular drill-down from `vw_event_study_event_readiness` (and related views as needed).
- **Market data import plan** — table from `vw_market_data_import_plan` / `vw_security_price_import_targets`.
- **Research readiness flags** — table from `vw_event_research_readiness_flags` and sibling flag views.
- **Price coverage** — coverage statistics from price tables and calendar/views.

These are **not** part of v0.2 and should not be started until v0.2 is merged and validated.

---

## Approval gate

Before any code changes:

1. Confirm the **file list** (`local.example.php`, `database.php`, `dashboard_queries.php`, `index.php`, `README.md`).
2. Confirm **naming**: `app/lib/dashboard_queries.php` vs `app/pages/dashboard_data.php`.
3. Confirm **PDO options** (e.g. exception mode, charset `utf8mb4`) and that only **SELECT** statements appear in Komodo.

After approval, implement in small steps: config template → PDO helper → query module → `index.php` integration → README.

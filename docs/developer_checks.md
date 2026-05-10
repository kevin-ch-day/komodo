# Developer checks — Komodo v0.0.2

Small **plain PHP / batch** helpers under `tools/`. No Composer, PHPUnit, or DB writes.

Run commands from the project root (`D:\Windows\xampp\htdocs\komodo`) unless noted.

---

## `tools/import_index_prices.php`

**Purpose:** **CLI-only** loader for local **index** CSV files into `index_daily_prices` (UPSERT). Uses `get_pdo()` from `app/config/database.php`. **Not** part of the web app — run with `php.exe` from a shell. See [`local_csv_index_import.md`](local_csv_index_import.md).

```bat
D:\Windows\xampp\php\php.exe tools\import_index_prices.php --dir=...\data\djia --index-code=DJIA --dry-run
```

Default is **dry-run**; **`--execute`** is required to write.

---

## `tools/import_security_prices.php`

**Purpose:** **CLI-only** loader for local **security** CSV files into `security_daily_prices` (UPSERT). Resolves **`--ticker`** to `security_id` via the `securities` table (no hardcoded map). With **`--dir`**, only files named `{TICKER}_*.csv` are read. Supports **batch** mode (`--all`, `--tickers=A,B`, default directory `data/securities`). See [`local_csv_security_import.md`](local_csv_security_import.md).

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --dir=...\data\securities --ticker=EFX --dry-run
```

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --all --dry-run
```

Default is **dry-run**; **`--execute`** is required to write.

---

## `tools/cleanup_stale_import_csvs.php`

**Purpose:** **CLI-only** helper to list (or delete) redundant CSVs named `SYMBOL_<exportTs>_<rangeTs>.csv` when an older **export** id sits next to a newer one in the same folder. Default **dry-run**; **`--execute`** unlinks stale files. See [`local_csv_index_import.md`](local_csv_index_import.md) (stale exports section).

```bat
D:\Windows\xampp\php\php.exe tools\cleanup_stale_import_csvs.php
D:\Windows\xampp\php\php.exe tools\cleanup_stale_import_csvs.php --execute
```

---

## `tools/komodo_smoke.php`

**Purpose:** Structural smoke test — critical files exist, route keys match `komodo_page_routes()` in `app/config/pages.php` (same source as `public/index.php`), unknown keys stay unmapped, `.gitignore` covers `local.php`, optional git check ensures `app/config/local.php` is **not tracked**, and the CSS bundle from `assets/css/style.css` resolves (via `komodo_css_validate_bundle()`).

```bat
D:\Windows\xampp\php\php.exe tools\komodo_smoke.php
```

- **Offline:** Fully valid — **exit 0** when all PASS.
- **Fails:** Missing file / bad route bookkeeping / tracked secrets — **exit 1**.

**Note:** Add new routes in `app/config/pages.php` only — smoke, `public/index.php`, and the sidebar consume that registry. New sidebar links belong in `komodo_sidebar_nav_groups()` (and labels in `komodo_sidebar_route_label()`); `komodo_sidebar_nav_keys()` is derived for smoke. Page-specific `$ctx` keys (market data, companies, etc.) are wired in `app/lib/page_context.php`.

The core file checklist includes `app/lib/market_data_queries.php` (Market Data import coverage helpers) and `app/lib/event_queries.php` (Events list page context).

---

## `tools/komodo_css_check.php`

**Purpose:** Keep `assets/css/style.css` maintainable — parses `@import url("…")` entries, ensures each partial exists, and fails if any `*.css` in `assets/css/` is not part of that chain (orphans). Also callable as `komodo_css_validate_bundle($repoRoot)` from smoke.

```bat
D:\Windows\xampp\php\php.exe tools\komodo_css_check.php
D:\Windows\xampp\php\php.exe tools\komodo_css_check.php list
```

- **Adding styles:** Prefer a focused partial under `assets/css/`, then add one `@import` line in `style.css` (order = cascade order).
- **Exit:** **0** when the chain is valid; **1** on missing imports or orphan files.

---

## `tools/komodo_db_check.php`

**Purpose:** Loads `komodo_get_database_status()` and, when connected, runs `komodo_get_table_counts_safe()` + `komodo_get_view_counts_safe()`, printing a compact table. Never prints passwords; connection errors surface as structured status messages only.

```bat
D:\Windows\xampp\php\php.exe tools\komodo_db_check.php
```

| Situation | Exit |
|-----------|------|
| `not_configured` / `misconfigured` / `unreachable` — no PDO | **0** (offline valid) |
| `connected`, all whitelist counts `ok` | **0** |
| `connected`, any count `unavailable` (degraded) | **2** |
| PDO set but inconsistent status text | **1** (unexpected) |

---

## `tools/komodo_security_scan.php`

**Purpose:** Recursive scan of `app/**/*.php` and `public/**/*.php` for conservative tripwires:

- SQL-ish keywords as words: `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `CREATE`, `TRUNCATE`, `REPLACE`
- Dynamic `include`/`require` using `$_GET` / `$_POST` / `$_REQUEST`
- Suspicious reads of `local.php` via `file_get_contents` / `readfile`

**Allowlist:** `app/partials/footer.php` line that states the app performs no INSERT / UPDATE / DELETE — documented false positive suppressed.

```bat
D:\Windows\xampp\php\php.exe tools\komodo_security_scan.php
```

- **Exit 0:** No findings  
- **Exit 1:** At least one finding (review output)

This is **not** a security audit — only a local safety net.

---

## `tools/lint_all.bat`

**Purpose:** Run `php -l` on the PHP files Komodo cares about (app, public, root `index.php`, and `tools/*.php`). Adjust the `PHP=` path at the top of the batch file if XAMPP lives elsewhere.

```bat
tools\lint_all.bat
```

- **Exit 0:** All syntax checks passed  
- **Exit 1:** At least one failure

---

## `tools/check_all.bat`

**Purpose:** Run **`lint_all.bat`**, then **`komodo_smoke.php`**, **`komodo_db_check.php`**, and **`komodo_security_scan.php`** in order. Uses `D:\Windows\xampp\php\php.exe` (edit the batch file if your XAMPP path differs).

```bat
tools\check_all.bat
```

- **Exit:** Non-zero if any step fails (per underlying tool). Note: `komodo_db_check.php` uses **exit 2** when connected but some whitelist counts are unavailable — that also fails this batch.

Milestone context: [`komodo_v0_0_2_milestone.md`](komodo_v0_0_2_milestone.md) · Before price loads: [`pre_import_checklist.md`](pre_import_checklist.md)

**Schema migrations (manual, not run by tools here):** e.g. [`sql/remove_data_sources.sql`](../sql/remove_data_sources.sql) — backup, review constraint names, then execute in MariaDB when approved. Import CLI scripts and the Market Data page no longer reference `data_sources`; dropping the table/columns keeps the DB aligned with the app.

---

## When to run

| Timing | Scripts |
|--------|---------|
| After refactors / route or layout changes | `komodo_smoke.php`, `lint_all.bat`, `komodo_security_scan.php` (smoke includes CSS bundle check) |
| Before commits | All of the above; fix security scan warnings or explicitly document exceptions |
| Before heavy DB import work | `komodo_db_check.php` (confirm live/degraded/offline posture); see [`pre_import_checklist.md`](pre_import_checklist.md) |
| Milestone / release sanity | `check_all.bat` (or run each tool individually) |
| After adding / editing `local.php` | `komodo_db_check.php`; `komodo_smoke.php` confirms git hygiene |
| After adding files under `app/` or `public/` | Update **`lint_all.bat`** file list if new includes matter |

---

See also [`smoke_test_checklist.md`](smoke_test_checklist.md) for browser-level checks and [`komodo_v0_0_2_milestone.md`](komodo_v0_0_2_milestone.md) for the closed portal milestone scope.

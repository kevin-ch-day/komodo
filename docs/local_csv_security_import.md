# Local CSV security price import (CLI)

**Purpose:** Load manually collected **equity** OHLCV CSV files into MariaDB table **`security_daily_prices`** using a **CLI-only** PHP script. Komodo’s web UI does **not** run imports and has no import buttons.

**Context:** The **benchmark index** CSV pipeline is proven in Komodo (`index_daily_prices`), but current index coverage may be **sparse** (e.g. weekly/monthly bars). That is **not** the same as **event-study-grade daily** benchmark data. **Security** price import is still worth building and testing: it validates the **next pipeline layer** (ticker → `security_id` → `security_daily_prices` → read-only UI). You can **replace benchmarks with daily data in parallel** without blocking security imports.

**Script:** `tools/import_security_prices.php`  
**Config:** Same as the app — `app/config/database.php` → `get_pdo()` → `app/config/local.php` (password never printed). Inserts do **not** use `data_source_id` (removed from schema).

---

## CLI-only warning

- Run with `php.exe` from a shell (e.g. XAMPP).
- Do **not** expose this file via the web server as an executable endpoint.
- Default mode is **`--dry-run`** (parses CSV, resolves ticker via **read-only** `SELECT` on `securities`, **no** `INSERT`/`UPDATE` on prices). **`--execute`** is required to write rows to `security_daily_prices`.
- **No CLI arguments** (or any combination that does not select a single ticker / file) defaults to **batch dry-run**: scan `data/securities` for `TICKER_*.csv` files and print a short per-ticker table (no writes).

---

## Expected CSV columns

Headers are matched case-insensitively (similar to the index importer):

| Canonical | Accepted header examples |
|-----------|-------------------------|
| Date | `Date`, `Trade Date`, `trade_date` |
| Open | `Open` |
| High | `High` |
| Low | `Low` |
| Close | `Close`, `Close/Last`, `Last` |
| Adjusted close | `Adj Close`, `Adjusted Close`, `adjusted_close` |
| Volume | `Volume` |

Extra columns (e.g. `% Change`, `% Change vs Average`) are ignored.

**Rules:**

- **trade_date** and **close** are required.
- If adjusted close is missing, **adjusted_close** is set to **close**.
- If volume is empty, it is stored as **NULL**.
- Dates may be `YYYY-MM-DD` or ISO timestamps such as `2020-01-06T06:00:00.000Z` (normalized to **YYYY-MM-DD**).

---

## Ticker → `security_id` resolution

The importer does **not** hardcode tickers. It runs:

```sql
SELECT
    security_id,
    ticker_symbol,
    security_name,
    company_id,
    start_date,
    end_date,
    is_active
FROM securities
WHERE ticker_symbol = :ticker
  AND is_active = 1
ORDER BY start_date DESC, security_id DESC;
```

- **Exactly one** active row → that `security_id` is used.
- **Zero** rows → script exits with an error (check ticker and `is_active`).
- **More than one** active row → script exits (listing/ambiguity — fix data or narrow query later).

Warnings (not fatal) may appear if a row’s `trade_date` is before `start_date` or after `end_date` (when `end_date` is set).

---

## FB vs META (Facebook listing change)

Vendor files may use the current ticker **META** for historical Facebook prices (including dates before the June 2022 ticker change). For **pre–June 2022** event windows tied to historical ticker **FB**, import those historical rows into the **FB** security record (`security_id` for the FB row in `securities`), **not** the active **META** record, **unless** an explicit ticker-continuity rule says otherwise. **META**-labeled exports do **not** automatically satisfy **FB**-tagged windows unless the import/load step maps historical rows to the **FB** `security_id`.

This is **ticker lineage and source labeling**, not a Komodo data error.

The CLI resolver above selects **active** securities (`is_active = 1`). If **FB** is stored as an **inactive** historical row in your database, the default ticker lookup may not target it — use whatever workflow correctly loads bars onto the **FB** `security_id` for your event-linked design.

---

## `--dir` and ticker filtering

`data/securities/` may contain many tickers.

- **Single-ticker mode:** with **`--dir`** (default directory: **`data/securities`** relative to the repo root when omitted) and **`--ticker`**, only files whose **basename** starts with **`{TICKER}_`** (case-insensitive) are read — e.g. `--ticker=EFX` loads `EFX_20260501000000000_....csv` and `EFX_20260504000000000_....csv`, not `AAP_...`.
- **Batch mode:** see below; tickers are inferred from filenames **`TICKER_*.csv`** (substring before the first `_`, uppercased). Files that do not match are skipped; the batch summary reports how many were skipped.

Files are sorted by path ascending; **`security_id` + `trade_date`** duplicates across files are merged with **later file wins**.

---

## Batch mode (`--all` / default dry-run / `--tickers`)

Batch mode scans a directory (default **`data/securities`**, override with **`--dir=<path>`**), groups CSVs by ticker from the filename pattern above, sorts tickers alphabetically for **`--all`**, and uses the **`--tickers=`** order when that flag is set.

- **One transaction per ticker** on **`--execute`**: commit on success; rollback only that ticker on error; continue with the rest unless **`--fail-fast`**.
- **Dry-run** shows a compact table (Status / rows / rejects / warnings / range). **Execute** shows Result, upserts, per-ticker DB row count, then **Total DB rows after batch** (`COUNT(*)` on `security_daily_prices`).
- **`--verbose` / `--debug`**: after the batch table, the script prints the same per-ticker detailed report as single-ticker mode for each ticker that produced a verdict payload (failed tickers may have no detail block).
- **`--archive-on-success`**: after a successful **`--execute`** commit for a ticker, its processed CSV files are moved under **`data/imported/securities/YYYYMMDD_HHMMSS/`** (one timestamp per batch run; filenames preserved). If a move fails, a warning is printed; **already committed rows are not rolled back**.

---

## CLI flags

| Flag | Meaning |
|------|--------|
| *(no ticker / file args)* | **Batch dry-run** on default `data/securities` (same as `--all` without `--execute`). |
| `--all` | Batch: every ticker found from `TICKER_*.csv` filenames in `--dir`. |
| `--tickers=A,B,C` | Batch: only these tickers (files still resolved from `--dir`). |
| `--dir=<path>` | Folder for batch or for **`--ticker`** scans (default: `data/securities`). Do not combine with `--file`. |
| `--file=<path>` | Single CSV (requires **`--ticker`**; do not pass `--dir`). |
| `--ticker=EFX` | Single-ticker mode: resolves `security_id` in `securities` |
| `--dry-run` | Default if `--execute` omitted; parse + summary; **no** price UPSERTs |
| `--execute` | Perform UPSERTs (**single-ticker:** one transaction; **batch:** one transaction per ticker) |
| `--fail-fast` | Batch: stop after the first hard ticker failure (default: continue and list failed tickers). |
| `--archive-on-success` | After a successful execute commit, move that ticker’s CSVs to `data/imported/securities/YYYYMMDD_HHMMSS/` (see above). |
| `--max-rows=N` | After merge/dedupe, keep first **N** rows in chronological order (smoke tests) |
| `--quiet` | One-line result (DRY-RUN / EXECUTE, counts, status). Batch: one summary line. Highest requested detail wins over quiet. |
| `--verbose` | After the concise summary: file paths + row counts per file, warning examples / summary, plan-vs-window detail (dry-run), post-import notes (execute). In batch, runs after the summary table. |
| `--debug` | Full legacy-style trace: headers, mapped columns, first five normalized rows, host/port/user, `SELECT DATABASE()`, `@@hostname`, long resolved ticker line, then a concise recap. In batch, per-ticker after the table. |
| `--verbose-warnings` | With `--verbose` or `--debug`: print **every** parser warning line. Default is bucket counts only (and examples in `--verbose`). |
| `--fail-on-warnings` | Exit code **2** if any parser warnings (dry-run or after commit). |

**Single-ticker:** exactly one of `--file` or `--dir` is required (or rely on default `--dir` when using `--ticker` only). **Batch:** do not pass `--file`. Do not pass `--dry-run` and `--execute` together.

**Output precedence:** `--debug` overrides `--verbose`; both override `--quiet`.

---

## Output modes (default is concise)

**Default (no extra flags)** prints a short operator summary, for example:

```text
Komodo security import — DIS
Mode: DRY-RUN
Database: gecko_research_database_prod
Ticker: DIS → security_id=41
Files: 1
Rows parsed: 113
Rejected: 0
Warnings: 0
Range: 2017-01-02 → 2026-05-01
Window: 2017-01-01 → 2026-05-09
Preview: READY TO EXECUTE
Next: refresh Price Import Triage / Price Coverage.
```

Execute mode adds **Status: COMMITTED**, **Upserts**, **DB total**, **Coverage:** (vs `vw_market_data_import_plan` using calendar-day slack), and the same **Next:** line.

**Warnings:** With many identical warnings (e.g. dates before `securities.start_date`), default output shows counts by bucket only, for example:

```text
Warnings: 284
- 284× trade_date before securities.start_date
```

Use `--verbose` or `--debug` for examples; add `--verbose-warnings` with those for the full per-line list.

Host, port, database user, and session identity lines appear only under `--debug`.

---

## Dry-run examples

**Batch (all tickers in `data/securities`, default dry-run):**

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php
```

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --all --dry-run
```

**Batch subset:**

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --tickers=EL,GLOB,GOOGL --dry-run
```

Preview parsing and resolution (no writes), single ticker:

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\securities" --ticker=EFX --dry-run
```

Limit normalized rows for a quick check:

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\securities" --ticker=EFX --dry-run --max-rows=10
```

Single file:

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --file="D:\Windows\xampp\htdocs\komodo\data\securities\AAP_20260504000000000_20190107000000000.csv" --ticker=AAP --dry-run
```

---

## Execute examples (use only after dry-run looks correct)

**Batch (explicit `--all` required with `--execute`; one DB transaction per ticker):**

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --all --execute
```

**Batch with archive after successful commits:**

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --all --execute --archive-on-success
```

Single ticker:

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\securities" --ticker=EFX --execute
```

Partial test batch:

```bat
D:\Windows\xampp\php\php.exe tools\import_security_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\securities" --ticker=EFX --execute --max-rows=10
```

`--dry-run` and `--execute` must not be passed together.

---

## EFX overlapping files

Two exports for the same ticker (different date ranges) are expected. Both match `EFX_*.csv`. The importer merges on **`trade_date`**; when both files contain the same date, the **later path** (sort order) overwrites — reported as **same-date overwrites** in the log. The database enforces **`UNIQUE (security_id, trade_date)`**, so **`--execute`** remains idempotent.

---

## Post-import SQL

```sql
SELECT
    s.ticker_symbol,
    s.security_name,
    COUNT(sdp.trade_date) AS price_rows,
    MIN(sdp.trade_date) AS first_trade_date,
    MAX(sdp.trade_date) AS last_trade_date
FROM securities s
LEFT JOIN security_daily_prices sdp
    ON s.security_id = sdp.security_id
WHERE s.security_id = 1   -- example: EFX; use your resolved security_id
GROUP BY
    s.security_id,
    s.ticker_symbol,
    s.security_name;
```

---

## Komodo pages to refresh

After a successful load:

- **Market Data** — security price coverage / import readiness.
- **Companies** — list and aggregates that reference price rows.
- **Company detail** — per-security price stats where shown.

Hard-refresh the browser if counts look stale.

---

## See also

- Index CSV import: [`local_csv_index_import.md`](local_csv_index_import.md) (includes **stale export cleanup** for `SYMBOL_<export>_<range>.csv` files under `data/`)
- Pre-import checklist: [`pre_import_checklist.md`](pre_import_checklist.md)
- Developer commands: [`developer_checks.md`](developer_checks.md)

# Local CSV → `index_daily_prices` (CLI importer)

**Komodo web app stays read-only.** Import runs only via:

`tools/import_index_prices.php` **from the command line** (not from Apache).

---

## Purpose

Load **benchmark index** daily rows from **local `.csv` files** (manually downloaded or exported) into `index_daily_prices` using **`INSERT ... ON DUPLICATE KEY UPDATE`** so reruns and overlapping files are safe (`UNIQUE (market_index_id, trade_date)`).

**Scope:** index prices only (e.g. DJIA first). **Not** security prices.

---

## Requirements

- PHP CLI (e.g. `D:\Windows\xampp\php\php.exe`)
- `app/config/local.php` configured and MariaDB reachable (for **`--execute`** only)
- CSV files readable from the path you pass

---

## Index IDs

| `--index-code` | `market_index_id` |
|----------------|-------------------|
| DJIA | 1 |
| SP500 | 2 |
| NASDAQ_COMP | 3 |

Provenance is not stored in the database; track exports in your own notes if needed.

---

## Flexible CSV headers

Headers are matched **case-insensitive** (trimmed). Recognized names include:

| Field | Accepted header examples |
|-------|-------------------------|
| Date | `Date`, `Trade Date`, `trade_date` |
| Open | `Open`, `Open Value`, `open` |
| High | `High`, `High Value`, `high` |
| Low | `Low`, `Low Value`, `low` |
| Close | `Close`, `Close/Last`, `Last`, `close` |
| Adj close | `Adj Close`, `Adj Close*`, `Adjusted Close`, `adjusted_close` |
| Volume | `Volume`, `volume` |

**Date formats:**

- `YYYY-MM-DD`
- `MM/DD/YYYY`
- ISO timestamps such as `2025-11-10T06:00:00.000Z` (calendar **date** is taken from the `YYYY-MM-DD` prefix)

**Rules:**

- **Close** is required (maps to `close_value`).
- If adjusted close is missing, **`adjusted_close_value` = `close_value`**.
- Empty volume → SQL `NULL`.
- Commas and `$` stripped from numbers.
- Overlapping files: files are processed in **ascending filename order**; **later file wins** for the same `trade_date` (in-memory), then UPSERT applies the same to the DB.

---

## CLI flags

| Flag | Meaning |
|------|---------|
| `--file=<path>` | One CSV |
| `--dir=<path>` | All `*.csv` in folder (sorted by name) |
| `--index-code=DJIA` | **Required** |
| `--dry-run` | Parse + validate + print summary; **no DB writes** (this is the default if `--execute` is omitted) |
| `--execute` | **Required** to perform UPSERTs |
| `--max-rows=N` | After merge/dedupe, import only the **first N** rows in **chronological order** (handy for smoke tests) |
| `--quiet` | One-line summary |
| `--verbose` | Concise summary plus file list, row counts, warnings/rejects, post-import coverage JSON |
| `--debug` | Full parse dump (headers, mapped columns, sample rows, DB config/session), then concise summary |

Exactly one of `--file` or `--dir` is required. Detail precedence: `--debug` > `--verbose` > `--quiet`.

---

## Examples

**Dry-run (default), directory, first 10 rows:**

```bat
D:\Windows\xampp\php\php.exe tools\import_index_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\indexes\djia" --index-code=DJIA --max-rows=10
```

**Explicit dry-run:**

```bat
D:\Windows\xampp\php\php.exe tools\import_index_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\indexes\djia" --index-code=DJIA --dry-run
```

**Execute test batch (10 rows):**

```bat
D:\Windows\xampp\php\php.exe tools\import_index_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\indexes\djia" --index-code=DJIA --execute --max-rows=10
```

**Full directory import (writes):**

```bat
D:\Windows\xampp\php\php.exe tools\import_index_prices.php --dir="D:\Windows\xampp\htdocs\komodo\data\indexes\djia" --index-code=DJIA --execute
```

---

## After import (Komodo)

Refresh in the browser (no deploy needed if DB updated):

1. **Market Data** — index coverage / row counts  
2. **Price Import Readiness** — benchmark row should move toward **Partial** / **Loaded**  
3. **Pipeline** — narrative may shift as counts change  

---

## Related docs

- [`manual_price_import_workflow.md`](manual_price_import_workflow.md) — paste/SQL-only workflow  
- [`pre_import_checklist.md`](pre_import_checklist.md) — manual SQL checks  
- [`developer_checks.md`](developer_checks.md) — lint, smoke, security scan  

---

## Removing stale exports (same symbol, older download)

Files named `SYMBOL_<exportTimestamp>_<rangeTimestamp>.csv` sometimes accumulate when you re-download the same index or equity: the **export** token (middle segment) increases on newer pulls. Importers merge overlapping dates in filename order, so older export IDs in the **same folder** are usually safe to delete once you trust the newest export.

**Tool:** `tools/cleanup_stale_import_csvs.php` (default **dry-run**; **`--execute`** deletes).

```bat
D:\Windows\xampp\php\php.exe tools\cleanup_stale_import_csvs.php
D:\Windows\xampp\php\php.exe tools\cleanup_stale_import_csvs.php --execute
```

Optional: `--root=path` (defaults to `data/` under the repo). **`--quiet`** for machine-friendly output.

---

## Git and data

Keep CSV trees under `data/` — **`data/` is gitignored** so local exports are not committed.

---

_CLI only: if `PHP_SAPI !== 'cli'`, the script exits with an error._

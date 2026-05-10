# Manual benchmark index price import (SQL workflow)

**Komodo stays read-only.** This workflow is for **you** (or a helper) to paste data into ChatGPT/Cursor, generate `INSERT ... ON DUPLICATE KEY UPDATE` statements, and run them in the **MariaDB client** on Windows — not through the web app.

**Budget / timeline:** No downloader, no paid API, no PHP/Python importer in this phase — manual collection + generated SQL only.

---

## 1. Manual data format (paste block)

Use a **single header row** followed by one row per trading day per index. **CSV-style:** commas separate fields; **no thousands separators** in numbers.

### Header (required)

```text
index_code,trade_date,open,high,low,close,adjusted_close,volume
```

### Columns

| Column | Required | Notes |
|--------|----------|--------|
| `index_code` | Yes | Exactly one of: `DJIA`, `SP500`, `NASDAQ_COMP` |
| `trade_date` | Yes | `YYYY-MM-DD` |
| `open`, `high`, `low`, `close` | See validation | Decimal; no comma thousands |
| `adjusted_close` | Yes | May **equal** `close` if adjusted series unavailable |
| `volume` | Optional | Integer or empty for **NULL**; no commas |

### Example block (illustrative — not real data)

```text
index_code,trade_date,open,high,low,close,adjusted_close,volume
SP500,2013-01-02,1426.19,1462.43,1426.19,1462.42,1462.42,4202600000
SP500,2013-01-03,1462.42,1472.58,1459.51,1461.89,1461.89,3601230000
```

### Formatting rules

- Strip **commas** from large numbers (`4,202,600,000` → `4202600000`).
- Dates **must** be `YYYY-MM-DD`.
- Empty `volume` field → SQL `NULL`.
- One logical row per **`index_code` + `trade_date`** in each paste batch (no duplicates in the batch).

---

## 2. `market_index_id` mapping

| `index_code`   | `market_index_id` |
|----------------|-------------------|
| `DJIA`         | 1 |
| `SP500`        | 2 |
| `NASDAQ_COMP`  | 3 |

---

## 3. Provenance (documentation only)

Komodo no longer stores a `data_source_id` / `data_sources` row for price loads. Note URLs, export dates, and provider quirks in [`index_symbol_source_notes.md`](index_symbol_source_notes.md) or your own import log.

---

## 4. SQL generation rules

Target table: **`index_daily_prices`**.

- **Do not** insert `index_price_id` (auto-increment).
- **Do not** include `created_at` in `INSERT` (use table default; **never** `ON DUPLICATE KEY UPDATE` it).

### UPSERT template (one row)

Replace placeholders with literals. `volume` may be `NULL`.

```sql
INSERT INTO index_daily_prices (
  market_index_id,
  trade_date,
  open_value,
  high_value,
  low_value,
  close_value,
  adjusted_close_value,
  volume
) VALUES (
  /* market_index_id */ 2,
  /* trade_date */ '2013-01-02',
  /* open */ 1426.19,
  /* high */ 1462.43,
  /* low */ 1426.19,
  /* close */ 1462.42,
  /* adjusted_close */ 1462.42,
  /* volume */ 4202600000
) AS new
ON DUPLICATE KEY UPDATE
  open_value = new.open_value,
  high_value = new.high_value,
  low_value = new.low_value,
  close_value = new.close_value,
  adjusted_close_value = new.adjusted_close_value,
  volume = new.volume;
```

**MariaDB 10.2+** supports `VALUES(col)` in `ON DUPLICATE KEY UPDATE`; **10.3.3+** recommends row alias (`AS new` + `new.col`) as above. If your MariaDB rejects `AS new`, use the equivalent:

```sql
ON DUPLICATE KEY UPDATE
  open_value = VALUES(open_value),
  high_value = VALUES(high_value),
  low_value = VALUES(low_value),
  close_value = VALUES(close_value),
  adjusted_close_value = VALUES(adjusted_close_value),
  volume = VALUES(volume);
```

### Multi-row INSERT (chunked)

For **100–250 rows** per statement, use one `INSERT INTO ... VALUES (...), (...), ...` with a **single** `ON DUPLICATE KEY UPDATE` clause listing the same assignments using `VALUES(column_name)` or row alias per your server version.

**First test:** generate only **5–10 rows per index** (separate statements or one small multi-row insert), run post-import checks, then scale.

---

## 5. Validation checklist (before generating or running SQL)

Have ChatGPT/Cursor (or a quick script later) verify:

- [ ] **`index_code`** ∈ `DJIA`, `SP500`, `NASDAQ_COMP` only.
- [ ] **`trade_date`** matches `YYYY-MM-DD` and is a real calendar date.
- [ ] **Numeric fields** parse as decimals/integers; no stray text.
- [ ] **`close_value`** is **NOT NULL** in SQL (required for sensible series).
- [ ] **`high_value` ≥ `low_value`** when both non-NULL.
- [ ] **`volume`** is integer or NULL (no decimals unless you explicitly allow — table is `bigint`).
- [ ] **No duplicate** `(index_code, trade_date)` within the pasted batch.
- [ ] **Warn:** `trade_date` falls on **Saturday/Sunday** (might still be valid for some sources — flag for review).
- [ ] **Warn:** `trade_date` **before 2013-01-01** or **after today** (policy: usually 2013-01-01 through current date).

If any **hard** check fails, fix the CSV and regenerate — do not run bad SQL.

---

## 6. Chunking and test workflow

1. **Pilot:** For each index (or one index first), paste **5–10 days** in the CSV format.
2. Generate UPSERT SQL; review literals and `market_index_id`.
3. Run in MariaDB; run **post-import checks** (section 7).
4. Open **Komodo** → Market Data + Price Import Readiness (section 8).
5. **Bulk:** Increase to chunks of **100–250 rows** per statement until coverage is complete.

Keep a **log** (text file) of which batch ran when and any source URL — see optional [`index_symbol_source_notes.md`](index_symbol_source_notes.md).

---

## 7. Post-import SQL checks

After each batch:

### Coverage by index

```sql
SELECT
    mi.index_code,
    mi.index_name,
    COUNT(idp.trade_date) AS price_rows,
    MIN(idp.trade_date) AS first_trade_date,
    MAX(idp.trade_date) AS last_trade_date
FROM market_indexes mi
LEFT JOIN index_daily_prices idp
    ON mi.market_index_id = idp.market_index_id
GROUP BY
    mi.market_index_id,
    mi.index_code,
    mi.index_name
ORDER BY mi.index_code;
```

### Duplicate guard (should return **no rows**)

```sql
SELECT
    market_index_id,
    trade_date,
    COUNT(*) AS duplicate_count
FROM index_daily_prices
GROUP BY market_index_id, trade_date
HAVING COUNT(*) > 1;
```

---

## 8. Komodo verification (read-only UI)

After inserts, in the browser:

1. **Market Data** — index coverage table and KPIs should show non-zero index rows where data exists.
2. **Price Import Readiness** — **Benchmark indexes** should move from **Not started** toward **Partial** or **Loaded** as `indexes_with_any_prices` catches up to `total_indexes`.
3. **Overall** readiness often moves to **Partial** while security prices are still empty.
4. **Recommended next action** should shift toward **event-linked security prices** (still loaded outside Komodo / manual SQL later if you follow the same pattern).

No web app deploy is required for this to update — only DB rows and refresh the page.

---

## 9. Files in this repo

| File | Purpose |
|------|---------|
| [`manual_price_import_workflow.md`](manual_price_import_workflow.md) | This workflow (you are here) |
| [`index_symbol_source_notes.md`](index_symbol_source_notes.md) | Optional provenance / URL / symbol scratchpad |

---

## 10. Out of scope for this doc

- Automated downloaders, Python importers, paid APIs.
- Komodo PHP write routes or import buttons.
- Changing `index_daily_prices` schema without a separate approval step.

---

_Last updated: manual benchmark SQL import planning (zero-dollar, CLI-only loads)._

# Pre-import checklist — benchmark & security prices

Use this before loading **`index_daily_prices`** and **`security_daily_prices`** from **external** tools. Komodo remains read-only; it does not run imports.

---

## A. Database checks to run manually

Run in MariaDB (or your SQL client) against `gecko_research_database_prod`. Adjust only if your schema differs.

### Market indexes

```sql
SELECT
    market_index_id,
    index_code,
    index_name,
    country_code,
    currency_code
FROM market_indexes
ORDER BY market_index_id;
```

### Index price table structure

```sql
SHOW COLUMNS FROM index_daily_prices;
```

### Security price table structure

```sql
SHOW COLUMNS FROM security_daily_prices;
```

### Data sources

```sql
SELECT
    data_source_id,
    source_name,
    source_type,
    base_url
FROM data_sources
ORDER BY data_source_id;
```

### Market data import plan (by role)

```sql
SELECT
    price_import_role,
    COUNT(*) AS security_count
FROM vw_market_data_import_plan
GROUP BY price_import_role
ORDER BY price_import_role;
```

---

## B. Expected current state before import

Align expectations with Komodo telemetry before you load prices:

- **`index_daily_prices`** = 0 rows  
- **`security_daily_prices`** = 0 rows  
- **Benchmark readiness** (Market Data / price import readiness) = **Not started** (or equivalent) until index bars exist  
- **Event-linked** readiness = **Not started** until those series have rows / window coverage  
- **Comparison / unlinked** readiness = **Not started** likewise  
- **Event-study results** = empty (`event_study_runs` / `event_study_results`)

---

## C. Recommended import order

1. **Benchmark / index** daily prices (`index_daily_prices`)  
2. **Event-linked** securities (`security_daily_prices`)  
3. **Comparison / unlinked** securities (`security_daily_prices`)  
4. **Coverage QA** (reload Komodo Market Data; compare to suggested windows)  
5. **Event-study computation** later, outside Komodo  

---

## D. Komodo pages to check after import

After each major batch (especially indexes, then event-linked, then comparison):

- **Market Data** — coverage, roles, price import readiness  
- **Companies** — securities still in scope; notes if any  
- **Company detail** — sample tickers you care about  
- **Events** — unchanged by price load, but useful for cross-checking linkage context  
- **Pipeline** — narrative should move as counts change  

---

## E. Safety reminder

**Price import must run through external scripts, ETL, or CLI — not through the Komodo web UI.** Komodo v0.0.2 has no import buttons and performs no writes.

---

_See also: [`komodo_v0_0_2_milestone.md`](komodo_v0_0_2_milestone.md), [`developer_checks.md`](developer_checks.md)._

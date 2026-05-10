# Komodo v0.0.2 — milestone note

Concise project snapshot for closing the read-only web portal milestone before external price import work.

---

## A. Milestone summary

**Komodo v0.0.2** is a read-only PHP/XAMPP **cybersecurity–finance research portal** for the MariaDB database `gecko_research_database_prod`. It supports **event-study preparation**, **data readiness review**, and **audit traceability** (human-facing labels plus technical identifiers). It is **not** a trading, execution, or investment-advice system.

---

## B. What the portal can currently do

- Connect to MariaDB safely (PDO, whitelist reads).
- Operate in **offline**, **live**, or **degraded** (partial metrics) modes.
- Show **dataset** table inventory and row counts.
- Show **market data** coverage and **price import readiness** (benchmark indexes, event-linked vs comparison/unlinked securities, suggested windows).
- Show **Companies** list and **Company detail** drilldown.
- Show **Events** list (cyber events, dates, linkage signals).
- Show **Research quality**, **Data gaps**, and **Pipeline** narrative pages.
- Preserve **human-readable labels** with **technical traceability** (subtle codes, tooltips, collapsed detail where applicable).
- Run local **developer checks** (lint, smoke, DB check, security scan — see [`developer_checks.md`](developer_checks.md)).

---

## C. Current confirmed data state

Baseline counts as verified for this milestone (live `COUNT(*)` against the current corpus):

| Object / signal | Rows |
|-----------------|-----:|
| companies | 68 |
| securities | 69 |
| cyber_events | 50 |
| cyber_event_dates | 101 |
| — disclosure dates | 50 |
| — first_trading_day dates | 50 |
| — discovery dates | 1 |
| cyber_event_features | 50 |
| cyber_event_impacts | 50 |
| cyber_event_securities | 50 |
| market_calendar | 5113 |
| security_daily_prices | 0 |
| index_daily_prices | 0 |
| cyber_event_sources | 0 |
| event_study_runs | 0 |
| event_study_results | 0 |

*Date-type breakdown is informational; re-verify with SQL if the schema or corpus changes.*

---

## D. Read-only safety model

- **SELECT-only** web app (whitelist table/view names; prepared counts and scoped reads).
- No edit forms, no import buttons, no DML/DDL from the web UI.
- `app/config/local.php` is **gitignored**; credentials stay out of version control.
- No raw PDO errors surfaced to the browser in normal operation; degraded/offline messaging instead.
- Safe **`?page=`** route allowlist (`app/config/pages.php`).
- Developer checks: smoke (`tools/komodo_smoke.php`), DB check (`tools/komodo_db_check.php`), security tripwire (`tools/komodo_security_scan.php`), syntax lint (`tools/lint_all.bat`). Optional one-shot: `tools/check_all.bat`.

---

## E. Important pages

| Page | Purpose (short) |
|------|-----------------|
| **Dashboard** | Connection status, workflow phase, high-level counts, next actions |
| **Dataset** | Core table inventory |
| **Companies** | Company/security listing, linkage and plan signals |
| **Company detail** | Drilldown for one company |
| **Events** | Cyber event listing and research readiness signals |
| **Market Data** | Price coverage, roles, price import readiness |
| **Research quality** | Diagnostic view row counts |
| **Data gaps** | Gap narrative from interpreted counts |
| **Pipeline** | Roadmap / phase copy |

---

## F. Known limitations

- No **Event detail** page yet.
- No **Research queues** page yet.
- No **price importer** in the app (imports are **external**).
- **Source provenance** table empty (`cyber_event_sources` = 0) at milestone close.
- No **event-study results** yet (`event_study_runs` / `event_study_results` empty).
- No rich **search/filter** UI yet.
- **Pagination** helpers not extracted as shared library yet.
- Some UI/code debt **intentionally deferred** to keep v0.0.2 read-only and small.
- **Historical docs** (e.g. `komodo_v0_2_plan.md`) may still say **v0.2**; they describe earlier planning, not the current version label.

---

## G. Next phase — benchmark price import

1. Audit **`market_indexes`** (definitions you intend to load).
2. Audit **`index_daily_prices`** schema and constraints.
3. Optional: track provider metadata in docs or a private import log (Komodo does not store `data_sources` for prices).
4. Load **`index_daily_prices`** using an **external** CLI/script (not the web UI).
5. Refresh Komodo **Market Data** (coverage + price import readiness).
6. Load **event-linked** security prices (`security_daily_prices`).
7. Load **comparison / unlinked** security prices.
8. Run **coverage QA**, then **event-study computation** in your research stack **later** (still outside Komodo unless explicitly changed in a future milestone).

See also: [`pre_import_checklist.md`](pre_import_checklist.md).

---

_Last updated: Komodo v0.0.2 milestone close._

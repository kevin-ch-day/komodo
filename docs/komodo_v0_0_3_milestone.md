# Komodo v0.0.3 — milestone note

UX and responsive polish on top of the **v0.0.2** read-only portal baseline. Full portal scope and data snapshot remain as documented in [`komodo_v0_0_2_milestone.md`](komodo_v0_0_2_milestone.md) unless noted below.

---

## A. Summary

- **Version:** `KOMODO_APP_VERSION` = **0.0.3** (`app/lib/dashboard_context.php`).
- **Still read-only:** no web imports, no DML/DDL from the UI — same safety model as v0.0.2.

---

## B. UX and information architecture

- Clear split between **Price Worklist** (actions), **company drilldown** (context and per-security tables), and **Price Audit** (proof / diagnostics).
- **Market import triage** tables use **labeled-mobile** layout across modes so narrow viewports show field labels on stacked rows.
- **Dashboard**, **Dataset**, **Research quality**, **Data gaps**, and **Price Audit** tables extended with **`data-table--labeled-mobile`** and **`data-label`** where it improves scanability on small screens.

---

## C. CSS / layout fixes

- **Sticky table headers** are disabled below the main mobile breakpoint so they do not compete with the **sticky top navigation** (z-index / overlap).
- **Labeled card tables** treat **`th[data-label]`** like labeled cells so row-header columns (e.g. Price Audit role summary) get correct mobile labels.
- **Long primary labels** in tables use **`overflow-wrap: anywhere`** to avoid horizontal overflow.

---

## D. Deferred (unchanged intent)

- Shared breakpoint tokens across all CSS, optional sidebar scroll/max-height tuning, and richer mobile “summary first / details expandable” patterns remain **future** work — not required for this tag.

---

## E. Checks before tag / push

- `tools\lint_all.bat`
- `tools\komodo_smoke.php` (includes CSS bundle check)
- `tools\komodo_security_scan.php`
- `tools\check_all.bat` (above plus `komodo_db_check.php` — needs DB; exit 2 if counts unavailable)
- Optional: [`smoke_test_checklist.md`](smoke_test_checklist.md) in a browser

---

_Last updated: Komodo v0.0.3 release prep._

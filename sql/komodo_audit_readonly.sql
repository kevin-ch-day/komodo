-- =============================================================================
-- komodo_audit_readonly.sql — read-only audits vs Komodo coverage logic
-- =============================================================================
--
-- Usage (from repo root or full path):
--   mysql -u root gecko_research_database_prod < sql/komodo_audit_readonly.sql
--
-- On Windows PowerShell, use cmd for redirection:
--   cmd /c "D:\Windows\xampp\mysql\bin\mysql.exe -u root gecko_research_database_prod < D:\...\sql\komodo_audit_readonly.sql"
--
-- Span/slack queries below use slack = 7 calendar days — keep in sync with
-- KOMODO_TRIAGE_WINDOW_SLACK_DAYS in app/lib/market_data_queries.php.
--
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Quick inventory
-- ---------------------------------------------------------------------------
SELECT 'plan_rows' AS k, COUNT(*) AS v FROM vw_market_data_import_plan
UNION ALL SELECT 'securities', COUNT(*) FROM securities
UNION ALL SELECT 'security_daily_prices', COUNT(*) FROM security_daily_prices
UNION ALL SELECT 'index_daily_prices', COUNT(*) FROM index_daily_prices
UNION ALL SELECT 'vw_us_trading_days', COUNT(*) FROM vw_us_trading_days;

SELECT price_import_role, COUNT(*) AS n
FROM vw_market_data_import_plan
GROUP BY price_import_role
ORDER BY price_import_role;

-- ---------------------------------------------------------------------------
-- 2) Consistency (expect zeros)
-- ---------------------------------------------------------------------------
SELECT 'role_vs_linked_mismatch' AS check_id, COUNT(*) AS n
FROM vw_market_data_import_plan
WHERE (linked_event_count > 0 AND price_import_role <> 'event_linked_security')
   OR (linked_event_count = 0 AND price_import_role = 'event_linked_security');

SELECT 'plan_orphan_securities' AS check_id, COUNT(*) AS n
FROM vw_market_data_import_plan p
LEFT JOIN securities s ON s.security_id = p.security_id
WHERE s.security_id IS NULL;

SELECT 'prices_orphan_security_id' AS check_id, COUNT(*) AS n
FROM security_daily_prices sdp
LEFT JOIN securities s ON s.security_id = sdp.security_id
WHERE s.security_id IS NULL;

SELECT 'duplicate_security_trade_dates' AS check_id, COUNT(*) AS n
FROM (
  SELECT security_id, trade_date
  FROM security_daily_prices
  GROUP BY security_id, trade_date
  HAVING COUNT(*) > 1
) z;

-- ---------------------------------------------------------------------------
-- 3) Mirror komodo_security_coverage_status (slack = 7) — distribution
-- ---------------------------------------------------------------------------
SELECT audit_status, COUNT(*) AS n
FROM (
  SELECT
    CASE
      WHEN px.c IS NULL OR px.c = 0 THEN 'not_started'
      WHEN p.suggested_import_start_date IS NULL OR p.suggested_import_end_date IS NULL THEN 'has_prices_window_unknown'
      WHEN px.fd IS NULL OR px.ld IS NULL THEN 'partial_unknown_dates'
      ELSE CASE
        WHEN NOT (
          DATE(px.fd) > DATE(p.suggested_import_start_date)
          AND DATEDIFF(DATE(px.fd), DATE(p.suggested_import_start_date)) > 7
        ) AND NOT (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'covers_suggested_window'
        WHEN (
          DATE(px.fd) > DATE(p.suggested_import_start_date)
          AND DATEDIFF(DATE(px.fd), DATE(p.suggested_import_start_date)) > 7
        ) AND (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'missing_end_window'
        WHEN (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'missing_end_window'
        ELSE 'missing_start_window'
      END
    END AS audit_status
  FROM vw_market_data_import_plan p
  LEFT JOIN (
    SELECT
      security_id,
      COUNT(*) AS c,
      MIN(trade_date) AS fd,
      MAX(trade_date) AS ld
    FROM security_daily_prices
    GROUP BY security_id
  ) px ON px.security_id = p.security_id
) x
GROUP BY audit_status
ORDER BY audit_status;

-- ---------------------------------------------------------------------------
-- 4) All plan rows that are not covers_suggested_window (detail)
-- ---------------------------------------------------------------------------
SELECT
  audit_status,
  ticker_symbol,
  price_import_role,
  DATE(suggested_import_start_date) AS win_start,
  DATE(suggested_import_end_date) AS win_end,
  DATE(fd) AS first_bar,
  DATE(ld) AS last_bar
FROM (
  SELECT
    p.ticker_symbol,
    p.price_import_role,
    p.suggested_import_start_date,
    p.suggested_import_end_date,
    px.fd,
    px.ld,
    CASE
      WHEN px.c IS NULL OR px.c = 0 THEN 'not_started'
      WHEN p.suggested_import_start_date IS NULL OR p.suggested_import_end_date IS NULL THEN 'has_prices_window_unknown'
      WHEN px.fd IS NULL OR px.ld IS NULL THEN 'partial_unknown_dates'
      ELSE CASE
        WHEN NOT (
          DATE(px.fd) > DATE(p.suggested_import_start_date)
          AND DATEDIFF(DATE(px.fd), DATE(p.suggested_import_start_date)) > 7
        ) AND NOT (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'covers_suggested_window'
        WHEN (
          DATE(px.fd) > DATE(p.suggested_import_start_date)
          AND DATEDIFF(DATE(px.fd), DATE(p.suggested_import_start_date)) > 7
        ) AND (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'missing_end_window'
        WHEN (
          DATE(px.ld) < DATE(p.suggested_import_end_date)
          AND DATEDIFF(DATE(p.suggested_import_end_date), DATE(px.ld)) > 7
        ) THEN 'missing_end_window'
        ELSE 'missing_start_window'
      END
    END AS audit_status
  FROM vw_market_data_import_plan p
  LEFT JOIN (
    SELECT
      security_id,
      COUNT(*) AS c,
      MIN(trade_date) AS fd,
      MAX(trade_date) AS ld
    FROM security_daily_prices
    GROUP BY security_id
  ) px ON px.security_id = p.security_id
) x
WHERE audit_status <> 'covers_suggested_window'
ORDER BY audit_status, ticker_symbol;

-- ---------------------------------------------------------------------------
-- 5) 2014 comparison floor (expect only FTNT, PANW with start_date < 2014-01-01)
-- ---------------------------------------------------------------------------
SELECT ticker_symbol, price_import_role, linked_event_count,
       DATE(start_date) AS natural_start, DATE(suggested_import_start_date) AS plan_start
FROM vw_market_data_import_plan
WHERE linked_event_count = 0 AND start_date < '2014-01-01'
ORDER BY ticker_symbol;

-- ---------------------------------------------------------------------------
-- 6) Quick density — distinct trade_date anywhere in [suggested_start, suggested_end]
--     vs count of vw_us_trading_days in that range.
--     Counts weekend/holiday bars if present; use section 7 for stricter alignment.
-- ---------------------------------------------------------------------------
SELECT
  p.ticker_symbol,
  (SELECT COUNT(DISTINCT sdp.trade_date)
   FROM security_daily_prices sdp
   WHERE sdp.security_id = p.security_id
     AND sdp.trade_date BETWEEN DATE(p.suggested_import_start_date) AND DATE(p.suggested_import_end_date)
  ) AS loaded_days_in_win,
  (SELECT COUNT(*)
   FROM vw_us_trading_days u
   WHERE u.calendar_date BETWEEN DATE(p.suggested_import_start_date) AND DATE(p.suggested_import_end_date)
  ) AS expected_us_td_in_win,
  ROUND(100 * (SELECT COUNT(DISTINCT sdp.trade_date)
               FROM security_daily_prices sdp
               WHERE sdp.security_id = p.security_id
                 AND sdp.trade_date BETWEEN DATE(p.suggested_import_start_date) AND DATE(p.suggested_import_end_date)
              ) / NULLIF((SELECT COUNT(*)
                          FROM vw_us_trading_days u
                          WHERE u.calendar_date BETWEEN DATE(p.suggested_import_start_date) AND DATE(p.suggested_import_end_date)
                         ), 0), 1) AS pct_density
FROM vw_market_data_import_plan p
WHERE p.ticker_symbol IN ('DIS', 'FTNT', 'PANW', 'TSLA', 'JPM', 'MCD')
ORDER BY p.ticker_symbol;

-- ---------------------------------------------------------------------------
-- 7) Aligned density — bars only on dates that exist in vw_us_trading_days;
--    loaded_trading_days = intersection count. expected_trading_days = US trading
--    days in the suggested window. density_ratio = loaded / expected.
--    Read-only UI: komodo_fetch_aligned_daily_density() in app/lib/market_data_queries.php
--    (Price audit → "Aligned daily density"; full plan, not the sample filter below).
--    Edit WHERE p.ticker_symbol for ad-hoc single-ticker probes.
--    Note: MIN/MAX sdp here are over the join; for global first/last bar use
--    security_daily_prices aggregates without the calendar join.
-- ---------------------------------------------------------------------------
SELECT
  p.ticker_symbol,
  p.price_import_role,
  p.start_date AS natural_start_date,
  p.suggested_import_start_date,
  p.suggested_import_end_date,
  MIN(sdp.trade_date) AS first_loaded_trade_date,
  MAX(sdp.trade_date) AS last_loaded_trade_date,
  COUNT(DISTINCT td.calendar_date) AS expected_trading_days,
  COUNT(DISTINCT sdp.trade_date) AS loaded_trading_days,
  ROUND(
    COUNT(DISTINCT sdp.trade_date) / NULLIF(COUNT(DISTINCT td.calendar_date), 0),
    4
  ) AS density_ratio
FROM vw_market_data_import_plan p
LEFT JOIN vw_us_trading_days td
  ON td.calendar_date BETWEEN p.suggested_import_start_date
                          AND p.suggested_import_end_date
LEFT JOIN security_daily_prices sdp
  ON sdp.security_id = p.security_id
 AND sdp.trade_date = td.calendar_date
WHERE p.ticker_symbol IN ('FTNT', 'PANW')
GROUP BY
  p.security_id,
  p.ticker_symbol,
  p.price_import_role,
  p.start_date,
  p.suggested_import_start_date,
  p.suggested_import_end_date
ORDER BY p.ticker_symbol;

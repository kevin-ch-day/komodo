-- =============================================================================
-- komodo_schema_introspection.sql — run in MariaDB to capture schema context
-- =============================================================================
--
-- Usage:
--   USE gecko_research_database_prod;
--   SOURCE D:/Windows/xampp/htdocs/komodo/sql/komodo_schema_introspection.sql;
--
-- Or run sections one at a time. Paste query results (or \T outfile) when
-- sharing with someone reviewing Komodo + this database.
--
-- =============================================================================

SET NAMES utf8mb4;

SELECT DATABASE() AS current_database, VERSION() AS mariadb_version, NOW() AS captured_at;

-- ---------------------------------------------------------------------------
-- 1) Tables vs views (inventory)
-- ---------------------------------------------------------------------------
SELECT
  TABLE_TYPE,
  COUNT(*) AS object_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
GROUP BY TABLE_TYPE
ORDER BY TABLE_TYPE;

-- ---------------------------------------------------------------------------
-- 2) Komodo-adjacent objects (name match; edit list if needed)
-- ---------------------------------------------------------------------------
SELECT
  TABLE_NAME,
  TABLE_TYPE,
  ENGINE,
  TABLE_ROWS,
  ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
  TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    TABLE_NAME IN (
      'securities',
      'companies',
      'security_daily_prices',
      'market_indexes',
      'index_daily_prices',
      'cyber_events',
      'cyber_event_securities',
      'cyber_event_sources',
      'event_study_runs',
      'event_study_results'
    )
    OR TABLE_NAME LIKE 'vw\_%' ESCAPE '\\'
    OR TABLE_NAME LIKE '%import%'
    OR TABLE_NAME LIKE '%market%'
  )
ORDER BY TABLE_TYPE DESC, TABLE_NAME;

-- ---------------------------------------------------------------------------
-- 3) Column names Komodo code often references (find typos / renames)
-- ---------------------------------------------------------------------------
SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT,
  COLUMN_KEY,
  EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'securities',
    'companies',
    'security_daily_prices',
    'market_indexes',
    'index_daily_prices',
    'cyber_event_securities',
    'cyber_events',
    'cyber_event_sources',
    'event_study_runs',
    'event_study_results'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- ---------------------------------------------------------------------------
-- 4) Any column named like import_notes / suggested_import (base tables only)
--    TABLE_TYPE lives on information_schema.TABLES, not COLUMNS — join required.
-- ---------------------------------------------------------------------------
SELECT
  c.TABLE_NAME,
  c.COLUMN_NAME,
  c.COLUMN_TYPE
FROM information_schema.COLUMNS c
JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
 AND t.TABLE_NAME = c.TABLE_NAME
WHERE c.TABLE_SCHEMA = DATABASE()
  AND t.TABLE_TYPE = 'BASE TABLE'
  AND (
    c.COLUMN_NAME LIKE '%import%note%'
    OR c.COLUMN_NAME LIKE 'suggested\_import%' ESCAPE '\\'
    OR c.COLUMN_NAME IN ('import_notes', 'notes', 'ticker_symbol', 'security_id')
  )
ORDER BY c.TABLE_NAME, c.COLUMN_NAME;

-- ---------------------------------------------------------------------------
-- 5) Foreign keys touching core fact tables (ingest / joins)
-- ---------------------------------------------------------------------------
SELECT
  k.TABLE_NAME,
  k.COLUMN_NAME,
  k.CONSTRAINT_NAME,
  k.REFERENCED_TABLE_NAME,
  k.REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.TABLE_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
  AND k.TABLE_NAME IN (
    'security_daily_prices',
    'index_daily_prices',
    'securities',
    'cyber_event_securities',
    'event_study_runs',
    'event_study_results'
  )
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;

-- ---------------------------------------------------------------------------
-- 6) View list (full names) — run SHOW CREATE separately for heavy hitters
-- ---------------------------------------------------------------------------
SELECT
  TABLE_NAME AS view_name
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- ---------------------------------------------------------------------------
-- 7) Row counts (fast approximate for InnoDB; good enough for sanity)
-- ---------------------------------------------------------------------------
SELECT
  'securities' AS tbl,
  COUNT(*) AS row_count
FROM securities
UNION ALL
SELECT 'security_daily_prices', COUNT(*) FROM security_daily_prices
UNION ALL
SELECT 'companies', COUNT(*) FROM companies
UNION ALL
SELECT 'market_indexes', COUNT(*) FROM market_indexes
UNION ALL
SELECT 'index_daily_prices', COUNT(*) FROM index_daily_prices
UNION ALL
SELECT 'cyber_events', COUNT(*) FROM cyber_events
UNION ALL
SELECT 'cyber_event_securities', COUNT(*) FROM cyber_event_securities
UNION ALL
SELECT 'event_study_runs', COUNT(*) FROM event_study_runs
UNION ALL
SELECT 'event_study_results', COUNT(*) FROM event_study_results;

-- If any table is missing, that SELECT will error — comment out the line.

-- ---------------------------------------------------------------------------
-- 8) Plan / triage shape (what Komodo reads)
-- ---------------------------------------------------------------------------
SELECT
  price_import_role,
  COUNT(*) AS n
FROM vw_security_price_import_targets
GROUP BY price_import_role
ORDER BY price_import_role;

SELECT
  price_import_role,
  COUNT(*) AS n
FROM vw_market_data_import_plan
GROUP BY price_import_role
ORDER BY price_import_role;

SELECT
  ticker_symbol,
  import_notes
FROM vw_market_data_import_plan
WHERE import_notes IS NOT NULL
ORDER BY ticker_symbol;

-- ---------------------------------------------------------------------------
-- 9) Optional: paste SHOW CREATE output in chat for these (run one per line)
--    Note: vw_market_data_import_plan.import_notes may be a CASE expression only
--    (no base-table column). In that case the durable fix is CREATE OR REPLACE
--    VIEW — see sql/patch_jbsay_import_notes.sql in the Komodo repo.
-- ---------------------------------------------------------------------------
-- SHOW CREATE VIEW vw_market_data_import_plan \G
-- SHOW CREATE VIEW vw_security_price_import_targets \G
-- SHOW CREATE VIEW vw_event_study_event_readiness \G
-- SHOW CREATE TABLE securities \G
-- SHOW CREATE TABLE security_daily_prices \G

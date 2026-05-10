-- =============================================================================
-- remove_data_sources.sql — drop data_sources table and data_source_id columns
-- =============================================================================
--
-- IMPORTANT — run manually only after review and backup.
-- Do NOT run from Komodo or automated tooling until approved.
--
-- Backup reminder (example):
--   mysqldump -u ... -p gecko_research_database_prod > backup_before_remove_data_sources.sql
--
-- Dependency summary (this repo):
--   FOREIGN KEYs into data_sources from:
--     cyber_event_sources.data_source_id
--     event_study_runs.data_source_id
--     index_daily_prices.data_source_id
--     security_daily_prices.data_source_id
--
-- If constraint names differ on your server, list them first:
--   SELECT CONSTRAINT_NAME, TABLE_NAME
--   FROM information_schema.REFERENTIAL_CONSTRAINTS
--   WHERE CONSTRAINT_SCHEMA = DATABASE()
--     AND REFERENCED_TABLE_NAME = 'data_sources';
--
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE cyber_event_sources
    DROP FOREIGN KEY fk_cyber_event_sources_data_source;

ALTER TABLE event_study_runs
    DROP FOREIGN KEY fk_event_study_runs_source;

ALTER TABLE index_daily_prices
    DROP FOREIGN KEY fk_index_daily_prices_source;

ALTER TABLE security_daily_prices
    DROP FOREIGN KEY fk_security_daily_prices_source;

ALTER TABLE cyber_event_sources
    DROP COLUMN data_source_id;

ALTER TABLE event_study_runs
    DROP COLUMN data_source_id;

ALTER TABLE index_daily_prices
    DROP COLUMN data_source_id;

ALTER TABLE security_daily_prices
    DROP COLUMN data_source_id;

DROP TABLE data_sources;

-- =============================================================================
-- Verification (expect zero rows from each query after success)
-- =============================================================================

-- No columns named data_source_id should remain:
SELECT
    TABLE_NAME,
    COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'data_source_id'
ORDER BY TABLE_NAME;

-- No foreign keys pointing at data_sources:
SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME = 'data_sources'
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- data_sources table should be gone (this should error "Table doesn't exist"
-- or return empty from SHOW TABLES depending on client — optional):
-- SHOW TABLES LIKE 'data_sources';

-- =============================================================================
-- patch_jbsay_import_notes.sql — vw_market_data_import_plan (notes + window floor)
-- =============================================================================
--
-- Replaces view `vw_market_data_import_plan` (from `vw_security_price_import_targets` `t`).
--
-- Policies embedded here:
--   1. import_notes — per-ticker CASE (FB, META, JBSAY, IPO list, TSLA/MARA, etc.).
--   2. suggested_import_start_date — event-linked: unchanged (readiness min − 220d).
--      Comparison/control (linked_event_count = 0): GREATEST(natural start, 2014-01-01)
--      so IPO-era securities.start_date does not force unnecessary backfill; event-linked
--      rows (e.g. TGT 2013) are unchanged. Override for older comparison history is TBD.
--
-- `vw_security_price_import_targets` is unchanged — it still exposes natural `start_date`.
--
-- Run manually after backup/review. Omit trailing `\G;` in clients that split statements.
--
-- Komodo PHP may also merge JBSAY import_notes when DB is behind; after this patch,
-- DB and app stay aligned for notes.
--
-- =============================================================================

SET NAMES utf8mb4;

CREATE OR REPLACE
ALGORITHM = UNDEFINED
VIEW `vw_market_data_import_plan` AS
SELECT
  `t`.`security_id` AS `security_id`,
  `t`.`ticker_symbol` AS `ticker_symbol`,
  `t`.`display_name` AS `display_name`,
  `t`.`security_name` AS `security_name`,
  `t`.`exchange_code` AS `exchange_code`,
  `t`.`price_import_role` AS `price_import_role`,
  `t`.`linked_event_count` AS `linked_event_count`,
  `t`.`start_date` AS `start_date`,
  `t`.`end_date` AS `end_date`,
  `t`.`is_active` AS `is_active`,
  CASE
    WHEN `t`.`linked_event_count` > 0 THEN (
      SELECT MIN(`v`.`first_trading_day`)
      FROM `vw_event_study_event_readiness` `v`
      WHERE `v`.`security_id` = `t`.`security_id`
    ) - INTERVAL 220 DAY
    ELSE GREATEST(COALESCE(`t`.`start_date`, DATE('2014-01-01')), DATE('2014-01-01'))
  END AS `suggested_import_start_date`,
  CASE
    WHEN `t`.`linked_event_count` > 0 THEN (
      SELECT MAX(`v`.`first_trading_day`)
      FROM `vw_event_study_event_readiness` `v`
      WHERE `v`.`security_id` = `t`.`security_id`
    ) + INTERVAL 220 DAY
    ELSE CURDATE()
  END AS `suggested_import_end_date`,
  CASE
    WHEN `t`.`ticker_symbol` = 'FB' THEN 'Historical ticker; import only through end_date / ticker change boundary.'
    WHEN `t`.`ticker_symbol` = 'META' THEN 'Current Meta ticker; separate from historical FB event ticker.'
    WHEN `t`.`ticker_symbol` = 'JBSAY' THEN 'OTC ADR; standard export source unavailable. Needs alternate historical source for 2020-10-24 to 2022-01-07.'
    WHEN `t`.`ticker_symbol` IN ('SWI', 'DNUT', 'DOLE', 'DBX', 'COIN', 'S', 'ZS', 'UBER', 'OKTA', 'CRWD') THEN 'Check IPO/listing or availability window before import.'
    WHEN `t`.`ticker_symbol` IN ('TSLA', 'MARA') THEN 'High-volatility comparison security; included to test whether benchmark, peer, and comparison-group results remain stable when volatile market behavior is present.'
    ELSE NULL
  END AS `import_notes`
FROM `vw_security_price_import_targets` `t`
WHERE `t`.`is_active` = 1
   OR `t`.`linked_event_count` > 0;

-- Verify examples (keep spaces/newlines — do not join keywords like end_dateFROM):
-- SELECT ticker_symbol, price_import_role, linked_event_count, start_date,
--        suggested_import_start_date, suggested_import_end_date
-- FROM vw_market_data_import_plan
-- WHERE ticker_symbol IN ('FTNT','PANW','DIS','TSLA','FB','SWI','TGT','JBSAY')
-- ORDER BY ticker_symbol;
--
-- One-line copy (spaces required before FROM / WHERE / ORDER):
-- SELECT ticker_symbol, price_import_role, linked_event_count, start_date, suggested_import_start_date, suggested_import_end_date FROM vw_market_data_import_plan WHERE ticker_symbol IN ('FTNT','PANW','DIS','TSLA','FB','SWI','TGT','JBSAY') ORDER BY ticker_symbol;

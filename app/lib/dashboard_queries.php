<?php

declare(strict_types=1);

/**
 * Read-only dashboard COUNT(*) helpers. SQL is SELECT-only; identifiers are whitelist-only.
 */

/** @var list<string> */
const KOMODO_DASHBOARD_TABLES = [
    'companies',
    'securities',
    'cyber_events',
    'cyber_event_dates',
    'cyber_event_features',
    'cyber_event_impacts',
    'cyber_event_securities',
    'market_calendar',
    'security_daily_prices',
    'index_daily_prices',
    'cyber_event_sources',
    'event_study_runs',
    'event_study_results',
];

/** @var list<string> */
const KOMODO_DASHBOARD_VIEWS = [
    'vw_event_study_event_readiness',
    'vw_security_price_import_targets',
    'vw_market_data_import_plan',
    'vw_us_trading_days',
    'vw_event_window_boundaries',
    'vw_event_same_ticker_window_overlaps',
    'vw_event_nearby_cyber_clusters',
    'vw_event_contamination_flags',
    'vw_event_impact_quality_flags',
    'vw_event_research_readiness_flags',
];

/**
 * Whitelist-safe COUNT(*) for a table or view name. Used internally; invalid names throw.
 *
 * @throws InvalidArgumentException|RuntimeException
 */
function komodo_count_rows_exact(PDO $pdo, string $name): int
{
    $allowed = array_merge(KOMODO_DASHBOARD_TABLES, KOMODO_DASHBOARD_VIEWS);
    if (!in_array($name, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table or view name.');
    }

    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $name)) {
        throw new InvalidArgumentException('Malformed identifier.');
    }

    $quoted = '`' . str_replace('`', '``', $name) . '`';
    $sql = 'SELECT COUNT(*) AS c FROM ' . $quoted;

    $stmt = $pdo->query($sql);
    if ($stmt === false) {
        throw new RuntimeException('Count query failed.');
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false || !array_key_exists('c', $row)) {
        throw new RuntimeException('Unexpected COUNT result.');
    }

    return (int) $row['c'];
}

/**
 * Safe row count — one failure does not affect other callers.
 *
 * @return array{identifier: string, count: ?int, status: string}
 */
function komodo_count_rows_safe(PDO $pdo, string $name): array
{
    try {
        $count = komodo_count_rows_exact($pdo, $name);

        return [
            'identifier' => $name,
            'count' => $count,
            'status' => 'ok',
        ];
    } catch (Throwable $e) {
        error_log('Komodo: row count failed for whitelist object.');
        return [
            'identifier' => $name,
            'count' => null,
            'status' => 'unavailable',
        ];
    }
}

/**
 * @return array<string, array{identifier: string, count: ?int, status: string}>
 */
function komodo_get_table_counts_safe(PDO $pdo): array
{
    $out = [];
    foreach (KOMODO_DASHBOARD_TABLES as $name) {
        $out[$name] = komodo_count_rows_safe($pdo, $name);
    }

    return $out;
}

/**
 * @return array<string, array{identifier: string, count: ?int, status: string}>
 */
function komodo_get_view_counts_safe(PDO $pdo): array
{
    $out = [];
    foreach (KOMODO_DASHBOARD_VIEWS as $name) {
        $out[$name] = komodo_count_rows_safe($pdo, $name);
    }

    return $out;
}

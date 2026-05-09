<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/dashboard_queries.php';

/**
 * Offline reference row counts (documentation only — not verified live).
 *
 * @var array<string, int>
 */
const KOMODO_OFFLINE_TABLE_REFERENCE = [
    'companies' => 68,
    'securities' => 69,
    'cyber_events' => 50,
    'cyber_event_dates' => 101,
    'cyber_event_features' => 50,
    'cyber_event_impacts' => 50,
    'cyber_event_securities' => 50,
    'market_calendar' => 5113,
    'security_daily_prices' => 0,
    'index_daily_prices' => 0,
    'cyber_event_sources' => 0,
    'event_study_runs' => 0,
    'event_study_results' => 0,
];

const KOMODO_APP_VERSION = '0.0.2';

/**
 * Offline analytical view counts (documentation snapshots).
 *
 * @var array<string, int>
 */
const KOMODO_OFFLINE_VIEW_REFERENCE = [
    'vw_event_study_event_readiness' => 50,
    'vw_security_price_import_targets' => 69,
    'vw_market_data_import_plan' => 69,
    'vw_us_trading_days' => 3520,
    'vw_event_window_boundaries' => 350,
    'vw_event_same_ticker_window_overlaps' => 13,
    'vw_event_nearby_cyber_clusters' => 7,
    'vw_event_contamination_flags' => 50,
    'vw_event_impact_quality_flags' => 50,
    'vw_event_research_readiness_flags' => 50,
];

function komodo_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $safe
 */
function komodo_any_metric_unavailable(array $safe): bool
{
    foreach ($safe as $row) {
        if (($row['status'] ?? '') !== 'ok') {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $safe
 */
function komodo_int_if_ok(array $safe, string $key): ?int
{
    if (!isset($safe[$key])) {
        return null;
    }
    $r = $safe[$key];
    if (($r['status'] ?? '') !== 'ok' || $r['count'] === null) {
        return null;
    }

    return $r['count'];
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $live
 */
function komodo_metric_html(bool $offlineMode, array $live, string $key, ?int $reference): string
{
    if ($offlineMode) {
        if ($reference !== null) {
            return '<span class="metric-placeholder" title="Offline reference (not live)">'
                . komodo_e(number_format($reference))
                . '</span>';
        }

        return '<span class="metric-unavailable">—</span>';
    }

    if (!isset($live[$key])) {
        return '<span class="metric-unavailable">—</span>';
    }

    $row = $live[$key];
    if (($row['status'] ?? '') !== 'ok' || $row['count'] === null) {
        return '<span class="metric-unavailable" title="Metric unavailable">—</span>';
    }

    return '<span>' . komodo_e(number_format($row['count'])) . '</span>';
}

/**
 * @return list<array{type: string, text: string}>
 */
function komodo_dashboard_banners(string $status, bool $partialMetrics, string $baseMessage): array
{
    $banners = [];

    if ($status === 'not_configured') {
        $banners[] = ['type' => 'warn', 'text' => $baseMessage];
    } elseif ($status === 'misconfigured') {
        $banners[] = ['type' => 'error', 'text' => $baseMessage];
    } elseif ($status === 'unreachable') {
        $banners[] = ['type' => 'error', 'text' => $baseMessage];
    } elseif ($status === 'connected') {
        $banners[] = ['type' => 'success', 'text' => 'Live database mode.'];
        if ($partialMetrics) {
            $banners[] = ['type' => 'warn', 'text' => 'Some portal metrics could not be loaded.'];
        }
    }

    return $banners;
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tables
 * @return array{id: string, title: string, rationale: string}
 */
function komodo_dashboard_workflow_phase(string $connStatus, array $tables): array
{
    if ($connStatus !== 'connected') {
        return [
            'id' => 'offline',
            'title' => 'Offline',
            'rationale' => 'Configure app/config/local.php and ensure MariaDB is running to see live counts, gaps, and phase guidance.',
        ];
    }

    $events = komodo_int_if_ok($tables, 'cyber_events');
    $mcal = komodo_int_if_ok($tables, 'market_calendar');
    $secPx = komodo_int_if_ok($tables, 'security_daily_prices');
    $idxPx = komodo_int_if_ok($tables, 'index_daily_prices');
    $runs = komodo_int_if_ok($tables, 'event_study_runs');

    $results = komodo_int_if_ok($tables, 'event_study_results');

    if ($events !== null && $events > 0
        && $mcal !== null && $mcal > 0
        && $secPx !== null && $idxPx !== null && $secPx === 0 && $idxPx === 0
    ) {
        return [
            'id' => 'market_import_prep',
            'title' => 'Price import readiness (external loads)',
            'rationale' => 'Core cyber-event metadata, market calendar, event windows, and QA views exist, but benchmark index and security daily price tables are still empty — use an external pipeline to load prices; Komodo stays read-only.',
        ];
    }

    if (($secPx !== null && $secPx > 0) || ($idxPx !== null && $idxPx > 0)) {
        if (($runs === null || $runs === 0) && ($results === null || $results === 0)) {
            return [
                'id' => 'event_study_prep',
                'title' => 'Event-study preparation',
                'rationale' => 'Price history is loading or present; validate windows, source provenance, and QA flags here, then run event-study estimation outside Komodo.',
            ];
        }

        return [
            'id' => 'analysis_review',
            'title' => 'Results Review',
            'rationale' => 'Event-study runs or stored results indicate analysis activity — reconcile outputs against readiness and contamination flags.',
        ];
    }

    return [
        'id' => 'core_dataset',
        'title' => 'Core dataset build',
        'rationale' => 'Continue aligning core identifiers, cyber events, and calendar scaffolding until market and window views stabilize for cybersecurity–finance research.',
    ];
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tables
 * @return list<string>
 */
function komodo_dashboard_next_actions(string $connStatus, array $tables, bool $partial): array
{
    $actions = [];

    if ($connStatus === 'not_configured') {
        $actions[] = 'Copy app/config/local.example.php to app/config/local.php (gitignored), then reload.';
    }

    if ($connStatus === 'misconfigured') {
        $actions[] = 'Fix required keys in local.php: host, port, database, username, password, charset.';
    }

    if ($connStatus === 'unreachable') {
        $actions[] = 'Confirm MariaDB is running in XAMPP and credentials match the target database.';
        return $actions;
    }

    if ($partial && $connStatus === 'connected') {
        $actions[] = 'Repair or restore failing views/tables referenced in this portal.';
        $actions[] = 'Refresh after GRANT/select access or DDL fixes.';
    }

    if ($connStatus !== 'connected') {
        return $actions !== [] ? $actions : ['Review README for local setup steps.'];
    }

    $idxPx = komodo_int_if_ok($tables, 'index_daily_prices');
    $secPx = komodo_int_if_ok($tables, 'security_daily_prices');
    $sources = komodo_int_if_ok($tables, 'cyber_event_sources');
    $runs = komodo_int_if_ok($tables, 'event_study_runs');

    if ($idxPx !== null && $idxPx === 0) {
        $actions[] = 'Pending data load: benchmark index prices (e.g. into index_daily_prices) via your external pipeline — Komodo does not write rows.';
    }

    if ($secPx !== null && $secPx === 0) {
        $actions[] = 'Pending data load: security prices for event-linked names (e.g. into security_daily_prices) via your external pipeline.';
    }

    if ($sources !== null && $sources === 0) {
        $actions[] = 'Add cyber_event_sources provenance rows.';
    }

    if (($runs === null || $runs === 0)
        && $secPx !== null && $idxPx !== null && ($secPx > 0 || $idxPx > 0)) {
        $actions[] = 'After minimum price coverage is confirmed, run event-study estimation in your research environment — not from this portal.';
    }

    if ($secPx !== null && $idxPx !== null && ($secPx > 0 || $idxPx > 0)) {
        $actions[] = 'Compare fresh loads against vw_market_data_import_plan and vw_security_price_import_targets for coverage QA.';
    }

    if ($actions === []) {
        $actions[] = 'Review QA and research readiness views before widening the cyber-event corpus.';
        $actions[] = 'Maintain provenance rows as datasets expand.';
    }

    return $actions;
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tables
 * @return list<array{0: string, 1: string}> Plain-text gap detail (escaped at render).
 */
function komodo_build_gap_rows(bool $offlineMode, array $tables): array
{
    if ($offlineMode) {
        return [
            ['Security daily prices', 'security_daily_prices — empty pending external load (offline reference only).'],
            ['Index daily prices', 'index_daily_prices — empty pending external load (offline reference only).'],
            ['Event sources', 'cyber_event_sources — populate for provenance.'],
            ['Event study outputs', 'event_study_runs / event_study_results — empty prior to pipelines.'],
        ];
    }

    $rows = [];
    $sp = komodo_int_if_ok($tables, 'security_daily_prices');
    if ($sp === null) {
        $rows[] = ['Security daily prices', 'Could not read security_daily_prices (metric unavailable).'];
    } elseif ($sp === 0) {
        $rows[] = ['Security daily prices', 'security_daily_prices reports 0 rows.'];
    }

    $idx = komodo_int_if_ok($tables, 'index_daily_prices');
    if ($idx === null) {
        $rows[] = ['Index daily prices', 'Could not read index_daily_prices (metric unavailable).'];
    } elseif ($idx === 0) {
        $rows[] = ['Index daily prices', 'index_daily_prices reports 0 rows.'];
    }

    $src = komodo_int_if_ok($tables, 'cyber_event_sources');
    if ($src === null) {
        $rows[] = ['Event sources', 'Could not read cyber_event_sources.'];
    } elseif ($src === 0) {
        $rows[] = ['Event sources', 'cyber_event_sources reports 0 rows.'];
    }

    $runs = komodo_int_if_ok($tables, 'event_study_runs');
    $res = komodo_int_if_ok($tables, 'event_study_results');
    if ($runs === null || $res === null) {
        $rows[] = ['Event study outputs', 'Could not read event_study_runs or event_study_results.'];
    } elseif ($runs === 0 && $res === 0) {
        $rows[] = ['Event study outputs', 'No rows in event_study_runs / event_study_results yet.'];
    }

    if ($rows === []) {
        $rows[] = ['No automated gaps surfaced', 'Zero-row diagnostics look clear from table counts alone — sanity-check QA views.'];
    }

    return $rows;
}

function komodo_banner_class(string $type): string
{
    return match ($type) {
        'success' => 'env-note env-note--success',
        'warn' => 'env-note env-note--warn',
        'error' => 'env-note env-note--error',
        default => 'env-note',
    };
}

function komodo_page_visual_mode(string $connState, bool $partialMetrics): string
{
    if ($connState === 'connected') {
        return $partialMetrics ? 'degraded' : 'live';
    }
    if ($connState === 'not_configured') {
        return 'offline';
    }

    return 'unavailable';
}

/**
 * @return array{class: string, text: string}
 */
function komodo_primary_status_badge(string $visualMode): array
{
    return match ($visualMode) {
        'live' => ['class' => 'badge badge--primary badge--live', 'text' => 'Live'],
        'degraded' => ['class' => 'badge badge--primary badge--degraded', 'text' => 'Degraded'],
        'offline' => ['class' => 'badge badge--primary badge--offline', 'text' => 'Offline'],
        default => ['class' => 'badge badge--primary badge--unavailable', 'text' => 'Unavailable'],
    };
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $live
 * @return array{class: string, text: string}
 */
function komodo_metric_badge(bool $offlineMode, array $live, string $key): array
{
    if ($offlineMode) {
        return ['class' => 'badge badge--placeholder', 'text' => 'Placeholder'];
    }

    if (!isset($live[$key]) || (($live[$key]['status'] ?? '') !== 'ok')) {
        return ['class' => 'badge badge--missing', 'text' => 'Missing'];
    }

    $n = $live[$key]['count'];
    if ($n === null) {
        return ['class' => 'badge badge--missing', 'text' => 'Missing'];
    }

    if ($n === 0) {
        $expectedEmpty = [
            'security_daily_prices',
            'index_daily_prices',
            'cyber_event_sources',
            'event_study_runs',
            'event_study_results',
        ];
        if (in_array($key, $expectedEmpty, true)) {
            return ['class' => 'badge badge--zero-muted', 'text' => 'Zero rows'];
        }

        return ['class' => 'badge badge--zero', 'text' => 'Zero rows'];
    }

    return ['class' => 'badge badge--ready', 'text' => 'Count OK'];
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $live
 */
function komodo_stat_card_class(bool $offlineMode, array $live, string $key): string
{
    $classes = ['stat-card'];
    if ($offlineMode) {
        return implode(' ', $classes);
    }

    if (!isset($live[$key]) || (($live[$key]['status'] ?? '') !== 'ok')) {
        $classes[] = 'stat-card--missing';

        return implode(' ', $classes);
    }

    $n = $live[$key]['count'];
    if ($n === null) {
        $classes[] = 'stat-card--missing';

        return implode(' ', $classes);
    }

    if ($n === 0) {
        $gapKeys = [
            'security_daily_prices',
            'index_daily_prices',
            'cyber_event_sources',
            'event_study_runs',
            'event_study_results',
        ];
        if (in_array($key, $gapKeys, true)) {
            $classes[] = 'stat-card--gap';
        }
    }

    return implode(' ', $classes);
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tables
 * @return list<string>
 */
function komodo_phase_status_lines(string $connState, string $phaseId, array $tables, bool $offlineMode): array
{
    if ($offlineMode || $connState !== 'connected') {
        return [
            'Offline / not configured — add app/config/local.php and a running MariaDB instance for live readiness.',
            'Reference numbers on this page are documentation-only until the connection succeeds.',
        ];
    }

    $lines = [];

    switch ($phaseId) {
        case 'market_import_prep':
            $lines[] = 'Price import readiness — core events and calendar are populated; benchmark and security price tables are still empty.';
            $lines[] = 'Next: load benchmark index prices, then event-linked security prices, outside Komodo — see Market Data for coverage QA.';
            break;
        case 'event_study_prep':
            $lines[] = 'Event-study preparation — some market data exists; validate windows and provenance before running estimation outside Komodo.';
            break;
        case 'analysis_review':
            $lines[] = 'Results Review — reconcile stored runs/results with QA and contamination signals.';
            break;
        case 'core_dataset':
            $lines[] = 'Core Dataset Build — continue loading identifiers, events, and calendar scaffolding.';
            break;
        default:
            $lines[] = 'Review connection status and table counts to determine the active research stage.';
            break;
    }

    $src = komodo_int_if_ok($tables, 'cyber_event_sources');
    if ($src === 0) {
        $lines[] = 'Source provenance missing — cyber_event_sources has no rows.';
    }

    $runs = komodo_int_if_ok($tables, 'event_study_runs');
    $res = komodo_int_if_ok($tables, 'event_study_results');
    if ($runs !== null && $res !== null && $runs === 0 && $res === 0) {
        $lines[] = 'Event study not started — event_study_runs and event_study_results are empty.';
    }

    $secPx = komodo_int_if_ok($tables, 'security_daily_prices');
    $idxPx = komodo_int_if_ok($tables, 'index_daily_prices');
    if ($secPx !== null && $idxPx !== null && $secPx === 0 && $idxPx === 0 && $phaseId !== 'market_import_prep') {
        $lines[] = 'Price data still missing for both securities and benchmark indexes.';
    }

    return array_values(array_unique($lines));
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tables
 * @return list<string>
 */
function komodo_pipeline_narrative(string $connState, array $tables, bool $offlineMode): array
{
    if ($offlineMode || $connState !== 'connected') {
        return [
            'MariaDB connection is not live — treat pipeline wording as orientation, not telemetry.',
            'After local.php validates, this section summarizes external data loads, source provenance, and analysis gates from live counts.',
        ];
    }

    $lines = [];

    $events = komodo_int_if_ok($tables, 'cyber_events');
    $lines[] = $events !== null && $events > 0
        ? 'Cyber event identifiers and linkage tables carry rows — data changes stay outside this read-only research portal.'
        : 'Cyber event metadata still thin — stabilize core events before expanding market or QA overlays.';

    $mcal = komodo_int_if_ok($tables, 'market_calendar');
    $lines[] = $mcal !== null && $mcal > 0
        ? 'Market calendar is populated — anchors expected US trading spans for downstream price QA.'
        : 'Market calendar is missing or unreadable — fix DDL or GRANT paths before interpreting market-data plan views.';

    $secPx = komodo_int_if_ok($tables, 'security_daily_prices');
    $idxPx = komodo_int_if_ok($tables, 'index_daily_prices');
    if ($secPx !== null && $idxPx !== null && $secPx === 0 && $idxPx === 0) {
        $lines[] = 'Daily price loads have not landed — securities and benchmarks still show zero rows.';
        $lines[] = 'External benchmark and security price loads are the near-term bottleneck before event-window QA at scale.';
    } elseif (($secPx !== null && $secPx === 0) xor ($idxPx !== null && $idxPx === 0)) {
        $lines[] = 'Security price coverage is asymmetric — reconcile issuer vs benchmark index series after external loads.';
    } elseif ($secPx !== null && $idxPx !== null && $secPx > 0 && $idxPx > 0) {
        $lines[] = 'Both issuer and benchmark price tables contain rows — run coverage QA against import-plan targets.';
    }

    $src = komodo_int_if_ok($tables, 'cyber_event_sources');
    $lines[] = match (true) {
        $src === null => 'Provenance telemetry unavailable — rerun counts after fixing SELECT access.',
        $src === 0 => 'cyber_event_sources is empty — add provenance rows outside Komodo before publishing research results.',
        default => 'Source provenance: rows exist — continue expanding cyber_event_sources with each external ingestion batch.',
    };

    $runs = komodo_int_if_ok($tables, 'event_study_runs');
    $results = komodo_int_if_ok($tables, 'event_study_results');
    if ($runs === null || $results === null) {
        $lines[] = 'Event study outputs unreadable — clear failing objects before layering ML overlays.';
    } elseif ($runs === 0 && $results === 0) {
        $lines[] = 'Event study analysis not started — event_study_runs and event_study_results remain empty.';
    }

    $lines[] = 'Machine learning / broader data mining is not active in this Komodo shell — telemetry only for v'
        . KOMODO_APP_VERSION . '.';

    return array_values(array_unique($lines));
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $live
 * @return array{class: string, text: string}
 */
function komodo_calendar_overview_badge(bool $offlineMode, array $live): array
{
    if ($offlineMode) {
        return ['class' => 'badge badge--placeholder', 'text' => 'Placeholder'];
    }

    foreach (['market_calendar', 'vw_us_trading_days'] as $key) {
        if (!isset($live[$key]) || (($live[$key]['status'] ?? '') !== 'ok')) {
            return ['class' => 'badge badge--missing', 'text' => 'Missing'];
        }
        if (($live[$key]['count'] ?? null) === null) {
            return ['class' => 'badge badge--missing', 'text' => 'Missing'];
        }
    }

    $calendar = $live['market_calendar']['count'];
    $tradingDays = $live['vw_us_trading_days']['count'];
    if ($calendar === null || $tradingDays === null) {
        return ['class' => 'badge badge--missing', 'text' => 'Missing'];
    }
    if ($calendar === 0 || $tradingDays === 0) {
        return ['class' => 'badge badge--zero', 'text' => 'Incomplete'];
    }

    return ['class' => 'badge badge--ready', 'text' => 'Populated'];
}

/**
 * @param array<string, array{identifier: string, count: ?int, status: string}> $live
 * @return array{class: string, text: string}
 */
function komodo_price_overview_badge(bool $offlineMode, array $live): array
{
    if ($offlineMode) {
        return ['class' => 'badge badge--placeholder', 'text' => 'Placeholder'];
    }

    foreach (['security_daily_prices', 'index_daily_prices'] as $key) {
        if (!isset($live[$key]) || (($live[$key]['status'] ?? '') !== 'ok')) {
            return ['class' => 'badge badge--missing', 'text' => 'Missing'];
        }
    }

    $sec = $live['security_daily_prices']['count'];
    $idx = $live['index_daily_prices']['count'];
    if ($sec === null || $idx === null) {
        return ['class' => 'badge badge--missing', 'text' => 'Missing'];
    }

    if ($sec === 0 && $idx === 0) {
        return ['class' => 'badge badge--zero-muted', 'text' => 'Pending load'];
    }

    if (($sec === 0) xor ($idx === 0)) {
        return ['class' => 'badge badge--warning', 'text' => 'Partial load'];
    }

    return ['class' => 'badge badge--ready', 'text' => 'Loaded'];
}

/**
 * Assemble dashboard context once per request (read-only snapshots).
 *
 * @return array<string, mixed>
 */
function komodo_build_dashboard_context(): array
{
    $dbStatus = komodo_get_database_status();
    $pdo = $dbStatus['pdo'];
    $connState = $dbStatus['status'];

    $tableCountsSafe = [];
    $viewCountsSafe = [];
    if ($pdo !== null) {
        $tableCountsSafe = komodo_get_table_counts_safe($pdo);
        $viewCountsSafe = komodo_get_view_counts_safe($pdo);
    }

    $offlineMode = $pdo === null;
    $liveMerged = $offlineMode ? [] : array_merge($tableCountsSafe, $viewCountsSafe);
    $partialMetrics = ($connState === 'connected')
        && (komodo_any_metric_unavailable($tableCountsSafe) || komodo_any_metric_unavailable($viewCountsSafe));

    $banners = komodo_dashboard_banners($connState, $partialMetrics, $dbStatus['message']);

    $workflow = komodo_dashboard_workflow_phase($connState, $tableCountsSafe);
    $nextActions = komodo_dashboard_next_actions($connState, $tableCountsSafe, $partialMetrics);
    $gapRows = komodo_build_gap_rows($offlineMode, $tableCountsSafe);

    $visualMode = komodo_page_visual_mode($connState, $partialMetrics);
    $primaryStatusBadge = komodo_primary_status_badge($visualMode);
    $phaseStatusLines = komodo_phase_status_lines($connState, $workflow['id'], $tableCountsSafe, $offlineMode);
    $pipelineNarrative = komodo_pipeline_narrative($connState, $tableCountsSafe, $offlineMode);

    $tableOrder = [
        'companies', 'securities', 'cyber_events', 'cyber_event_dates', 'cyber_event_features',
        'cyber_event_impacts', 'cyber_event_securities', 'market_calendar', 'security_daily_prices',
        'index_daily_prices', 'cyber_event_sources', 'event_study_runs', 'event_study_results',
    ];

    $marketKpis = [
        'market_calendar',
        'vw_market_data_import_plan',
        'vw_security_price_import_targets',
        'vw_us_trading_days',
        'security_daily_prices',
        'index_daily_prices',
    ];

    $eventReadinessKpis = [
        'vw_event_study_event_readiness',
        'vw_event_window_boundaries',
    ];

    $researchViews = [
        'vw_event_contamination_flags',
        'vw_event_impact_quality_flags',
        'vw_event_research_readiness_flags',
        'vw_event_same_ticker_window_overlaps',
        'vw_event_nearby_cyber_clusters',
    ];

    $eventCoreTables = [
        'cyber_events',
        'cyber_event_dates',
        'cyber_event_features',
        'cyber_event_impacts',
        'cyber_event_securities',
    ];

    $overviewEventsBadge = komodo_metric_badge($offlineMode, $liveMerged, 'cyber_events');
    $overviewCalendarBadge = komodo_calendar_overview_badge($offlineMode, $liveMerged);
    $overviewPriceBadge = komodo_price_overview_badge($offlineMode, $liveMerged);
    $overviewProvenanceBadge = komodo_metric_badge($offlineMode, $liveMerged, 'cyber_event_sources');

    $komodoPageGenerated = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $sidebarModeCaption = match ($visualMode) {
        'live' => 'Telemetry: live reads',
        'degraded' => 'Telemetry: partial',
        'offline' => 'Reference: offline',
        default => 'Connection blocked',
    };

    $majorGapRows = array_slice($gapRows, 0, 5);

    return [
        'db_status' => $dbStatus,
        'conn_state' => $connState,
        'offline_mode' => $offlineMode,
        'partial_metrics' => $partialMetrics,
        'table_counts_safe' => $tableCountsSafe,
        'view_counts_safe' => $viewCountsSafe,
        'live_merged' => $liveMerged,
        'banners' => $banners,
        'workflow' => $workflow,
        'next_actions' => $nextActions,
        'gap_rows' => $gapRows,
        'major_gap_rows' => $majorGapRows,
        'visual_mode' => $visualMode,
        'primary_status_badge' => $primaryStatusBadge,
        'phase_status_lines' => $phaseStatusLines,
        'pipeline_narrative' => $pipelineNarrative,
        'table_order' => $tableOrder,
        'market_kpis' => $marketKpis,
        'event_readiness_kpis' => $eventReadinessKpis,
        'research_views' => $researchViews,
        'event_core_tables' => $eventCoreTables,
        'overview_events_badge' => $overviewEventsBadge,
        'overview_calendar_badge' => $overviewCalendarBadge,
        'overview_price_badge' => $overviewPriceBadge,
        'overview_provenance_badge' => $overviewProvenanceBadge,
        'page_generated_atom' => $komodoPageGenerated->format(DATE_ATOM),
        'page_generated_human' => $komodoPageGenerated->format('Y-m-d H:i:s') . ' UTC',
        'sidebar_mode_caption' => $sidebarModeCaption,
    ];
}

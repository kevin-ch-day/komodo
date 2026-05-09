<?php

declare(strict_types=1);

/**
 * Read-only market / price coverage helpers (vw_market_data_import_plan + price aggregates).
 */

/**
 * Full market-data payload for Market Data page.
 *
 * @param array<string, mixed> $baseContext
 *
 * @return array{
 *   available: bool,
 *   partial: bool,
 *   mode: string,
 *   message: string,
 *   errors: list<string>,
 *   security_summary: ?array<string, mixed>,
 *   index_summary: ?array<string, mixed>,
 *   security_rows: list<array<string, mixed>>,
 *   index_rows: list<array<string, mixed>>,
 *   data_sources: list<array<string, mixed>>,
 *   top_problem_securities: list<array<string, mixed>>,
 *   insights: array<string, mixed>,
 *   notes_preview: list<array{ticker_symbol: string, import_notes: string}>,
 *   price_import_readiness: ?array<string, mixed>
 * }
 */
function komodo_build_market_data_context(?PDO $pdo, array $baseContext): array
{
    unset($baseContext);

    $offlineInsights = [
        'headline' => 'Connect MariaDB with app/config/local.php to evaluate suggested price windows and benchmark coverage.',
        'pct_securities_not_started' => null,
        'pct_covers_suggested_window' => null,
        'index_load_stage' => 'unknown',
        'next_step' => 'Add local credentials, start MariaDB, reload this page.',
        'checklist' => [
            'Confirm XAMPP MariaDB is running.',
            'Verify gecko_research_database_prod is reachable.',
            'Return here for per-ticker windows once live.',
        ],
    ];

    $empty = [
        'available' => false,
        'partial' => false,
        'mode' => 'offline',
        'message' => 'Live market data coverage requires a database connection.',
        'errors' => [],
        'security_summary' => null,
        'index_summary' => null,
        'security_rows' => [],
        'index_rows' => [],
        'data_sources' => [],
        'top_problem_securities' => [],
        'insights' => $offlineInsights,
        'notes_preview' => [],
        'price_import_readiness' => null,
    ];

    if ($pdo === null) {
        return $empty;
    }

    $errors = [];
    $partial = false;

    $securityFetch = komodo_fetch_security_price_coverage($pdo);
    if (!$securityFetch['ok']) {
        $errors[] = 'Security price coverage could not be loaded.';
        $partial = true;
        $securityRows = [];
    } else {
        $securityRows = $securityFetch['rows'];
        foreach ($securityRows as &$sr) {
            $sr['coverage_status'] = komodo_security_coverage_status($sr);
        }
        unset($sr);
    }

    $indexFetch = komodo_fetch_index_price_coverage($pdo);
    if (!$indexFetch['ok']) {
        $errors[] = 'Index price coverage could not be loaded.';
        $partial = true;
        $indexRows = [];
    } else {
        $indexRows = $indexFetch['rows'];
        foreach ($indexRows as &$ir) {
            $ir['coverage_status'] = komodo_index_coverage_status($ir);
        }
        unset($ir);
    }

    $sourcesFetch = komodo_fetch_data_sources($pdo);
    if (!$sourcesFetch['ok']) {
        $errors[] = 'Data sources could not be loaded.';
        $partial = true;
        $dataSources = [];
    } else {
        $dataSources = $sourcesFetch['rows'];
    }

    $securitySummary = $securityFetch['ok'] ? komodo_summarize_security_coverage($securityRows) : null;
    $indexSummary = $indexFetch['ok'] ? komodo_summarize_index_coverage($indexRows) : null;

    $topProblems = $securityFetch['ok'] ? komodo_top_problem_securities($securityRows) : [];

    $notesPreview = $securityFetch['ok'] ? komodo_securities_notes_preview($securityRows, 8) : [];

    $insights = komodo_market_data_insights($securitySummary, $indexSummary);

    $priceImportReadiness = komodo_market_price_import_readiness($securitySummary, $indexSummary);

    $mode = $partial ? 'partial' : 'live';
    $message = $partial
        ? 'Some market data coverage sections could not be loaded.'
        : 'Live coverage from MariaDB whitelisted SELECT queries.';

    return [
        'available' => true,
        'partial' => $partial,
        'mode' => $mode,
        'message' => $message,
        'errors' => $errors,
        'security_summary' => $securitySummary,
        'index_summary' => $indexSummary,
        'security_rows' => $securityRows,
        'index_rows' => $indexRows,
        'data_sources' => $dataSources,
        'top_problem_securities' => $topProblems,
        'insights' => $insights,
        'notes_preview' => $notesPreview,
        'price_import_readiness' => $priceImportReadiness,
    ];
}

/**
 * Price import readiness for event-study prep (no SQL — uses existing summaries).
 *
 * @param array<string, mixed>|null $securitySummary
 * @param array<string, mixed>|null $indexSummary
 *
 * @return array{
 *   overall: array{state: string, label: string, badge_class: string},
 *   benchmark: array{state: string, label: string, badge_class: string, dek: string, technical: list<string>},
 *   event_linked: array{state: string, label: string, badge_class: string, dek: string, technical: list<string>},
 *   comparison: array{state: string, label: string, badge_class: string, dek: string, technical: list<string>},
 *   notes_count: int,
 *   next_action: string
 * }
 */
function komodo_market_price_import_readiness(?array $securitySummary, ?array $indexSummary): array
{
    $notesCount = is_array($securitySummary) ? (int) ($securitySummary['securities_with_import_notes'] ?? 0) : 0;

    $benchmark = komodo_readiness_benchmark_indexes($indexSummary);
    $eventLinked = komodo_readiness_security_role($securitySummary, 'event_linked_security');
    $comparison = komodo_readiness_security_role($securitySummary, 'comparison_or_unlinked_security');

    $eventSatisfied = komodo_price_import_role_pipeline_satisfied($eventLinked);
    $compSatisfied = komodo_price_import_role_pipeline_satisfied($comparison);

    $benchmarkLoaded = !$benchmark['summary_missing'] && $benchmark['state'] === 'loaded';

    $overallState = 'partial';
    $overallLabel = 'Partial';
    $overallBadge = 'coverage-badge--partial';

    if ($benchmark['summary_missing']) {
        $overallBadge = 'coverage-badge--unknown';
    } elseif ($benchmark['state'] === 'not_started') {
        $overallState = 'not_started';
        $overallLabel = 'Not started';
        $overallBadge = 'coverage-badge--not-started';
    } elseif ($benchmarkLoaded && $eventSatisfied && $compSatisfied) {
        $overallState = 'ready';
        $overallLabel = 'Prepared for QA';
        $overallBadge = 'coverage-badge--ok';
    }

    $nextAction = komodo_price_import_next_action($benchmark, $eventLinked, $comparison);

    return [
        'overall' => [
            'state' => $overallState,
            'label' => $overallLabel,
            'badge_class' => $overallBadge,
        ],
        'benchmark' => $benchmark,
        'event_linked' => $eventLinked,
        'comparison' => $comparison,
        'notes_count' => $notesCount,
        'next_action' => $nextAction,
    ];
}

/**
 * @param array<string, mixed>|null $indexSummary
 *
 * @return array{state: string, label: string, badge_class: string, dek: string, technical: list<string>, total_indexes: int, with_prices: int, summary_missing: bool}
 */
function komodo_readiness_benchmark_indexes(?array $indexSummary): array
{
    $technical = ['index_daily_prices', 'market_indexes'];
    if ($indexSummary === null) {
        return [
            'state' => 'unavailable',
            'label' => 'Unavailable',
            'badge_class' => 'coverage-badge--unknown',
            'dek' => 'Index summary could not be computed.',
            'technical' => $technical,
            'total_indexes' => 0,
            'with_prices' => 0,
            'summary_missing' => true,
        ];
    }

    $total = (int) ($indexSummary['total_indexes'] ?? 0);
    $with = (int) ($indexSummary['indexes_with_any_prices'] ?? 0);

    if ($total === 0) {
        return [
            'state' => 'unavailable',
            'label' => 'Unavailable',
            'badge_class' => 'coverage-badge--unknown',
            'dek' => 'No benchmark indexes are defined — add market index rows before loading index_daily_prices.',
            'technical' => $technical,
            'total_indexes' => 0,
            'with_prices' => 0,
            'summary_missing' => false,
        ];
    }

    if ($with === 0) {
        return [
            'state' => 'not_started',
            'label' => 'Not started',
            'badge_class' => 'coverage-badge--not-started',
            'dek' => sprintf('%d benchmark index(es) in scope; no rows loaded in index_daily_prices yet.', $total),
            'technical' => $technical,
            'total_indexes' => $total,
            'with_prices' => 0,
            'summary_missing' => false,
        ];
    }

    if ($with < $total) {
        return [
            'state' => 'partial',
            'label' => 'Partial coverage',
            'badge_class' => 'coverage-badge--partial',
            'dek' => sprintf('%d of %d benchmark index(es) have price bars — finish remaining series first.', $with, $total),
            'technical' => $technical,
            'total_indexes' => $total,
            'with_prices' => $with,
            'summary_missing' => false,
        ];
    }

    return [
        'state' => 'loaded',
        'label' => 'Benchmark series loaded',
        'badge_class' => 'coverage-badge--ok',
        'dek' => sprintf('All %d benchmark index(es) have price data in index_daily_prices.', $total),
        'technical' => $technical,
        'total_indexes' => $total,
        'with_prices' => $with,
        'summary_missing' => false,
    ];
}

/**
 * @param array<string, mixed>|null $securitySummary
 *
 * @return array{state: string, label: string, badge_class: string, dek: string, technical: list<string>, total: int, covers: int, not_started: int, summary_missing: bool, bucket_missing: bool}
 */
function komodo_readiness_security_role(?array $securitySummary, string $roleKey): array
{
    $technical = ['security_daily_prices', 'vw_market_data_import_plan', 'vw_security_price_import_targets'];
    if ($securitySummary === null) {
        return [
            'state' => 'unavailable',
            'label' => 'Unavailable',
            'badge_class' => 'coverage-badge--unknown',
            'dek' => 'Security coverage summary not loaded.',
            'technical' => $technical,
            'total' => 0,
            'covers' => 0,
            'not_started' => 0,
            'summary_missing' => true,
            'bucket_missing' => false,
        ];
    }

    if (!isset($securitySummary['by_role'][$roleKey]) || !is_array($securitySummary['by_role'][$roleKey])) {
        return [
            'state' => 'unavailable',
            'label' => 'Unavailable',
            'badge_class' => 'coverage-badge--unknown',
            'dek' => 'Role bucket missing from security summary — coverage data may be partial.',
            'technical' => $technical,
            'total' => 0,
            'covers' => 0,
            'not_started' => 0,
            'summary_missing' => false,
            'bucket_missing' => true,
        ];
    }

    /** @var array<string, mixed> $b */
    $b = $securitySummary['by_role'][$roleKey];
    $total = (int) ($b['total'] ?? 0);
    $notStarted = (int) ($b['not_started'] ?? 0);
    $covers = (int) ($b['covers_suggested_window'] ?? 0);

    if ($total === 0) {
        return [
            'state' => 'unavailable',
            'label' => 'Unavailable',
            'badge_class' => 'coverage-badge--unknown',
            'dek' => 'No securities in this role in the market data plan.',
            'technical' => $technical,
            'total' => 0,
            'covers' => 0,
            'not_started' => 0,
            'summary_missing' => false,
            'bucket_missing' => false,
        ];
    }

    if ($notStarted === $total) {
        return [
            'state' => 'not_started',
            'label' => 'Not started',
            'badge_class' => 'coverage-badge--not-started',
            'dek' => sprintf('%d security price series in scope; none have rows in security_daily_prices yet.', $total),
            'technical' => $technical,
            'total' => $total,
            'covers' => $covers,
            'not_started' => $notStarted,
            'summary_missing' => false,
            'bucket_missing' => false,
        ];
    }

    if ($covers === $total) {
        return [
            'state' => 'ready',
            'label' => 'Coverage ready',
            'badge_class' => 'coverage-badge--ok',
            'dek' => sprintf('All %d series fully cover the suggested import window.', $total),
            'technical' => $technical,
            'total' => $total,
            'covers' => $covers,
            'not_started' => $notStarted,
            'summary_missing' => false,
            'bucket_missing' => false,
        ];
    }

    return [
        'state' => 'partial',
        'label' => 'Partial coverage',
        'badge_class' => 'coverage-badge--partial',
        'dek' => sprintf('%d of %d fully cover the suggested window; resolve gaps before event-study QA.', $covers, $total),
        'technical' => $technical,
        'total' => $total,
        'covers' => $covers,
        'not_started' => $notStarted,
        'summary_missing' => false,
        'bucket_missing' => false,
    ];
}

/**
 * @param array<string, mixed> $roleReadiness
 */
function komodo_price_import_role_pipeline_satisfied(array $roleReadiness): bool
{
    if (!empty($roleReadiness['summary_missing']) || !empty($roleReadiness['bucket_missing'])) {
        return false;
    }

    if ($roleReadiness['state'] === 'ready') {
        return true;
    }

    return $roleReadiness['state'] === 'unavailable' && (int) ($roleReadiness['total'] ?? 0) === 0;
}

/**
 * @param array<string, mixed> $benchmark
 * @param array<string, mixed> $eventLinked
 * @param array<string, mixed> $comparison
 */
function komodo_price_import_next_action(array $benchmark, array $eventLinked, array $comparison): string
{
    if (!empty($benchmark['summary_missing'])) {
        return 'Reconnect the database or reload this page — benchmark index coverage could not be summarized.';
    }

    if (!empty($eventLinked['summary_missing']) || !empty($comparison['summary_missing'])) {
        return 'Reconnect the database or reload this page — security price coverage could not be summarized.';
    }

    if (!empty($eventLinked['bucket_missing']) || !empty($comparison['bucket_missing'])) {
        return 'Security role breakdown is incomplete — fix the market data summary load, then revisit this readiness panel.';
    }

    if ($benchmark['state'] === 'unavailable' && (int) ($benchmark['total_indexes'] ?? 0) === 0) {
        return 'Define benchmark indexes in the database, then load benchmark index prices (e.g. into index_daily_prices) outside Komodo for each benchmark you need for abnormal returns.';
    }

    if ($benchmark['state'] === 'not_started' || $benchmark['state'] === 'partial') {
        return 'Next action: load benchmark index prices first (e.g. into index_daily_prices) using your external pipeline — Komodo does not write rows. Finish all benchmark series before scaling security price loads.';
    }

    if (!komodo_price_import_role_pipeline_satisfied($eventLinked)) {
        return 'Next action: load security prices for event-linked securities (e.g. into security_daily_prices) outside Komodo — these anchor primary event-study observations.';
    }

    if (!komodo_price_import_role_pipeline_satisfied($comparison)) {
        return 'Next action: load security prices for comparison / unlinked securities outside Komodo, then re-run this page for coverage QA.';
    }

    return 'Price coverage looks sufficient for event-study QA in this portal — verify windows, special import notes, and data sources; run estimation outside Komodo.';
}

/**
 * One-screen narrative + next actions from summaries (no SQL).
 *
 * @param array<string, mixed>|null $securitySummary
 * @param array<string, mixed>|null $indexSummary
 *
 * @return array{
 *   headline: string,
 *   pct_securities_not_started: int|null,
 *   pct_covers_suggested_window: int|null,
 *   index_load_stage: string,
 *   next_step: string,
 *   checklist: list<string>
 * }
 */
function komodo_market_data_insights(?array $securitySummary, ?array $indexSummary): array
{
    $idxTotal = is_array($indexSummary) ? (int) ($indexSummary['total_indexes'] ?? 0) : 0;
    $idxWithPrices = is_array($indexSummary) ? (int) ($indexSummary['indexes_with_any_prices'] ?? 0) : 0;
    $idxZero = is_array($indexSummary) ? (int) ($indexSummary['indexes_with_zero_prices'] ?? 0) : 0;
    $idxBarRows = is_array($indexSummary) ? (int) ($indexSummary['total_index_price_rows'] ?? 0) : 0;

    if ($securitySummary === null && $indexSummary === null) {
        return [
            'headline' => 'Coverage summaries could not be computed.',
            'pct_securities_not_started' => null,
            'pct_covers_suggested_window' => null,
            'index_load_stage' => 'unknown',
            'next_step' => 'Repair failing queries, then refresh.',
            'checklist' => [
                'Check MariaDB error logs.',
                'Confirm views market_indexes and vw_market_data_import_plan exist.',
            ],
        ];
    }

    $totalSec = is_array($securitySummary) ? (int) ($securitySummary['total_securities'] ?? 0) : 0;
    $notStarted = is_array($securitySummary) ? (int) ($securitySummary['not_started'] ?? 0) : 0;
    $covers = is_array($securitySummary) ? (int) ($securitySummary['covers_suggested_window'] ?? 0) : 0;
    $withPrices = is_array($securitySummary) ? (int) ($securitySummary['securities_with_any_prices'] ?? 0) : 0;

    $pctNs = $totalSec > 0 ? (int) round(100 * $notStarted / $totalSec) : null;
    $pctCov = $totalSec > 0 ? (int) round(100 * $covers / $totalSec) : null;

    if ($idxTotal === 0) {
        $indexStage = 'no_indexes_defined';
    } elseif ($idxWithPrices === 0) {
        $indexStage = 'index_prices_empty';
    } elseif ($idxZero === 0) {
        $indexStage = 'all_indexes_have_bars';
    } else {
        $indexStage = 'index_prices_partial';
    }

    $parts = [];
    if (is_array($securitySummary)) {
        $parts[] = $withPrices === 0
            ? sprintf('No securities have price rows yet (%d in market data plan).', $totalSec)
            : sprintf('%d of %d securities have at least one price row.', $withPrices, $totalSec);
    }
    if (is_array($indexSummary)) {
        if ($idxBarRows === 0) {
            $parts[] = sprintf('Index bars: 0 rows across %d benchmark(s).', $idxTotal);
        } else {
            $parts[] = sprintf(
                'Index bars: %s row(s) loaded; %d index(es) with data.',
                number_format($idxBarRows),
                $idxWithPrices
            );
        }
    }
    $headline = $parts !== [] ? implode(' ', $parts) : 'Market data coverage snapshot.';

    $next = 'Pending external load: benchmark index daily prices first, then event-linked securities (Komodo is read-only).';
    if ($indexStage === 'all_indexes_have_bars' && $withPrices < $totalSec && $totalSec > 0) {
        $next = 'Benchmark indexes look loaded — focus on event-linked security price coverage, then comparison tickers (outside Komodo).';
    }
    if ($indexStage === 'index_prices_partial') {
        $next = 'Finish remaining benchmark index series outside Komodo before relying on cross-asset QA.';
    }
    if ($covers === $totalSec && $totalSec > 0 && $indexStage === 'all_indexes_have_bars') {
        $next = 'Planned windows show coverage in telemetry — spot-check gaps and source provenance before running estimation outside Komodo.';
    }

    $checklist = [
        'Outside Komodo: load index_daily_prices for every market_indexes row you need as a benchmark.',
        'Outside Komodo: load security_daily_prices starting with event_linked_security rows.',
        'Compare first/last bar dates against suggested_import_* dates on this page.',
        'Revisit Data gaps for price provenance after each external load batch.',
    ];

    return [
        'headline' => $headline,
        'pct_securities_not_started' => $pctNs,
        'pct_covers_suggested_window' => $pctCov,
        'index_load_stage' => $indexStage,
        'next_step' => $next,
        'checklist' => $checklist,
    ];
}

/**
 * Tickers with non-empty import_notes for quick visibility.
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return list<array{ticker_symbol: string, import_notes: string}>
 */
function komodo_securities_notes_preview(array $securityRows, int $limit = 8): array
{
    $out = [];
    foreach ($securityRows as $row) {
        $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        if ($note === '') {
            continue;
        }
        $ticker = (string) ($row['ticker_symbol'] ?? '');
        $snippet = strlen($note) > 140 ? substr($note, 0, 140) . '…' : $note;
        $out[] = ['ticker_symbol' => $ticker, 'import_notes' => $snippet];
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_security_price_coverage(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
  plan.security_id,
  plan.ticker_symbol,
  plan.display_name,
  plan.security_name,
  plan.exchange_code,
  plan.price_import_role,
  plan.linked_event_count,
  plan.suggested_import_start_date,
  plan.suggested_import_end_date,
  plan.import_notes,
  COALESCE(px.price_rows, 0) AS price_rows,
  px.first_price_date,
  px.last_price_date
FROM vw_market_data_import_plan plan
LEFT JOIN (
  SELECT
    security_id,
    COUNT(*) AS price_rows,
    MIN(trade_date) AS first_price_date,
    MAX(trade_date) AS last_price_date
  FROM security_daily_prices
  GROUP BY security_id
) px
  ON px.security_id = plan.security_id
ORDER BY
  CASE
    WHEN plan.price_import_role = 'event_linked_security' THEN 1
    ELSE 2
  END,
  plan.ticker_symbol
SQL;

    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return ['ok' => false, 'rows' => [], 'error' => 'query_failed'];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_index_price_coverage(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
  mi.market_index_id,
  mi.index_code,
  mi.index_name,
  mi.country_code,
  mi.currency_code,
  COALESCE(px.price_rows, 0) AS price_rows,
  px.first_price_date,
  px.last_price_date
FROM market_indexes mi
LEFT JOIN (
  SELECT
    market_index_id,
    COUNT(*) AS price_rows,
    MIN(trade_date) AS first_price_date,
    MAX(trade_date) AS last_price_date
  FROM index_daily_prices
  GROUP BY market_index_id
) px
  ON px.market_index_id = mi.market_index_id
ORDER BY mi.index_code
SQL;

    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return ['ok' => false, 'rows' => [], 'error' => 'query_failed'];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_data_sources(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
  data_source_id,
  source_name,
  source_type,
  base_url,
  notes,
  created_at
FROM data_sources
ORDER BY data_source_id
SQL;

    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return ['ok' => false, 'rows' => [], 'error' => 'query_failed'];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * @param array<string, mixed> $row
 */
function komodo_security_coverage_status(array $row): string
{
    $priceRows = (int) ($row['price_rows'] ?? 0);
    if ($priceRows === 0) {
        return 'not_started';
    }

    $start = $row['suggested_import_start_date'] ?? null;
    $end = $row['suggested_import_end_date'] ?? null;
    if ($start === null || $start === '' || $end === null || $end === '') {
        return 'has_prices_window_unknown';
    }

    $first = komodo_normalize_date_string($row['first_price_date'] ?? null);
    $last = komodo_normalize_date_string($row['last_price_date'] ?? null);

    if ($first === null || $last === null) {
        return 'partial_unknown_dates';
    }

    $sStart = komodo_normalize_date_string($start);
    $sEnd = komodo_normalize_date_string($end);
    if ($sStart === null || $sEnd === null) {
        return 'has_prices_window_unknown';
    }

    if (strcmp($first, $sStart) > 0) {
        return 'missing_start_window';
    }

    if (strcmp($last, $sEnd) < 0) {
        return 'missing_end_window';
    }

    if (strcmp($first, $sStart) <= 0 && strcmp($last, $sEnd) >= 0) {
        return 'covers_suggested_window';
    }

    return 'partial';
}

/**
 * @param array<string, mixed> $row
 */
function komodo_index_coverage_status(array $row): string
{
    $priceRows = (int) ($row['price_rows'] ?? 0);

    return $priceRows === 0 ? 'not_started' : 'has_prices';
}

/**
 * Normalize DB date-ish value to Y-m-d or null.
 */
function komodo_normalize_date_string(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d');
    }
    $s = (string) $v;
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
        return $m[1];
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array<string, mixed>
 */
function komodo_summarize_security_coverage(array $rows): array
{
    $counts = [
        'total_securities' => 0,
        'securities_with_import_notes' => 0,
        'event_linked_securities' => 0,
        'comparison_or_unlinked_securities' => 0,
        'securities_with_any_prices' => 0,
        'securities_with_zero_prices' => 0,
        'covers_suggested_window' => 0,
        'missing_start_window' => 0,
        'missing_end_window' => 0,
        'has_prices_window_unknown' => 0,
        'partial_unknown_dates' => 0,
        'partial' => 0,
        'not_started' => 0,
    ];

    $byRole = [
        'event_linked_security' => komodo_empty_role_bucket(),
        'comparison_or_unlinked_security' => komodo_empty_role_bucket(),
    ];

    foreach ($rows as $row) {
        $counts['total_securities']++;
        $role = (string) ($row['price_import_role'] ?? '');
        $priceRows = (int) ($row['price_rows'] ?? 0);

        if ($role === 'event_linked_security') {
            $counts['event_linked_securities']++;
        } else {
            $counts['comparison_or_unlinked_securities']++;
        }

        if ($priceRows > 0) {
            $counts['securities_with_any_prices']++;
        } else {
            $counts['securities_with_zero_prices']++;
        }

        $rawNote = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        if ($rawNote !== '') {
            $counts['securities_with_import_notes']++;
        }

        $status = (string) ($row['coverage_status'] ?? 'not_started');
        $key = match ($status) {
            'covers_suggested_window' => 'covers_suggested_window',
            'missing_start_window' => 'missing_start_window',
            'missing_end_window' => 'missing_end_window',
            'has_prices_window_unknown' => 'has_prices_window_unknown',
            'partial_unknown_dates' => 'partial_unknown_dates',
            'partial' => 'partial',
            'not_started' => 'not_started',
            default => 'partial',
        };
        if (isset($counts[$key])) {
            $counts[$key]++;
        }

        $bucketKey = $role === 'event_linked_security'
            ? 'event_linked_security'
            : 'comparison_or_unlinked_security';
        $b = &$byRole[$bucketKey];
        $b['total']++;
        if ($status === 'not_started') {
            $b['not_started']++;
        }
        if ($priceRows > 0) {
            $b['has_prices']++;
        }
        if ($status === 'covers_suggested_window') {
            $b['covers_suggested_window']++;
        }
        if ($status === 'missing_start_window') {
            $b['missing_start_window']++;
        }
        if ($status === 'missing_end_window') {
            $b['missing_end_window']++;
        }
        unset($b);
    }

    return array_merge($counts, ['by_role' => $byRole]);
}

/**
 * @return array{total: int, not_started: int, has_prices: int, covers_suggested_window: int, missing_start_window: int, missing_end_window: int}
 */
function komodo_empty_role_bucket(): array
{
    return [
        'total' => 0,
        'not_started' => 0,
        'has_prices' => 0,
        'covers_suggested_window' => 0,
        'missing_start_window' => 0,
        'missing_end_window' => 0,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array<string, mixed>
 */
function komodo_summarize_index_coverage(array $rows): array
{
    $total = count($rows);
    $any = 0;
    $zero = 0;
    $sumRows = 0;
    $firstGlobal = null;
    $lastGlobal = null;

    foreach ($rows as $row) {
        $pr = (int) ($row['price_rows'] ?? 0);
        $sumRows += $pr;
        if ($pr > 0) {
            $any++;
            $f = komodo_normalize_date_string($row['first_price_date'] ?? null);
            $l = komodo_normalize_date_string($row['last_price_date'] ?? null);
            if ($f !== null && ($firstGlobal === null || strcmp($f, $firstGlobal) < 0)) {
                $firstGlobal = $f;
            }
            if ($l !== null && ($lastGlobal === null || strcmp($l, $lastGlobal) > 0)) {
                $lastGlobal = $l;
            }
        } else {
            $zero++;
        }
    }

    return [
        'total_indexes' => $total,
        'indexes_with_any_prices' => $any,
        'indexes_with_zero_prices' => $zero,
        'total_index_price_rows' => $sumRows,
        'first_index_price_date' => $firstGlobal,
        'last_index_price_date' => $lastGlobal,
    ];
}

/** @var list<string> */
const KOMODO_PROBLEM_SECURITY_STATUSES = [
    'not_started',
    'missing_start_window',
    'missing_end_window',
    'partial_unknown_dates',
    'has_prices_window_unknown',
    'partial',
];

/**
 * @param list<array<string, mixed>> $securityRows pre-sorted: event-linked then ticker from SQL
 *
 * @return list<array<string, mixed>>
 */
function komodo_top_problem_securities(array $securityRows): array
{
    $out = [];
    foreach ($securityRows as $row) {
        $st = (string) ($row['coverage_status'] ?? '');
        if (in_array($st, KOMODO_PROBLEM_SECURITY_STATUSES, true)) {
            $out[] = [
                'ticker_symbol' => $row['ticker_symbol'] ?? '',
                'display_name' => $row['display_name'] ?? '',
                'security_name' => $row['security_name'] ?? '',
                'price_import_role' => $row['price_import_role'] ?? '',
                'linked_event_count' => $row['linked_event_count'] ?? null,
                'suggested_import_start_date' => $row['suggested_import_start_date'] ?? null,
                'suggested_import_end_date' => $row['suggested_import_end_date'] ?? null,
                'price_rows' => (int) ($row['price_rows'] ?? 0),
                'coverage_status' => $st,
                'import_notes' => $row['import_notes'] ?? null,
            ];
        }
    }

    usort($out, static function (array $a, array $b): int {
        $ra = ($a['price_import_role'] ?? '') === 'event_linked_security' ? 1 : 2;
        $rb = ($b['price_import_role'] ?? '') === 'event_linked_security' ? 1 : 2;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcmp((string) ($a['ticker_symbol'] ?? ''), (string) ($b['ticker_symbol'] ?? ''));
    });

    return array_slice($out, 0, 15);
}

<?php

declare(strict_types=1);

/**
 * Read-only market import coverage helpers (vw_market_data_import_plan + price aggregates).
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
 *   notes_preview: list<array{ticker_symbol: string, import_notes: string}>
 * }
 */
function komodo_build_market_data_context(?PDO $pdo, array $baseContext): array
{
    unset($baseContext);

    $offlineInsights = [
        'headline' => 'Connect MariaDB with app/config/local.php to evaluate import windows and benchmark coverage.',
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
    ];
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
            ? sprintf('No securities have price rows yet (%d in import plan).', $totalSec)
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

    $next = 'Import benchmark index daily prices first, then event-linked securities.';
    if ($indexStage === 'all_indexes_have_bars' && $withPrices < $totalSec && $totalSec > 0) {
        $next = 'Indexes look loaded — focus on event-linked security prices, then comparison tickers.';
    }
    if ($indexStage === 'index_prices_partial') {
        $next = 'Finish remaining benchmark index series before relying on cross-asset QA.';
    }
    if ($covers === $totalSec && $totalSec > 0 && $indexStage === 'all_indexes_have_bars') {
        $next = 'All planned windows show coverage — spot-check gaps and data sources before studies.';
    }

    $checklist = [
        'Load index_daily_prices for every market_indexes row you need as a benchmark.',
        'Load security_daily_prices starting with event_linked_security rows.',
        'Compare first/last bar dates against suggested_import_* on this page.',
        'Revisit Data gaps for price provenance after imports.',
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

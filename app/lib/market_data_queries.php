<?php

declare(strict_types=1);

/**
 * Read-only market / price coverage helpers (vw_market_data_import_plan + price aggregates).
 */

/**
 * Calendar-day slack for web coverage / triage: first bar within this many days after suggested start (and last within
 * this many before suggested end) still counts as covering the window.
 *
 * Note: `tools/import_security_prices.php` uses a separate constant (currently 10 days) for CLI preview text — values
 * may differ until you intentionally unify them.
 */
const KOMODO_TRIAGE_WINDOW_SLACK_DAYS = 7;

/**
 * UI label for Section-7-style density (shown on Price audit): counts only bars aligned to vw_us_trading_days
 * (see komodo_fetch_aligned_daily_density). Readiness signal only — not a claim that the series is analysis-ready.
 */
const KOMODO_ALIGNED_DAILY_DENSITY_LABEL = 'Aligned daily density';

/**
 * JBSAY: OTC ADR; standard export feed may be unavailable — alternate historical source needed for the stated window.
 * Merged into vw_market_data_import_plan row payloads when the DB stores notes outside `securities.import_notes` (see sql/patch_jbsay_import_notes.sql).
 */
const KOMODO_JBSAY_SPECIAL_SOURCE_NOTE = 'OTC ADR; standard export source unavailable. Needs alternate historical source for 2020-10-24 to 2022-01-07.';

/**
 * Operational rule for Facebook / Meta ticker lineage: vendor export labels vs which security_id receives historical rows.
 * Plain text for escape-wrapped UI paragraphs (Komodo does not infer continuity from filenames).
 */
function komodo_fb_meta_lineage_import_policy_paragraph(): string
{
    return 'Vendor files may use the current ticker META for historical Facebook prices. '
        . 'For pre–June 2022 event windows tied to historical ticker FB, import those historical rows into the FB security record, '
        . 'not the active META record, unless an explicit ticker-continuity rule says otherwise. '
        . 'META-labeled exports do not automatically satisfy FB-tagged windows unless the import/load step maps historical rows to the FB security_id. '
        . 'This is a ticker-lineage and source-label issue, not a database error.';
}

/**
 * Merge known per-ticker import_notes overrides for plan rows (read-only UI; does not write the database).
 *
 * @param list<array<string, mixed>> $rows
 *
 * @return list<array<string, mixed>>
 */
function komodo_merge_vw_market_plan_import_note_overrides(array $rows): array
{
    foreach ($rows as &$row) {
        if (strtoupper(trim((string) ($row['ticker_symbol'] ?? ''))) !== 'JBSAY') {
            continue;
        }
        $existing = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        if ($existing !== '' && str_contains(strtolower($existing), 'otc adr')) {
            continue;
        }
        $row['import_notes'] = $existing === ''
            ? KOMODO_JBSAY_SPECIAL_SOURCE_NOTE
            : $existing . ' ' . KOMODO_JBSAY_SPECIAL_SOURCE_NOTE;
    }
    unset($row);

    return $rows;
}

/**
 * Signed whole calendar days from date A to date B (Y-m-d).
 */
function komodo_calendar_day_diff_ymd(string $fromYmd, string $toYmd): int
{
    $a = DateTimeImmutable::createFromFormat('Y-m-d', $fromYmd);
    $b = DateTimeImmutable::createFromFormat('Y-m-d', $toYmd);
    if ($a === false || $b === false) {
        return 0;
    }

    return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
}

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
 *   top_problem_securities: list<array<string, mixed>>,
 *   insights: array<string, mixed>,
 *   notes_preview: list<array{ticker_symbol: string, import_notes: string}>,
 *   price_import_readiness: ?array<string, mixed>,
 *   queue_loaded_event_linked: list<array<string, mixed>>,
 *   queue_pending_event_linked: list<array<string, mixed>>,
 *   queue_loaded_comparison: list<array<string, mixed>>,
 *   queue_pending_comparison: list<array<string, mixed>>,
 *   queue_rows_with_import_notes: list<array<string, mixed>>,
 *   queue_securities_with_price_rows: list<array<string, mixed>>,
 *   readiness_conclusion: ?array<string, mixed>,
 *   loaded_but_incomplete: list<array<string, mixed>>,
 *   lineage_rows: list<array<string, mixed>>,
 *   triage_needs_price: list<array<string, mixed>>,
 *   triage_needs_price_event_linked: list<array<string, mixed>>,
 *   triage_needs_price_comparison: list<array<string, mixed>>,
 *   triage_window_gaps: list<array<string, mixed>>,
 *   triage_historical_special: list<array<string, mixed>>,
 *   triage_special_notes: list<array<string, mixed>>,
 *   triage_special_notes_event_linked: list<array<string, mixed>>,
 *   triage_special_notes_comparison: list<array<string, mixed>>,
 *   triage_next_batch_normal: list<array<string, mixed>>,
 *   triage_next_batch_older_history: list<array<string, mixed>>,
 *   triage_next_batch_special_source: list<array<string, mixed>>,
 *   triage_dashboard: array<string, int|null>,
 *   aligned_daily_density: array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
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
        'top_problem_securities' => [],
        'insights' => $offlineInsights,
        'notes_preview' => [],
        'price_import_readiness' => null,
        'queue_loaded_event_linked' => [],
        'queue_pending_event_linked' => [],
        'queue_loaded_comparison' => [],
        'queue_pending_comparison' => [],
        'queue_rows_with_import_notes' => [],
        'queue_securities_with_price_rows' => [],
        'readiness_conclusion' => null,
        'loaded_but_incomplete' => [],
        'lineage_rows' => [],
        'triage_needs_price' => [],
        'triage_needs_price_event_linked' => [],
        'triage_needs_price_comparison' => [],
        'triage_window_gaps' => [],
        'triage_historical_special' => [],
        'triage_special_notes' => [],
        'triage_special_notes_event_linked' => [],
        'triage_special_notes_comparison' => [],
        'triage_next_batch_normal' => [],
        'triage_next_batch_older_history' => [],
        'triage_next_batch_special_source' => [],
        'triage_dashboard' => [
            'open_total' => 0,
            'needs_count' => 0,
            'window_count' => 0,
            'historical_count' => 0,
            'special_notes_count' => 0,
            'completed_plan_rows' => 0,
            'plan_total' => 0,
            'covers_from_summary' => null,
        ],
        'aligned_daily_density' => [
            'ok' => false,
            'rows' => [],
            'error' => null,
        ],
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

    $alignedDensity = ['ok' => false, 'rows' => [], 'error' => null];
    if ($securityFetch['ok']) {
        $alignedFetch = komodo_fetch_aligned_daily_density($pdo);
        if (!$alignedFetch['ok']) {
            $errors[] = 'Aligned daily density (trading-day signal) could not be loaded — confirm vw_us_trading_days exists.';
            $partial = true;
            $alignedDensity = [
                'ok' => false,
                'rows' => [],
                'error' => $alignedFetch['error'],
            ];
        } else {
            $alignedDensity = [
                'ok' => true,
                'rows' => $alignedFetch['rows'],
                'error' => null,
            ];
        }
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

    $securitySummary = $securityFetch['ok'] ? komodo_summarize_security_coverage($securityRows) : null;
    $indexSummary = $indexFetch['ok'] ? komodo_summarize_index_coverage($indexRows) : null;

    $topProblems = $securityFetch['ok'] ? komodo_top_problem_securities($securityRows) : [];
    $readinessConclusion = komodo_market_data_readiness_conclusion($securitySummary);
    $loadedIncomplete = $securityFetch['ok'] ? komodo_loaded_but_incomplete_securities($securityRows) : [];
    $lineageRows = $securityFetch['ok'] ? komodo_lineage_highlight_rows($securityRows) : [];

    $notesPreview = $securityFetch['ok'] ? komodo_securities_notes_preview($securityRows, 8) : [];

    $insights = komodo_market_data_insights($securitySummary, $indexSummary);

    $priceImportReadiness = komodo_market_price_import_readiness($securitySummary, $indexSummary);

    $queueSlices = $securityFetch['ok'] ? komodo_market_data_queue_slices($securityRows) : [
        'loaded_event_linked' => [],
        'pending_event_linked' => [],
        'loaded_comparison' => [],
        'pending_comparison' => [],
        'rows_with_import_notes' => [],
        'securities_with_price_rows' => [],
    ];

    $triageSlices = $securityFetch['ok'] ? komodo_price_import_triage_slices($securityRows) : [
        'needs_price_event_linked' => [],
        'needs_price_comparison' => [],
        'needs_price' => [],
        'window_gaps' => [],
        'historical_special' => [],
        'special_notes' => [],
    ];

    $triageSpecialPart = $securityFetch['ok']
        ? komodo_partition_triage_special_notes_by_role($triageSlices['special_notes'])
        : ['event_linked' => [], 'comparison' => []];

    $triageNextModel = $securityFetch['ok'] ? komodo_price_import_triage_next_batch_model($securityRows) : [
        'normal' => [],
        'older_history' => [],
        'special_source' => [],
    ];

    $triageDashboard = $securityFetch['ok']
        ? komodo_price_import_triage_dashboard($securitySummary, $securityRows, $triageSlices)
        : [
            'open_total' => 0,
            'needs_count' => 0,
            'window_count' => 0,
            'historical_count' => 0,
            'special_notes_count' => 0,
            'completed_plan_rows' => 0,
            'plan_total' => 0,
            'covers_from_summary' => null,
        ];

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
        'top_problem_securities' => $topProblems,
        'insights' => $insights,
        'notes_preview' => $notesPreview,
        'price_import_readiness' => $priceImportReadiness,
        'queue_loaded_event_linked' => $queueSlices['loaded_event_linked'],
        'queue_pending_event_linked' => $queueSlices['pending_event_linked'],
        'queue_loaded_comparison' => $queueSlices['loaded_comparison'],
        'queue_pending_comparison' => $queueSlices['pending_comparison'],
        'queue_rows_with_import_notes' => $queueSlices['rows_with_import_notes'],
        'queue_securities_with_price_rows' => $queueSlices['securities_with_price_rows'],
        'readiness_conclusion' => $readinessConclusion,
        'loaded_but_incomplete' => $loadedIncomplete,
        'lineage_rows' => $lineageRows,
        'triage_needs_price' => $triageSlices['needs_price'],
        'triage_needs_price_event_linked' => $triageSlices['needs_price_event_linked'],
        'triage_needs_price_comparison' => $triageSlices['needs_price_comparison'],
        'triage_window_gaps' => $triageSlices['window_gaps'],
        'triage_historical_special' => $triageSlices['historical_special'],
        'triage_special_notes' => $triageSlices['special_notes'],
        'triage_special_notes_event_linked' => $triageSpecialPart['event_linked'],
        'triage_special_notes_comparison' => $triageSpecialPart['comparison'],
        'triage_next_batch_normal' => $triageNextModel['normal'],
        'triage_next_batch_older_history' => $triageNextModel['older_history'],
        'triage_next_batch_special_source' => $triageNextModel['special_source'],
        'triage_dashboard' => $triageDashboard,
        'aligned_daily_density' => $alignedDensity,
    ];
}

/**
 * Analyst-facing conclusion block (read-only, from security_summary only).
 *
 * @return array{
 *   paragraph: string,
 *   short_paragraph: string,
 *   planned_securities: int,
 *   not_started: int,
 *   securities_with_prices: int,
 *   event_linked_total: int,
 *   event_linked_covers_window: int,
 *   event_linked_not_window_complete: int
 * }|null
 */
function komodo_market_data_readiness_conclusion(?array $securitySummary): ?array
{
    if ($securitySummary === null) {
        return null;
    }

    $planned = (int) ($securitySummary['total_securities'] ?? 0);
    $notStarted = (int) ($securitySummary['not_started'] ?? 0);
    $withPrices = (int) ($securitySummary['securities_with_any_prices'] ?? 0);
    $elTotal = (int) ($securitySummary['event_linked_securities'] ?? 0);
    /** @var array<string, mixed> $elBucket */
    $elBucket = is_array($securitySummary['by_role']['event_linked_security'] ?? null)
        ? $securitySummary['by_role']['event_linked_security']
        : [];
    $elCovers = (int) ($elBucket['covers_suggested_window'] ?? 0);
    $elNotComplete = max(0, $elTotal - $elCovers);

    $paragraph = sprintf(
        'Price loading is partially complete. Benchmark and security importers run and persist rows, but the dataset is not event-study ready yet. Benchmark daily completeness must be reviewed, %d of %d planned securities still have no price rows, and %d of %d event-linked securities do not fully cover their suggested windows. Treat current imports as pipeline validation and coverage tracking until windows and benchmarks are validated.',
        $notStarted,
        $planned,
        $elNotComplete,
        $elTotal
    );

    $short = sprintf(
        'Price loading is only partially complete — the dataset is not event-study ready yet. %d of %d planned securities have no price rows; %d of %d event-linked securities do not fully cover their suggested windows. Benchmark daily completeness still needs review.',
        $notStarted,
        $planned,
        $elNotComplete,
        $elTotal
    );

    return [
        'paragraph' => $paragraph,
        'short_paragraph' => $short,
        'planned_securities' => $planned,
        'not_started' => $notStarted,
        'securities_with_prices' => $withPrices,
        'event_linked_total' => $elTotal,
        'event_linked_covers_window' => $elCovers,
        'event_linked_not_window_complete' => $elNotComplete,
    ];
}

/**
 * True when import_notes suggests historical ticker / Meta lineage review.
 */
function komodo_import_notes_lineage_flag(string $note): bool
{
    $n = strtolower($note);

    return str_contains($n, 'historical ticker')
        || str_contains($n, 'current meta ticker')
        || str_contains($n, 'legacy ticker');
}

/**
 * Optional table row highlight for high-event or lineage-flagged names.
 */
function komodo_priority_attention_row_class(array $row): string
{
    $ev = (int) ($row['linked_event_count'] ?? 0);
    $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
    if ($ev >= 2 || komodo_import_notes_lineage_flag($note)) {
        return 'priority-attention-row';
    }

    return '';
}

/**
 * Sort key for queue / problem tables: event-linked first, more events, lineage notes, gaps, then ticker.
 *
 * @param array<string, mixed> $row
 *
 * @return array{int, int, int, int, string}
 */
function komodo_priority_attention_sort_key(array $row): array
{
    $role = (string) ($row['price_import_role'] ?? '');
    $isEl = $role === 'event_linked_security' ? 0 : 1;
    $events = (int) ($row['linked_event_count'] ?? 0);
    $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
    $histNote = komodo_import_notes_lineage_flag($note) ? 1 : 0;
    $st = (string) ($row['coverage_status'] ?? '');
    $statusPri = match ($st) {
        'not_started' => 0,
        'missing_end_window' => 1,
        'missing_start_window' => 2,
        default => 3,
    };
    $ticker = (string) ($row['ticker_symbol'] ?? '');

    return [$isEl, -$events, -$histNote, $statusPri, $ticker];
}

/**
 * @param array<string, mixed> $a
 * @param array<string, mixed> $b
 */
function komodo_priority_attention_compare(array $a, array $b): int
{
    $ka = komodo_priority_attention_sort_key($a);
    $kb = komodo_priority_attention_sort_key($b);
    foreach ([0, 1, 2, 3] as $i) {
        if ($ka[$i] !== $kb[$i]) {
            return $ka[$i] <=> $kb[$i];
        }
    }

    return strcmp($ka[4], $kb[4]);
}

/**
 * Securities with prices that do not span the full suggested window (missing start or end).
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return list<array<string, mixed>>
 */
function komodo_loaded_but_incomplete_securities(array $securityRows): array
{
    $out = [];
    foreach ($securityRows as $row) {
        if ((int) ($row['price_rows'] ?? 0) <= 0) {
            continue;
        }
        $st = (string) ($row['coverage_status'] ?? '');
        if ($st !== 'missing_start_window' && $st !== 'missing_end_window') {
            continue;
        }
        $out[] = $row;
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) ($a['ticker_symbol'] ?? ''), (string) ($b['ticker_symbol'] ?? ''));
    });

    return $out;
}

/**
 * Rows to highlight for FB / META lineage narrative (plan tickers only).
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return list<array<string, mixed>>
 */
function komodo_lineage_highlight_rows(array $securityRows): array
{
    $want = ['FB' => true, 'META' => true];
    $out = [];
    foreach ($securityRows as $row) {
        $t = strtoupper((string) ($row['ticker_symbol'] ?? ''));
        if (isset($want[$t])) {
            $out[] = $row;
            unset($want[$t]);
        }
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) ($a['ticker_symbol'] ?? ''), (string) ($b['ticker_symbol'] ?? ''));
    });

    return $out;
}

/**
 * Import-note flag: historical / lineage / ticker-change style review (triage bucket 3).
 */
function komodo_import_notes_triage_historical_flag(string $note): bool
{
    if (komodo_import_notes_lineage_flag($note)) {
        return true;
    }
    $n = strtolower($note);

    return str_contains($n, 'ticker change')
        || str_contains($n, 'symbol change')
        || str_contains($n, 'renamed')
        || str_contains($n, 'spin-off')
        || str_contains($n, 'spinoff');
}

/**
 * Import-note flag: IPO / listing / availability / source handling (triage bucket 4).
 */
function komodo_import_notes_triage_operational_flag(string $note): bool
{
    $n = strtolower($note);

    $ipo = (bool) preg_match('/\bipo\b/', $n);

    return $ipo
        || str_contains($n, 'listing')
        || str_contains($n, 'availability')
        || str_contains($n, 'delist')
        || str_contains($n, 'special source')
        || str_contains($n, 'otc adr')
        || str_contains($n, 'export source unavailable')
        || str_contains($n, 'alternate historical source')
        || str_contains($n, 'verify ')
        || str_contains($n, 'check ');
}

/**
 * OTC ADR / non-standard vendor path — not a normal CSV download target (next-batch exclusion).
 */
function komodo_import_notes_triage_special_source_otc_adr(string $note): bool
{
    $n = strtolower($note);

    return str_contains($n, 'otc adr')
        && str_contains($n, 'standard export source unavailable')
        && str_contains($n, 'alternate historical source');
}

/**
 * Event-linked row: already has bars but first trade is after the suggested start (extend history backward).
 */
function komodo_triage_next_batch_needs_older_history(array $row): bool
{
    if ((int) ($row['price_rows'] ?? 0) <= 0) {
        return false;
    }

    return (string) ($row['coverage_status'] ?? '') === 'missing_start_window';
}

/**
 * @param list<array<string, mixed>> $specialNotes
 *
 * @return array{event_linked: list<array<string, mixed>>, comparison: list<array<string, mixed>>}
 */
function komodo_partition_triage_special_notes_by_role(array $specialNotes): array
{
    $el = [];
    $comp = [];
    foreach ($specialNotes as $r) {
        if (($r['price_import_role'] ?? '') === 'event_linked_security') {
            $el[] = $r;
        } else {
            $comp[] = $r;
        }
    }
    usort($el, 'komodo_triage_priority_compare');
    usort($comp, 'komodo_triage_priority_compare');

    return ['event_linked' => $el, 'comparison' => $comp];
}

/**
 * Loaded rows where bars do not satisfy the suggested window (or window cannot be evaluated).
 */
function komodo_triage_is_loaded_window_gap(string $status): bool
{
    return in_array($status, [
        'missing_start_window',
        'missing_end_window',
        'has_prices_window_unknown',
        'partial_unknown_dates',
        'partial',
    ], true);
}

/**
 * Sort rank for coverage_status within triage (lower = earlier).
 */
function komodo_triage_status_rank(string $status): int
{
    return match ($status) {
        'missing_end_window' => 0,
        'missing_start_window' => 1,
        'has_prices_window_unknown' => 2,
        'partial_unknown_dates' => 3,
        'partial' => 4,
        'not_started' => 5,
        default => 6,
    };
}

/**
 * Event-linked first; then lineage-style notes; higher event count; window gaps before not started; ticker.
 *
 * @param array<string, mixed> $a
 * @param array<string, mixed> $b
 */
function komodo_triage_priority_compare(array $a, array $b): int
{
    $elA = (($a['price_import_role'] ?? '') === 'event_linked_security') ? 0 : 1;
    $elB = (($b['price_import_role'] ?? '') === 'event_linked_security') ? 0 : 1;
    if ($elA !== $elB) {
        return $elA <=> $elB;
    }

    $noteA = isset($a['import_notes']) ? trim((string) $a['import_notes']) : '';
    $noteB = isset($b['import_notes']) ? trim((string) $b['import_notes']) : '';
    $histA = komodo_import_notes_triage_historical_flag($noteA) ? 1 : 0;
    $histB = komodo_import_notes_triage_historical_flag($noteB) ? 1 : 0;
    if ($histA !== $histB) {
        return $histB <=> $histA;
    }

    $evA = (int) ($a['linked_event_count'] ?? 0);
    $evB = (int) ($b['linked_event_count'] ?? 0);
    if ($evA !== $evB) {
        return $evB <=> $evA;
    }

    $stA = (string) ($a['coverage_status'] ?? '');
    $stB = (string) ($b['coverage_status'] ?? '');
    $rankA = komodo_triage_status_rank($stA);
    $rankB = komodo_triage_status_rank($stB);
    if ($rankA !== $rankB) {
        return $rankA <=> $rankB;
    }

    return strcmp((string) ($a['ticker_symbol'] ?? ''), (string) ($b['ticker_symbol'] ?? ''));
}

/**
 * Exclusive triage buckets for Price import triage page (no covers_suggested_window rows).
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return array{
 *   needs_price_event_linked: list<array<string, mixed>>,
 *   needs_price_comparison: list<array<string, mixed>>,
 *   needs_price: list<array<string, mixed>>,
 *   window_gaps: list<array<string, mixed>>,
 *   historical_special: list<array<string, mixed>>,
 *   special_notes: list<array<string, mixed>>
 * }
 */
function komodo_price_import_triage_slices(array $securityRows): array
{
    $needsEl = [];
    $needsComp = [];
    $window = [];
    $historical = [];
    $special = [];

    $pushNeeds = static function (array $row) use (&$needsEl, &$needsComp): void {
        if (($row['price_import_role'] ?? '') === 'event_linked_security') {
            $needsEl[] = $row;
        } else {
            $needsComp[] = $row;
        }
    };

    foreach ($securityRows as $row) {
        $st = (string) ($row['coverage_status'] ?? 'not_started');
        if ($st === 'covers_suggested_window') {
            continue;
        }

        $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        $hist = $note !== '' && komodo_import_notes_triage_historical_flag($note);
        $oper = $note !== '' && komodo_import_notes_triage_operational_flag($note);

        if ($hist) {
            $historical[] = $row;

            continue;
        }

        if (komodo_triage_is_loaded_window_gap($st)) {
            $window[] = $row;

            continue;
        }

        if ($st === 'not_started' && $oper) {
            $special[] = $row;

            continue;
        }

        if ($st === 'not_started') {
            $pushNeeds($row);

            continue;
        }

        if ($oper) {
            $special[] = $row;

            continue;
        }

        $pushNeeds($row);
    }

    usort($needsEl, 'komodo_triage_priority_compare');
    usort($needsComp, 'komodo_triage_priority_compare');
    usort($window, 'komodo_triage_priority_compare');
    usort($historical, 'komodo_triage_priority_compare');
    usort($special, 'komodo_triage_priority_compare');

    $needsMerged = array_merge($needsEl, $needsComp);

    return [
        'needs_price_event_linked' => $needsEl,
        'needs_price_comparison' => $needsComp,
        'needs_price' => $needsMerged,
        'window_gaps' => $window,
        'historical_special' => $historical,
        'special_notes' => $special,
    ];
}

/**
 * Event-linked “next actions” split: normal vendor pulls vs backward window extension vs OTC/special source (read-only).
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return array{
 *   normal: list<array<string, mixed>>,
 *   older_history: list<array<string, mixed>>,
 *   special_source: list<array<string, mixed>>
 * }
 */
function komodo_price_import_triage_next_batch_model(array $securityRows, int $limit = 10): array
{
    $cands = [];
    foreach ($securityRows as $row) {
        if (($row['price_import_role'] ?? '') !== 'event_linked_security') {
            continue;
        }
        if ((string) ($row['coverage_status'] ?? '') === 'covers_suggested_window') {
            continue;
        }
        $cands[] = $row;
    }

    $specialSource = [];
    $olderHistory = [];
    $normal = [];
    foreach ($cands as $row) {
        $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        if (komodo_import_notes_triage_special_source_otc_adr($note)) {
            $specialSource[] = $row;

            continue;
        }
        if (komodo_triage_next_batch_needs_older_history($row)) {
            $olderHistory[] = $row;

            continue;
        }
        $normal[] = $row;
    }

    usort($specialSource, 'komodo_triage_next_download_batch_compare');
    usort($olderHistory, 'komodo_triage_next_download_batch_compare');
    usort($normal, 'komodo_triage_next_download_batch_compare');

    return [
        'normal' => array_slice($normal, 0, $limit),
        'older_history' => array_slice($olderHistory, 0, $limit),
        'special_source' => $specialSource,
    ];
}

/**
 * @param array<string, mixed> $a
 * @param array<string, mixed> $b
 */
function komodo_triage_next_download_batch_compare(array $a, array $b): int
{
    $ka = komodo_triage_next_download_batch_sort_key($a);
    $kb = komodo_triage_next_download_batch_sort_key($b);
    foreach ([0, 1, 2, 3] as $i) {
        if ($ka[$i] !== $kb[$i]) {
            return $ka[$i] <=> $kb[$i];
        }
    }

    return strcmp($ka[4], $kb[4]);
}

/**
 * @param array<string, mixed> $row
 *
 * @return array{0: int, 1: int, 2: int, 3: int, 4: string}
 */
function komodo_triage_next_download_batch_sort_key(array $row): array
{
    $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
    $hist = komodo_import_notes_triage_historical_flag($note) ? 1 : 0;
    $ev = (int) ($row['linked_event_count'] ?? 0);
    $st = (string) ($row['coverage_status'] ?? '');
    $urgency = match ($st) {
        'not_started' => 0,
        'missing_end_window', 'missing_start_window' => 1,
        default => 2,
    };
    $endBeforeStart = match ($st) {
        'missing_end_window' => 0,
        'missing_start_window' => 1,
        default => 0,
    };

    return [-$hist, -$ev, $urgency, $endBeforeStart, (string) ($row['ticker_symbol'] ?? '')];
}

/**
 * Counts for triage page summary strip (read-only).
 *
 * @param array<string, mixed>|null $securitySummary
 * @param list<array<string, mixed>> $securityRows
 * @param array{
 *   needs_price_event_linked: list<array<string, mixed>>,
 *   needs_price_comparison: list<array<string, mixed>>,
 *   needs_price: list<array<string, mixed>>,
 *   window_gaps: list<array<string, mixed>>,
 *   historical_special: list<array<string, mixed>>,
 *   special_notes: list<array<string, mixed>>
 * } $triageSlices
 *
 * @return array{
 *   open_total: int,
 *   needs_count: int,
 *   window_count: int,
 *   historical_count: int,
 *   special_notes_count: int,
 *   completed_plan_rows: int,
 *   plan_total: int,
 *   covers_from_summary: int|null
 * }
 */
function komodo_price_import_triage_dashboard(?array $securitySummary, array $securityRows, array $triageSlices): array
{
    $completed = 0;
    foreach ($securityRows as $row) {
        if ((string) ($row['coverage_status'] ?? '') === 'covers_suggested_window') {
            $completed++;
        }
    }

    $coversFromSummary = is_array($securitySummary)
        ? (int) ($securitySummary['covers_suggested_window'] ?? 0)
        : null;

    $needs = count($triageSlices['needs_price_event_linked']) + count($triageSlices['needs_price_comparison']);
    $window = count($triageSlices['window_gaps']);
    $hist = count($triageSlices['historical_special']);
    $spec = count($triageSlices['special_notes']);

    return [
        'open_total' => $needs + $window + $hist + $spec,
        'needs_count' => $needs,
        'window_count' => $window,
        'historical_count' => $hist,
        'special_notes_count' => $spec,
        'completed_plan_rows' => $completed,
        'plan_total' => count($securityRows),
        'covers_from_summary' => $coversFromSummary,
    ];
}

/**
 * Optional row highlight on triage tables (FB / multi-event event-linked).
 *
 * @param array<string, mixed> $row
 */
function komodo_triage_row_prominent_class(array $row): string
{
    $t = strtoupper((string) ($row['ticker_symbol'] ?? ''));
    $ev = (int) ($row['linked_event_count'] ?? 0);
    $isEl = (($row['price_import_role'] ?? '') === 'event_linked_security');
    if ($t === 'FB' || ($isEl && $ev >= 2)) {
        return 'triage-row--prominent';
    }

    return '';
}

/**
 * Split vw_market_data_import_plan rows into queue-oriented groups (no extra SQL).
 *
 * @param list<array<string, mixed>> $securityRows
 *
 * @return array{
 *   loaded_event_linked: list<array<string, mixed>>,
 *   pending_event_linked: list<array<string, mixed>>,
 *   loaded_comparison: list<array<string, mixed>>,
 *   pending_comparison: list<array<string, mixed>>,
 *   rows_with_import_notes: list<array<string, mixed>>,
 *   securities_with_price_rows: list<array<string, mixed>>
 * }
 */
function komodo_market_data_queue_slices(array $securityRows): array
{
    $loadedEl = [];
    $pendingEl = [];
    $loadedCp = [];
    $pendingCp = [];
    $notes = [];
    $withPx = [];

    foreach ($securityRows as $row) {
        $role = (string) ($row['price_import_role'] ?? '');
        $st = (string) ($row['coverage_status'] ?? 'not_started');
        $px = (int) ($row['price_rows'] ?? 0);

        $note = isset($row['import_notes']) ? trim((string) $row['import_notes']) : '';
        if ($note !== '') {
            $notes[] = $row;
        }
        if ($px > 0) {
            $withPx[] = $row;
        }

        $isEl = $role === 'event_linked_security';
        $isCp = $role === 'comparison_or_unlinked_security';

        if ($st === 'covers_suggested_window') {
            if ($isEl) {
                $loadedEl[] = $row;
            }
            if ($isCp) {
                $loadedCp[] = $row;
            }
        } else {
            if ($isEl) {
                $pendingEl[] = $row;
            }
            if ($isCp) {
                $pendingCp[] = $row;
            }
        }
    }

    $sortTicker = static function (array $a, array $b): int {
        return strcmp((string) ($a['ticker_symbol'] ?? ''), (string) ($b['ticker_symbol'] ?? ''));
    };

    usort($pendingEl, 'komodo_priority_attention_compare');
    usort($pendingCp, 'komodo_priority_attention_compare');
    usort($loadedEl, $sortTicker);
    usort($loadedCp, $sortTicker);
    usort($withPx, $sortTicker);
    usort($notes, $sortTicker);

    return [
        'loaded_event_linked' => $loadedEl,
        'pending_event_linked' => $pendingEl,
        'loaded_comparison' => $loadedCp,
        'pending_comparison' => $pendingCp,
        'rows_with_import_notes' => $notes,
        'securities_with_price_rows' => $withPx,
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
        'label' => 'Benchmark rows present',
        'badge_class' => 'coverage-badge--ok',
        'dek' => sprintf(
            'All %d benchmark index(es) have rows in index_daily_prices. Daily trading-day coverage should be reviewed before event-study calculations.',
            $total
        ),
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

    return 'Coverage telemetry looks complete in this portal — verify suggested windows, special import notes, and your external documentation; run estimation outside Komodo.';
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
        $next = 'Benchmark index rows are present — review daily coverage separately, then focus on event-linked security prices, then comparison tickers (outside Komodo).';
    }
    if ($indexStage === 'index_prices_partial') {
        $next = 'Finish remaining benchmark index series outside Komodo before relying on cross-asset QA.';
    }
    if ($covers === $totalSec && $totalSec > 0 && $indexStage === 'all_indexes_have_bars') {
        $next = 'Planned windows show coverage in telemetry — spot-check gaps and documentation before running estimation outside Komodo.';
    }

    $checklist = [
        'Outside Komodo: load index_daily_prices for every market_indexes row you need as a benchmark.',
        'Outside Komodo: load security_daily_prices starting with event_linked_security rows.',
        'Compare first/last bar dates against suggested_import_* on Price import triage and Price audit (±7 calendar-day slack at each end); Price coverage is the short readiness summary.',
        'Revisit Data gaps and import notes after each external load batch.',
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
        $rows = komodo_merge_vw_market_plan_import_note_overrides($rows ?: []);

        return ['ok' => true, 'rows' => $rows, 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * Sort aligned-density rows for review: event-linked first, then lowest ratio (sparse weekly-style series surface first).
 *
 * @param list<array<string, mixed>> $rows
 *
 * @return list<array<string, mixed>>
 */
function komodo_sort_aligned_daily_density_rows(array $rows): array
{
    usort($rows, static function (array $a, array $b): int {
        $elRank = static function (array $r): int {
            return (($r['price_import_role'] ?? '') === 'event_linked_security') ? 0 : 1;
        };
        if (($c = $elRank($a) <=> $elRank($b)) !== 0) {
            return $c;
        }
        $expA = (int) ($a['expected_trading_days'] ?? 0);
        $expB = (int) ($b['expected_trading_days'] ?? 0);
        if ($expA === 0 && $expB > 0) {
            return 1;
        }
        if ($expB === 0 && $expA > 0) {
            return -1;
        }
        $fa = isset($a['aligned_density_ratio']) && $a['aligned_density_ratio'] !== null && $a['aligned_density_ratio'] !== ''
            ? (float) $a['aligned_density_ratio']
            : 1.0;
        $fb = isset($b['aligned_density_ratio']) && $b['aligned_density_ratio'] !== null && $b['aligned_density_ratio'] !== ''
            ? (float) $b['aligned_density_ratio']
            : 1.0;

        return $fa <=> $fb;
    });

    return $rows;
}

/**
 * Trading-day density (aligned): loaded bars only on dates that exist in vw_us_trading_days within the suggested window.
 * Mirrors sql/komodo_audit_readonly.sql section 7 — complements span/slack checks; can flag sparse or weekly-style files.
 *
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_aligned_daily_density(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
  p.security_id,
  p.ticker_symbol,
  p.display_name,
  p.price_import_role,
  p.linked_event_count,
  p.suggested_import_start_date,
  p.suggested_import_end_date,
  MIN(sdp.trade_date) AS first_aligned_trade_date,
  MAX(sdp.trade_date) AS last_aligned_trade_date,
  COUNT(DISTINCT td.calendar_date) AS expected_trading_days,
  COUNT(DISTINCT sdp.trade_date) AS loaded_aligned_days,
  ROUND(
    COUNT(DISTINCT sdp.trade_date) / NULLIF(COUNT(DISTINCT td.calendar_date), 0),
    4
  ) AS aligned_density_ratio
FROM vw_market_data_import_plan p
LEFT JOIN vw_us_trading_days td
  ON td.calendar_date BETWEEN DATE(p.suggested_import_start_date) AND DATE(p.suggested_import_end_date)
LEFT JOIN security_daily_prices sdp
  ON sdp.security_id = p.security_id
 AND sdp.trade_date = td.calendar_date
GROUP BY
  p.security_id,
  p.ticker_symbol,
  p.display_name,
  p.price_import_role,
  p.linked_event_count,
  p.suggested_import_start_date,
  p.suggested_import_end_date
ORDER BY p.ticker_symbol
SQL;

    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return ['ok' => false, 'rows' => [], 'error' => 'query_failed'];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['ok' => true, 'rows' => komodo_sort_aligned_daily_density_rows($rows), 'error' => null];
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

    $slack = KOMODO_TRIAGE_WINDOW_SLACK_DAYS;
    $missStart = false;
    $missEnd = false;

    if (strcmp($first, $sStart) > 0) {
        $gap = komodo_calendar_day_diff_ymd($sStart, $first);
        if ($gap > $slack) {
            $missStart = true;
        }
    }

    if (strcmp($last, $sEnd) < 0) {
        $gap = komodo_calendar_day_diff_ymd($last, $sEnd);
        if ($gap > $slack) {
            $missEnd = true;
        }
    }

    if (!$missStart && !$missEnd) {
        return 'covers_suggested_window';
    }

    if ($missStart && $missEnd) {
        return 'missing_end_window';
    }

    if ($missEnd) {
        return 'missing_end_window';
    }

    return 'missing_start_window';
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

    usort($out, 'komodo_priority_attention_compare');

    return array_slice($out, 0, 15);
}

/**
 * Safe table count from dashboard context shape (read-only).
 *
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tableCountsSafe
 */
function komodo_data_gaps_table_count(array $tableCountsSafe, string $key): ?int
{
    if (!isset($tableCountsSafe[$key])) {
        return null;
    }
    $r = $tableCountsSafe[$key];
    if (($r['status'] ?? '') !== 'ok' || $r['count'] === null) {
        return null;
    }

    return $r['count'];
}

function komodo_data_gaps_card_severity_rank(string $sev): int
{
    return match ($sev) {
        'blocking_now' => 0,
        'needs_review' => 1,
        'expected_later' => 2,
        default => 3,
    };
}

/**
 * @param list<array<string, mixed>> $cards
 */
function komodo_data_gaps_finalize_cards(array &$cards): void
{
    foreach ($cards as &$c) {
        $sev = (string) ($c['severity'] ?? 'informational');
        $c['accent'] = match ($sev) {
            'blocking_now' => 'blocking',
            'needs_review' => 'review',
            'expected_later' => 'expected',
            default => 'info',
        };
    }
    unset($c);

    usort($cards, static function (array $a, array $b): int {
        $ra = komodo_data_gaps_card_severity_rank((string) ($a['severity'] ?? ''));
        $rb = komodo_data_gaps_card_severity_rank((string) ($b['severity'] ?? ''));
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });
}

/**
 * @param list<array<string, mixed>> $cards
 *
 * @return array{blocking_now: int, needs_review: int, expected_later: int, informational: int}
 */
function komodo_data_gaps_severity_tally(array $cards): array
{
    $tally = [
        'blocking_now' => 0,
        'needs_review' => 0,
        'expected_later' => 0,
        'informational' => 0,
    ];
    foreach ($cards as $c) {
        $k = (string) ($c['severity'] ?? 'informational');
        if (!isset($tally[$k])) {
            $k = 'informational';
        }
        $tally[$k]++;
    }

    return $tally;
}

/**
 * Readiness snapshot for the Data gaps page (no extra SQL — uses market_data + table counts).
 *
 * @param array<string, mixed> $marketData komodo_build_market_data_context()
 * @param array<string, array{identifier: string, count: ?int, status: string}> $tableCountsSafe
 *
 * @return array{
 *   telemetry_ok: bool,
 *   telemetry_partial: bool,
 *   conclusion: string,
 *   blocking_cards: list<array{title: string, count: int|null, count_label: string, dek: string, severity: string, severity_label: string, href: string}>,
 *   price_summary: array{
 *     planned: int,
 *     with_prices: int,
 *     not_started: int,
 *     event_linked_no_prices: int,
 *     event_linked_not_started_role: int,
 *     window_incomplete: int,
 *     covers_complete: int
 *   },
 *   lineage: array{paragraphs: list<string>, fb_row: ?array<string, mixed>, meta_row: ?array<string, mixed>, historical_tickers: list<string>},
 *   benchmark: array{dek: string, index_headline: string},
 *   event_study: array{runs: ?int, results: ?int, dek: string},
 *   technical: list<array{area: string, detail: string, severity: string, severity_label: string}>,
 *   severity_tally: array{blocking_now: int, needs_review: int, expected_later: int, informational: int},
 *   readiness_overall: ?array{state: string, label: string, badge_class: string},
 *   insights_headline: string,
 *   next_steps: list<string>,
 *   coverage_progress: array{covers: int, planned: int, pct: ?int},
 *   triage_open_total: int
 * }
 */
function komodo_build_data_gaps_view_model(array $marketData, bool $offlineMode, array $tableCountsSafe): array
{
    $telemetryOk = !$offlineMode && ($marketData['available'] ?? false);
    $telemetryPartial = (bool) ($marketData['partial'] ?? false);

    $securityRows = is_array($marketData['security_rows'] ?? null) ? $marketData['security_rows'] : [];
    $ss = is_array($marketData['security_summary'] ?? null) ? $marketData['security_summary'] : null;
    $is = is_array($marketData['index_summary'] ?? null) ? $marketData['index_summary'] : null;

    $windowGaps = is_array($marketData['triage_window_gaps'] ?? null) ? $marketData['triage_window_gaps'] : [];
    $historical = is_array($marketData['triage_historical_special'] ?? null) ? $marketData['triage_historical_special'] : [];

    $planned = $ss !== null ? (int) ($ss['total_securities'] ?? 0) : 0;
    $withPrices = $ss !== null ? (int) ($ss['securities_with_any_prices'] ?? 0) : 0;
    $notStartedAll = $ss !== null ? (int) ($ss['not_started'] ?? 0) : 0;

    $elNoPrices = 0;
    foreach ($securityRows as $row) {
        if (($row['price_import_role'] ?? '') !== 'event_linked_security') {
            continue;
        }
        if ((int) ($row['price_rows'] ?? 0) === 0) {
            $elNoPrices++;
        }
    }

    $elRole = ($ss !== null && is_array($ss['by_role']['event_linked_security'] ?? null))
        ? $ss['by_role']['event_linked_security']
        : null;
    $elNotStartedRole = $elRole !== null ? (int) ($elRole['not_started'] ?? 0) : 0;

    $windowIncomplete = count($windowGaps);
    $historicalCount = count($historical);

    $idxTotal = $is !== null ? (int) ($is['total_indexes'] ?? 0) : 0;
    $idxWithPx = $is !== null ? (int) ($is['indexes_with_any_prices'] ?? 0) : 0;
    $idxBarRows = $is !== null ? (int) ($is['total_index_price_rows'] ?? 0) : 0;

    $runs = komodo_data_gaps_table_count($tableCountsSafe, 'event_study_runs');
    $results = komodo_data_gaps_table_count($tableCountsSafe, 'event_study_results');
    $sources = komodo_data_gaps_table_count($tableCountsSafe, 'cyber_event_sources');

    $fbRow = null;
    $metaRow = null;
    foreach ($securityRows as $row) {
        $t = strtoupper((string) ($row['ticker_symbol'] ?? ''));
        if ($t === 'FB') {
            $fbRow = $row;
        }
        if ($t === 'META') {
            $metaRow = $row;
        }
    }

    $historicalTickers = [];
    foreach ($historical as $row) {
        $historicalTickers[] = (string) ($row['ticker_symbol'] ?? '');
        if (count($historicalTickers) >= 10) {
            break;
        }
    }

    if (!$telemetryOk) {
        $conclusion = 'Connect MariaDB and reload this page for live readiness telemetry. Offline mode cannot evaluate price windows, benchmarks, or import triage buckets.';

        $offlineCards = [
            [
                'title' => 'Live telemetry required',
                'count' => null,
                'count_label' => '—',
                'dek' => 'Configure app/config/local.php and use a live database connection to populate gap cards.',
                'severity' => 'informational',
                'severity_label' => 'Informational',
                'href' => 'index.php?page=market-data',
            ],
        ];
        komodo_data_gaps_finalize_cards($offlineCards);

        return [
            'telemetry_ok' => false,
            'telemetry_partial' => false,
            'conclusion' => $conclusion,
            'blocking_cards' => $offlineCards,
            'severity_tally' => komodo_data_gaps_severity_tally($offlineCards),
            'readiness_overall' => null,
            'insights_headline' => '',
            'next_steps' => [
                'Connect MariaDB with app/config/local.php, then reload this page.',
                'Open Market Data for the coverage snapshot, or Price import triage for the prioritized work list.',
            ],
            'coverage_progress' => ['covers' => 0, 'planned' => 0, 'pct' => null],
            'triage_open_total' => 0,
            'price_summary' => [
                'planned' => 0,
                'with_prices' => 0,
                'not_started' => 0,
                'event_linked_no_prices' => 0,
                'event_linked_not_started_role' => 0,
                'window_incomplete' => 0,
                'covers_complete' => 0,
            ],
            'lineage' => [
                'paragraphs' => [
                    komodo_fb_meta_lineage_import_policy_paragraph(),
                ],
                'fb_row' => null,
                'meta_row' => null,
                'historical_tickers' => [],
            ],
            'benchmark' => [
                'dek' => 'Benchmark index series require a live connection to summarize.',
                'index_headline' => 'Connect to evaluate benchmark rows.',
            ],
            'event_study' => [
                'runs' => $runs,
                'results' => $results,
                'dek' => 'Event-study outputs are produced outside this read-only portal.',
            ],
            'technical' => komodo_build_data_gaps_technical_rows($sources, $runs, $results, true),
        ];
    }

    $conclusion = 'Security price importing is underway and the ingestion pipeline is working. The dataset is not event-study ready yet because some event-linked securities still lack prices, some loaded securities do not fully cover their suggested windows, benchmark daily completeness needs review, and event-study output tables have not been generated.';

    if ($telemetryPartial) {
        $conclusion .= ' Some coverage queries were partial — confirm MariaDB views and retry.';
    }

    $blockingCards = [];

    $blockingCards[] = [
        'title' => 'Event-linked securities with no prices',
        'count' => $elNoPrices,
        'count_label' => (string) $elNoPrices,
        'dek' => 'Event-linked roles in vw_market_data_import_plan with zero rows in security_daily_prices.',
        'severity' => $elNoPrices > 0 ? 'blocking_now' : 'informational',
        'severity_label' => $elNoPrices > 0 ? 'Blocking now' : 'Informational',
        'href' => 'index.php?page=price-import-queue',
    ];

    $blockingCards[] = [
        'title' => 'Loaded but incomplete windows',
        'count' => $windowIncomplete,
        'count_label' => (string) $windowIncomplete,
        'dek' => 'Bars exist but first/last trade dates miss the suggested import window beyond ±' . KOMODO_TRIAGE_WINDOW_SLACK_DAYS . ' calendar days (same bucket as Price import triage → window gaps).',
        'severity' => $windowIncomplete > 0 ? 'blocking_now' : 'informational',
        'severity_label' => $windowIncomplete > 0 ? 'Blocking now' : 'Informational',
        'href' => 'index.php?page=price-import-queue',
    ];

    $blockingCards[] = [
        'title' => 'Historical ticker / lineage cases',
        'count' => $historicalCount,
        'count_label' => (string) $historicalCount,
        'dek' => 'import_notes flag historical tickers, renames, or continuity risk (triage “Historical ticker / special handling”). FB/Meta: META-labeled vendor history for the Facebook era belongs on the FB security_id for FB-tagged pre–June 2022 windows when that is your import mapping — not assumed from the ticker printed on the CSV.',
        'severity' => $historicalCount > 0 ? 'needs_review' : 'informational',
        'severity_label' => $historicalCount > 0 ? 'Needs review' : 'Informational',
        'href' => 'index.php?page=price-import-queue#tri-hist',
    ];

    $benchSeverity = ($idxTotal > 0 && $idxWithPx > 0) ? 'needs_review' : 'informational';
    $benchLabel = ($idxTotal > 0 && $idxWithPx > 0) ? 'Needs review' : 'Informational';
    $blockingCards[] = [
        'title' => 'Benchmark coverage warning',
        'count' => null,
        'count_label' => $idxWithPx > 0 ? (string) $idxWithPx . ' / ' . (string) $idxTotal . ' indexes' : '—',
        'dek' => $idxBarRows > 0
            ? number_format($idxBarRows) . ' total index price rows across configured benchmarks — daily trading-day completeness not yet confirmed in Komodo.'
            : 'No benchmark price rows loaded yet.',
        'severity' => $benchSeverity,
        'severity_label' => $benchLabel,
        'href' => 'index.php?page=price-audit#index-coverage',
    ];

    $esMissing = ($runs === 0 || $runs === null) && ($results === 0 || $results === null);
    $blockingCards[] = [
        'title' => 'Event-study outputs missing',
        'count' => null,
        'count_label' => $esMissing ? 'Not generated' : 'Present',
        'dek' => 'event_study_runs / event_study_results are empty — expected until an analysis phase runs outside this portal.',
        'severity' => 'expected_later',
        'severity_label' => 'Expected later',
        'href' => 'index.php?page=pipeline',
    ];

    komodo_data_gaps_finalize_cards($blockingCards);
    $severityTally = komodo_data_gaps_severity_tally($blockingCards);

    $pir = is_array($marketData['price_import_readiness'] ?? null) ? $marketData['price_import_readiness'] : null;
    $insights = is_array($marketData['insights'] ?? null) ? $marketData['insights'] : [];
    $triageDashLive = is_array($marketData['triage_dashboard'] ?? null) ? $marketData['triage_dashboard'] : [];
    $triageOpenTotal = (int) ($triageDashLive['open_total'] ?? 0);

    $nextSteps = [];
    $pushStep = static function (string $s) use (&$nextSteps): void {
        $s = trim($s);
        if ($s === '' || in_array($s, $nextSteps, true)) {
            return;
        }
        $nextSteps[] = $s;
    };
    if ($pir !== null) {
        $pushStep((string) ($pir['next_action'] ?? ''));
    }
    $pushStep((string) ($insights['next_step'] ?? ''));
    foreach ($insights['checklist'] ?? [] as $item) {
        if (!is_string($item) || $item === '') {
            continue;
        }
        $pushStep($item);
        if (count($nextSteps) >= 6) {
            break;
        }
    }
    $nextSteps = array_slice($nextSteps, 0, 6);

    $readinessOverall = ($pir !== null && is_array($pir['overall'] ?? null)) ? $pir['overall'] : null;
    $insightsHeadline = trim((string) ($insights['headline'] ?? ''));

    $coversComplete = $ss !== null ? (int) ($ss['covers_suggested_window'] ?? 0) : 0;
    $coveragePct = $planned > 0 ? (int) max(0, min(100, round(100 * $coversComplete / $planned))) : null;

    $indexHeadline = $idxBarRows > 0
        ? sprintf('%s index price rows across %d of %d configured benchmark(s).', number_format($idxBarRows), $idxWithPx, $idxTotal)
        : 'Benchmark index prices not loaded or unavailable.';

    $lineageParagraphs = [komodo_fb_meta_lineage_import_policy_paragraph()];
    if ($fbRow !== null && (int) ($fbRow['price_rows'] ?? 0) === 0) {
        $lineageParagraphs[] = 'Live plan: FB currently shows zero price rows — load FB-era daily bars onto the FB security_id (including META-labeled vendor history mapped at import time), or document explicit substitution rules.';
    }
    if ($fbRow !== null && (int) ($fbRow['price_rows'] ?? 0) > 0) {
        $lineageParagraphs[] = 'Live plan: FB shows price rows — confirm they match your event windows; META-labeled source files are fine if the load mapped rows to the FB security_id.';
    }
    if ($metaRow !== null && (int) ($metaRow['price_rows'] ?? 0) > 0) {
        $lineageParagraphs[] = 'META has its own security row in the plan; rows stored only under META do not by themselves satisfy FB-tagged pre-rename windows.';
    }

    return [
        'telemetry_ok' => true,
        'telemetry_partial' => $telemetryPartial,
        'conclusion' => $conclusion,
        'blocking_cards' => $blockingCards,
        'severity_tally' => $severityTally,
        'readiness_overall' => $readinessOverall,
        'insights_headline' => $insightsHeadline,
        'next_steps' => $nextSteps,
        'coverage_progress' => [
            'covers' => $coversComplete,
            'planned' => $planned,
            'pct' => $coveragePct,
        ],
        'triage_open_total' => $triageOpenTotal,
        'price_summary' => [
            'planned' => $planned,
            'with_prices' => $withPrices,
            'not_started' => $notStartedAll,
            'event_linked_no_prices' => $elNoPrices,
            'event_linked_not_started_role' => $elNotStartedRole,
            'window_incomplete' => $windowIncomplete,
            'covers_complete' => $coversComplete,
        ],
        'lineage' => [
            'paragraphs' => $lineageParagraphs,
            'fb_row' => $fbRow,
            'meta_row' => $metaRow,
            'historical_tickers' => $historicalTickers,
        ],
        'benchmark' => [
            'dek' => 'Benchmark rows are present for all configured indexes, but daily trading-day completeness is not yet confirmed. Current benchmark/security files may be weekly or monthly, so imports should be treated as coverage tracking and pipeline validation until daily coverage is improved.',
            'index_headline' => $indexHeadline,
        ],
        'event_study' => [
            'runs' => $runs,
            'results' => $results,
            'dek' => 'Event-study result tables are part of a future analysis phase. Empty tables are expected in the import-and-coverage stage — not an application error.',
        ],
        'technical' => komodo_build_data_gaps_technical_rows($sources, $runs, $results, false),
    ];
}

/**
 * @return list<array{area: string, detail: string, severity: string, severity_label: string}>
 */
function komodo_build_data_gaps_technical_rows(?int $sources, ?int $runs, ?int $results, bool $offlineMode): array
{
    $rows = [];

    if ($offlineMode) {
        $rows[] = [
            'area' => 'cyber_event_sources',
            'detail' => 'Table count unavailable offline.',
            'severity' => 'informational',
            'severity_label' => 'Informational',
        ];
    } else {
        $srcN = $sources ?? null;
        $detail = $srcN === null ? 'Count unavailable (metric degraded).' : ($srcN === 0 ? '0 rows — provenance not populated.' : sprintf('%d row(s) loaded.', $srcN));
        $rows[] = [
            'area' => 'cyber_event_sources',
            'detail' => $detail,
            'severity' => ($srcN === 0) ? 'needs_review' : 'informational',
            'severity_label' => ($srcN === 0) ? 'Needs review' : 'Informational',
        ];
    }

    $runsU = $runs ?? 0;
    $resU = $results ?? 0;
    $runsLabel = $offlineMode ? 'Offline — expected empty in reference mode.' : (($runs === null) ? 'Count unavailable.' : sprintf('%d row(s).', $runsU));
    $resLabel = $offlineMode ? 'Offline — expected empty in reference mode.' : (($results === null) ? 'Count unavailable.' : sprintf('%d row(s).', $resU));
    $rows[] = [
        'area' => 'event_study_runs',
        'detail' => $runsLabel,
        'severity' => ($offlineMode || $runsU === 0) ? 'expected_later' : 'informational',
        'severity_label' => ($offlineMode || $runsU === 0) ? 'Expected later' : 'Informational',
    ];
    $rows[] = [
        'area' => 'event_study_results',
        'detail' => $resLabel,
        'severity' => ($offlineMode || $resU === 0) ? 'expected_later' : 'informational',
        'severity_label' => ($offlineMode || $resU === 0) ? 'Expected later' : 'Informational',
    ];

    return $rows;
}

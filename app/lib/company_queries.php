<?php

declare(strict_types=1);

/**
 * Read-only company/security exploration helpers.
 * Driving rowset: vw_market_data_import_plan (security/ticker grain).
 */

require_once __DIR__ . '/market_data_queries.php';

/**
 * @param array<string, mixed> $baseContext
 *
 * @return array{
 *   available: bool,
 *   partial: bool,
 *   mode: string,
 *   message: string,
 *   errors: list<string>,
 *   summary: ?array<string, int>,
 *   sector_summary: list<array{label: string, count: int}>,
 *   industry_summary: list<array{label: string, count: int}>,
 *   rows: list<array<string, mixed>>,
 *   attention: array<string, list<array<string, mixed>>>
 * }
 *
 * Attention keys: event_linked_without_prices, event_linked_window_issues, import_notes (internal), multiple_event_companies, missing_sector_or_industry
 */
function komodo_build_companies_context(?PDO $pdo, array $baseContext): array
{
    unset($baseContext);

    $empty = [
        'available' => false,
        'partial' => false,
        'mode' => 'offline',
        'message' => 'Company exploration requires a database connection.',
        'errors' => [],
        'summary' => null,
        'sector_summary' => [],
        'industry_summary' => [],
        'rows' => [],
        'attention' => [
            'event_linked_without_prices' => [],
            'event_linked_window_issues' => [],
            'import_notes' => [],
            'multiple_event_companies' => [],
            'missing_sector_or_industry' => [],
        ],
    ];

    if ($pdo === null) {
        return $empty;
    }

    $errors = [];
    $partial = false;

    $fetch = komodo_fetch_company_security_rows($pdo);
    if (!$fetch['ok']) {
        $errors[] = 'Company/security rows could not be loaded.';
        $partial = true;
        $rows = [];
    } else {
        $rows = $fetch['rows'];
        foreach ($rows as &$r) {
            $r['coverage_status'] = komodo_company_security_coverage_status($r);
        }
        unset($r);
    }

    $summary = $fetch['ok'] ? komodo_summarize_companies($rows) : null;
    $sectorSummary = $fetch['ok'] ? komodo_summarize_sectors($rows) : [];
    $industrySummary = $fetch['ok'] ? komodo_summarize_industries($rows) : [];
    $attention = $fetch['ok'] ? komodo_company_attention_items($rows) : $empty['attention'];

    $mode = $partial ? 'partial' : 'live';
    $message = $partial
        ? 'Some company exploration sections could not be loaded.'
        : 'Live company/security exploration from MariaDB (read-only).';

    return [
        'available' => true,
        'partial' => $partial,
        'mode' => $mode,
        'message' => $message,
        'errors' => $errors,
        'summary' => $summary,
        'sector_summary' => $sectorSummary,
        'industry_summary' => $industrySummary,
        'rows' => $rows,
        'attention' => $attention,
    ];
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_company_security_rows(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    plan.security_id,
    plan.ticker_symbol,
    plan.security_name,
    plan.exchange_code,
    plan.price_import_role,
    plan.linked_event_count,
    plan.suggested_import_start_date,
    plan.suggested_import_end_date,
    plan.import_notes,

    s.company_id,
    s.start_date AS security_start_date,
    s.end_date AS security_end_date,
    s.is_active AS security_is_active,

    c.legal_name,
    c.display_name AS company_name,
    c.company_role,
    c.notes AS company_notes,

    sec.sector_name,
    ind.industry_name,

    COALESCE(px.price_rows, 0) AS price_rows,
    px.first_price_date,
    px.last_price_date,

    COALESCE(ev.security_event_count, 0) AS security_event_count,
    COALESCE(cev.company_event_count, 0) AS company_event_count
FROM vw_market_data_import_plan plan
JOIN securities s
    ON plan.security_id = s.security_id
JOIN companies c
    ON s.company_id = c.company_id
LEFT JOIN sectors sec
    ON c.sector_id = sec.sector_id
LEFT JOIN industries ind
    ON c.industry_id = ind.industry_id
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
LEFT JOIN (
    SELECT
        security_id,
        COUNT(DISTINCT cyber_event_id) AS security_event_count
    FROM cyber_event_securities
    GROUP BY security_id
) ev
    ON ev.security_id = plan.security_id
LEFT JOIN (
    SELECT
        s2.company_id,
        COUNT(DISTINCT ces2.cyber_event_id) AS company_event_count
    FROM cyber_event_securities ces2
    JOIN securities s2
        ON ces2.security_id = s2.security_id
    GROUP BY s2.company_id
) cev
    ON cev.company_id = s.company_id
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
 * Compute coverage status — delegates to {@see komodo_security_coverage_status} (calendar-day slack, same as triage / Price coverage).
 *
 * @param array<string, mixed> $row
 */
function komodo_company_security_coverage_status(array $row): string
{
    return komodo_security_coverage_status($row);
}

/**
 * Compact import-plan flags for the Companies catalog table (full text stays in tooltip / Price audit).
 *
 * @return list<array{code: string, label: string}>
 */
function komodo_company_security_catalog_flags(array $r): array
{
    $flags = [];
    $seen = [];
    $add = static function (string $code, string $label) use (&$flags, &$seen): void {
        if (isset($seen[$code])) {
            return;
        }
        $seen[$code] = true;
        $flags[] = ['code' => $code, 'label' => $label];
    };

    $note = isset($r['import_notes']) ? trim((string) $r['import_notes']) : '';

    if ($note !== '' && komodo_import_notes_triage_historical_flag($note)) {
        $add('hist', 'Historical ticker');
    }

    if ((string) ($r['company_role'] ?? '') === 'wildcard_volatility_control') {
        $add('vol', 'Volatility test');
    }

    if ($note !== '' && komodo_import_notes_triage_special_source_otc_adr($note)) {
        $add('otc', 'OTC source');
    } else {
        $nl = strtolower($note);
        if ($note !== '' && str_contains($nl, 'otc') && str_contains($nl, 'adr')) {
            $add('otc', 'OTC source');
        }
    }

    if ($note !== '' && preg_match('/\bipo\b|listing|spac|delist/', strtolower($note))) {
        $add('ipo', 'IPO / listing');
    }

    return $flags;
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array<string, int>
 */
function komodo_summarize_companies(array $rows): array
{
    $companies = [];
    $companiesWithEvents = [];
    $companiesWithMultipleEvents = [];

    $totalSecurities = 0;
    $eventLinked = 0;
    $comparison = 0;
    $withPrices = 0;
    $withoutPrices = 0;
    $withNotes = 0;
    $eventLinkedNoPrices = 0;
    $eventLinkedNeedingPriceAttention = 0;
    $missingClassificationCompanies = [];

    foreach ($rows as $r) {
        $totalSecurities++;
        $cid = (string) ($r['company_id'] ?? '');
        if ($cid !== '') {
            $companies[$cid] = true;
        }

        $role = (string) ($r['price_import_role'] ?? '');
        if ($role === 'event_linked_security') {
            $eventLinked++;
            $prEl = (int) ($r['price_rows'] ?? 0);
            if ($prEl === 0) {
                $eventLinkedNoPrices++;
            }
            $stEl = (string) ($r['coverage_status'] ?? '');
            if ($stEl !== '' && $stEl !== 'covers_suggested_window') {
                $eventLinkedNeedingPriceAttention++;
            }
        } else {
            $comparison++;
        }

        $pr = (int) ($r['price_rows'] ?? 0);
        if ($pr > 0) {
            $withPrices++;
        } else {
            $withoutPrices++;
        }

        $note = isset($r['import_notes']) ? trim((string) $r['import_notes']) : '';
        if ($note !== '') {
            $withNotes++;
        }

        $secN = (string) ($r['sector_name'] ?? '');
        $indN = (string) ($r['industry_name'] ?? '');
        if ($cid !== '' && ($secN === '' || $indN === '')) {
            $missingClassificationCompanies[$cid] = true;
        }

        $secEv = (int) ($r['security_event_count'] ?? 0);
        $planEv = (int) ($r['linked_event_count'] ?? 0);
        if ($secEv > 0 || $planEv > 0) {
            if ($cid !== '') {
                $companiesWithEvents[$cid] = true;
            }
        }

        $cev = (int) ($r['company_event_count'] ?? 0);
        if ($cev >= 2 && $cid !== '') {
            $companiesWithMultipleEvents[$cid] = true;
        }
    }

    $totalCompanies = count($companies);
    $withEvCount = count($companiesWithEvents);

    return [
        'total_companies' => $totalCompanies,
        'total_securities' => $totalSecurities,
        'event_linked_securities' => $eventLinked,
        'comparison_or_unlinked_securities' => $comparison,
        'companies_with_events' => $withEvCount,
        'companies_without_events' => max(0, $totalCompanies - $withEvCount),
        'securities_with_prices' => $withPrices,
        'securities_without_prices' => $withoutPrices,
        'companies_with_multiple_events' => count($companiesWithMultipleEvents),
        'securities_with_import_notes' => $withNotes,
        'event_linked_without_prices_count' => $eventLinkedNoPrices,
        'event_linked_needing_price_attention_count' => $eventLinkedNeedingPriceAttention,
        'missing_classification_companies' => count($missingClassificationCompanies),
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{label: string, count: int}>
 */
function komodo_summarize_sectors(array $rows): array
{
    $by = [];
    $seen = [];
    foreach ($rows as $r) {
        $cid = (string) ($r['company_id'] ?? '');
        if ($cid === '' || isset($seen[$cid])) {
            continue;
        }
        $seen[$cid] = true;
        $label = (string) ($r['sector_name'] ?? '');
        $label = $label !== '' ? $label : '—';
        $by[$label] = ($by[$label] ?? 0) + 1;
    }

    arsort($by);
    $out = [];
    foreach ($by as $label => $count) {
        $out[] = ['label' => $label, 'count' => (int) $count];
    }
    return array_slice($out, 0, 10);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array{label: string, count: int}>
 */
function komodo_summarize_industries(array $rows): array
{
    $by = [];
    $seen = [];
    foreach ($rows as $r) {
        $cid = (string) ($r['company_id'] ?? '');
        if ($cid === '' || isset($seen[$cid])) {
            continue;
        }
        $seen[$cid] = true;
        $label = (string) ($r['industry_name'] ?? '');
        $label = $label !== '' ? $label : '—';
        $by[$label] = ($by[$label] ?? 0) + 1;
    }

    arsort($by);
    $out = [];
    foreach ($by as $label => $count) {
        $out[] = ['label' => $label, 'count' => (int) $count];
    }
    return array_slice($out, 0, 10);
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array<string, list<array<string, mixed>>>
 */
function komodo_company_attention_items(array $rows): array
{
    $eventLinkedNoPrices = [];
    $eventLinkedWindowIssues = [];
    $notes = [];
    $missingMeta = [];

    $companyMulti = [];

    foreach ($rows as $r) {
        $role = (string) ($r['price_import_role'] ?? '');
        $pr = (int) ($r['price_rows'] ?? 0);
        $note = isset($r['import_notes']) ? trim((string) $r['import_notes']) : '';

        if ($role === 'event_linked_security' && $pr === 0) {
            $eventLinkedNoPrices[] = komodo_attention_row($r);
        }

        if ($role === 'event_linked_security' && $pr > 0) {
            $st = (string) ($r['coverage_status'] ?? '');
            if ($st !== '' && $st !== 'covers_suggested_window') {
                $wi = komodo_attention_row($r);
                $wi['coverage_status'] = $st;
                $eventLinkedWindowIssues[] = $wi;
            }
        }

        if ($note !== '') {
            $item = komodo_attention_row($r);
            $item['import_notes'] = $note;
            $notes[] = $item;
        }

        $sec = (string) ($r['sector_name'] ?? '');
        $ind = (string) ($r['industry_name'] ?? '');
        if ($sec === '' || $ind === '') {
            $missingMeta[] = komodo_attention_row($r);
        }

        $cid = (string) ($r['company_id'] ?? '');
        $cev = (int) ($r['company_event_count'] ?? 0);
        if ($cid !== '' && $cev >= 2) {
            $companyMulti[$cid] = [
                'company_id' => $cid,
                'company_name' => (string) ($r['company_name'] ?? ($r['legal_name'] ?? '')),
                'company_event_count' => $cev,
            ];
        }
    }

    usort($eventLinkedNoPrices, static fn ($a, $b) => strcmp((string) $a['ticker_symbol'], (string) $b['ticker_symbol']));
    usort($eventLinkedWindowIssues, static fn ($a, $b) => strcmp((string) $a['ticker_symbol'], (string) $b['ticker_symbol']));
    usort($notes, static fn ($a, $b) => strcmp((string) $a['ticker_symbol'], (string) $b['ticker_symbol']));
    usort($missingMeta, static fn ($a, $b) => strcmp((string) $a['ticker_symbol'], (string) $b['ticker_symbol']));

    $multiCompanies = array_values($companyMulti);
    usort($multiCompanies, static function (array $a, array $b): int {
        $ca = (int) ($a['company_event_count'] ?? 0);
        $cb = (int) ($b['company_event_count'] ?? 0);
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        return strcmp((string) ($a['company_name'] ?? ''), (string) ($b['company_name'] ?? ''));
    });

    return [
        'event_linked_without_prices' => array_slice($eventLinkedNoPrices, 0, 15),
        'event_linked_window_issues' => array_slice($eventLinkedWindowIssues, 0, 15),
        'import_notes' => array_slice($notes, 0, 15),
        'multiple_event_companies' => array_slice($multiCompanies, 0, 15),
        'missing_sector_or_industry' => array_slice($missingMeta, 0, 15),
    ];
}

/**
 * Compact attention-queue item.
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function komodo_attention_row(array $r): array
{
    return [
        'company_id' => $r['company_id'] ?? null,
        'security_id' => $r['security_id'] ?? null,
        'company_name' => $r['company_name'] ?? ($r['legal_name'] ?? ''),
        'ticker_symbol' => $r['ticker_symbol'] ?? '',
        'price_import_role' => $r['price_import_role'] ?? '',
        'security_event_count' => $r['security_event_count'] ?? null,
        'linked_event_count' => $r['linked_event_count'] ?? null,
        'company_event_count' => $r['company_event_count'] ?? null,
    ];
}

/**
 * Company drilldown context (read-only).
 *
 * @param array<string, mixed> $baseContext
 *
 * @return array{
 *   available: bool,
 *   partial: bool,
 *   mode: string,
 *   message: string,
 *   errors: list<string>,
 *   not_found: bool,
 *   company_id: int,
 *   profile: ?array<string, mixed>,
 *   securities: list<array<string, mixed>>,
 *   events: list<array<string, mixed>>,
 *   summary: ?array<string, mixed>,
 *   trace_sources: list<string>
 * }
 */
function komodo_company_context_invalid_id(): array
{
    return [
        'available' => false,
        'partial' => false,
        'mode' => 'invalid',
        'message' => 'Invalid company id.',
        'errors' => [],
        'not_found' => false,
        'company_id' => 0,
        'profile' => null,
        'securities' => [],
        'events' => [],
        'summary' => null,
        'trace_sources' => komodo_company_trace_sources(),
    ];
}

function komodo_build_company_context(?PDO $pdo, array $baseContext, int $companyId): array
{
    unset($baseContext);

    $offline = [
        'available' => false,
        'partial' => false,
        'mode' => 'offline',
        'message' => 'Company detail requires a database connection.',
        'errors' => [],
        'not_found' => false,
        'company_id' => $companyId,
        'profile' => null,
        'securities' => [],
        'events' => [],
        'summary' => null,
        'trace_sources' => komodo_company_trace_sources(),
    ];

    if ($pdo === null) {
        return $offline;
    }

    $errors = [];
    $partial = false;

    $prof = komodo_fetch_company_profile($pdo, $companyId);
    if (!$prof['ok']) {
        $errors[] = 'Company profile could not be loaded.';
        $partial = true;
        $profile = null;
    } else {
        $profile = $prof['row'];
    }

    if ($profile === null) {
        // Distinguish not-found vs query error.
        $notFound = $prof['ok'] && $prof['row'] === null;
        return [
            'available' => true,
            'partial' => $partial,
            'mode' => $partial ? 'partial' : 'live',
            'message' => $notFound ? 'Company not found.' : 'Company detail unavailable.',
            'errors' => $errors,
            'not_found' => $notFound,
            'company_id' => $companyId,
            'profile' => null,
            'securities' => [],
            'events' => [],
            'summary' => null,
            'trace_sources' => komodo_company_trace_sources(),
        ];
    }

    $secs = komodo_fetch_company_securities($pdo, $companyId);
    if (!$secs['ok']) {
        $errors[] = 'Company securities could not be loaded.';
        $partial = true;
        $securities = [];
    } else {
        $securities = $secs['rows'];
        $secIds = [];
        foreach ($securities as $r0) {
            $sid = (int) ($r0['security_id'] ?? 0);
            if ($sid > 0) {
                $secIds[] = $sid;
            }
        }
        $densityBySec = [];
        $densityFetch = komodo_fetch_aligned_daily_density_for_security_ids($pdo, $secIds);
        if (!$densityFetch['ok']) {
            $errors[] = 'Aligned daily density could not be loaded for these securities.';
            $partial = true;
        } else {
            foreach ($densityFetch['rows'] as $drow) {
                $sid = (int) ($drow['security_id'] ?? 0);
                if ($sid > 0) {
                    $densityBySec[$sid] = $drow;
                }
            }
        }
        foreach ($securities as &$r) {
            $r['coverage_status'] = komodo_company_security_coverage_status($r);
            $sid = (int) ($r['security_id'] ?? 0);
            $r['aligned_daily_density'] = $densityBySec[$sid] ?? null;
        }
        unset($r);
    }

    $ev = komodo_fetch_company_events($pdo, $companyId);
    if (!$ev['ok']) {
        $errors[] = 'Company events could not be loaded.';
        $partial = true;
        $events = [];
    } else {
        $events = $ev['rows'];
    }

    $eventIds = [];
    foreach ($events as $e) {
        $id = isset($e['cyber_event_id']) ? (int) $e['cyber_event_id'] : 0;
        if ($id > 0) {
            $eventIds[] = $id;
        }
    }
    $eventIds = array_values(array_unique($eventIds));

    if ($eventIds !== []) {
        $dates = komodo_fetch_company_event_dates($pdo, $eventIds);
        if (!$dates['ok']) {
            $errors[] = 'Some event date details are unavailable.';
            $partial = true;
        } else {
            $events = komodo_merge_event_dates($events, $dates['rows']);
        }

        $ready = komodo_fetch_company_event_readiness($pdo, $eventIds);
        if (!$ready['ok']) {
            $errors[] = 'Some event readiness details are unavailable.';
            $partial = true;
        } else {
            $events = komodo_merge_event_readiness($events, $ready['rows']);
        }
    }

    $summary = komodo_summarize_company_detail($profile, $securities, $events);

    $mode = $partial ? 'partial' : 'live';
    $message = $partial ? 'Company detail loaded with partial readiness data.' : 'Live company detail from MariaDB (read-only).';

    return [
        'available' => true,
        'partial' => $partial,
        'mode' => $mode,
        'message' => $message,
        'errors' => $errors,
        'not_found' => false,
        'company_id' => $companyId,
        'profile' => $profile,
        'securities' => $securities,
        'events' => $events,
        'summary' => $summary,
        'trace_sources' => komodo_company_trace_sources(),
    ];
}

/**
 * @return array{ok: bool, row: ?array<string, mixed>, error: ?string}
 */
function komodo_fetch_company_profile(PDO $pdo, int $companyId): array
{
    $sql = <<<'SQL'
SELECT
    c.company_id,
    c.legal_name,
    c.display_name,
    c.company_role,
    c.notes,
    sec.sector_name,
    ind.industry_name
FROM companies c
LEFT JOIN sectors sec
    ON c.sector_id = sec.sector_id
LEFT JOIN industries ind
    ON c.industry_id = ind.industry_id
WHERE c.company_id = :company_id
LIMIT 1
SQL;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['ok' => true, 'row' => null, 'error' => null];
        }
        return ['ok' => true, 'row' => $row, 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'row' => null, 'error' => 'exception'];
    }
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_company_securities(PDO $pdo, int $companyId): array
{
    $sql = <<<'SQL'
SELECT
    s.security_id,
    s.ticker_symbol,
    s.security_name,
    ex.exchange_code,
    s.start_date,
    s.end_date,
    s.is_active,

    plan.price_import_role,
    plan.linked_event_count,
    plan.suggested_import_start_date,
    plan.suggested_import_end_date,
    plan.import_notes,

    COALESCE(px.price_rows, 0) AS price_rows,
    px.first_price_date,
    px.last_price_date,

    COALESCE(ev.security_event_count, 0) AS security_event_count
FROM securities s
LEFT JOIN exchanges ex
    ON s.exchange_id = ex.exchange_id
LEFT JOIN vw_market_data_import_plan plan
    ON s.security_id = plan.security_id
LEFT JOIN (
    SELECT
        security_id,
        COUNT(*) AS price_rows,
        MIN(trade_date) AS first_price_date,
        MAX(trade_date) AS last_price_date
    FROM security_daily_prices
    GROUP BY security_id
) px
    ON px.security_id = s.security_id
LEFT JOIN (
    SELECT
        security_id,
        COUNT(DISTINCT cyber_event_id) AS security_event_count
    FROM cyber_event_securities
    GROUP BY security_id
) ev
    ON ev.security_id = s.security_id
WHERE s.company_id = :company_id
ORDER BY
    CASE
        WHEN plan.price_import_role = 'event_linked_security' THEN 1
        ELSE 2
    END,
    s.ticker_symbol
SQL;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = komodo_merge_vw_market_plan_import_note_overrides($rows ?: []);

        return ['ok' => true, 'rows' => $rows, 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * Prefer security-link based events for company drilldown.
 *
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_company_events(PDO $pdo, int $companyId): array
{
    $sql = <<<'SQL'
SELECT DISTINCT
    ce.cyber_event_id,
    ce.event_name,
    ce.event_type,
    ce.severity_level,
    ce.confidence_level,
    ce.company_id
FROM securities s
JOIN cyber_event_securities ces
    ON ces.security_id = s.security_id
JOIN cyber_events ce
    ON ce.cyber_event_id = ces.cyber_event_id
WHERE s.company_id = :company_id
ORDER BY ce.cyber_event_id DESC
SQL;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * @param list<int> $eventIds
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_company_event_dates(PDO $pdo, array $eventIds): array
{
    if ($eventIds === []) {
        return ['ok' => true, 'rows' => [], 'error' => null];
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $sql = 'SELECT cyber_event_id, date_type, event_date FROM cyber_event_dates WHERE cyber_event_id IN (' . $placeholders . ')';

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($eventIds as $i => $id) {
            $stmt->bindValue($i + 1, (int) $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * Lightweight readiness fetch. If schema mismatch occurs, return ok=false and the page will degrade.
 *
 * @param list<int> $eventIds
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_company_event_readiness(PDO $pdo, array $eventIds): array
{
    if ($eventIds === []) {
        return ['ok' => true, 'rows' => [], 'error' => null];
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    // Column names may evolve; keep this minimal.
    $sql = 'SELECT * FROM vw_event_study_event_readiness WHERE cyber_event_id IN (' . $placeholders . ')';

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($eventIds as $i => $id) {
            $stmt->bindValue($i + 1, (int) $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['ok' => true, 'rows' => $rows ?: [], 'error' => null];
    } catch (Throwable) {
        return ['ok' => false, 'rows' => [], 'error' => 'exception'];
    }
}

/**
 * @param list<array<string, mixed>> $events
 * @param list<array<string, mixed>> $dateRows
 * @return list<array<string, mixed>>
 */
function komodo_merge_event_dates(array $events, array $dateRows): array
{
    $by = [];
    foreach ($dateRows as $r) {
        $id = (int) ($r['cyber_event_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $dt = (string) ($r['date_type'] ?? '');
        $d = komodo_normalize_date_string($r['event_date'] ?? null);
        if ($dt === '' || $d === null) {
            continue;
        }
        $by[$id][$dt] = $d;
    }

    foreach ($events as &$e) {
        $id = (int) ($e['cyber_event_id'] ?? 0);
        $e['dates'] = $by[$id] ?? [];
    }
    unset($e);
    return $events;
}

/**
 * @param list<array<string, mixed>> $events
 * @param list<array<string, mixed>> $readyRows
 * @return list<array<string, mixed>>
 */
function komodo_merge_event_readiness(array $events, array $readyRows): array
{
    $by = [];
    foreach ($readyRows as $r) {
        $id = (int) ($r['cyber_event_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $by[$id] = $r;
    }

    foreach ($events as &$e) {
        $id = (int) ($e['cyber_event_id'] ?? 0);
        $e['readiness'] = $by[$id] ?? null;
    }
    unset($e);
    return $events;
}

/**
 * @param array<string, mixed> $profile
 * @param list<array<string, mixed>> $securities
 * @param list<array<string, mixed>> $events
 *
 * @return array<string, mixed>
 */
function komodo_summarize_company_detail(array $profile, array $securities, array $events): array
{
    $totalSecs = count($securities);
    $eventLinked = 0;
    $withoutPrices = 0;
    $withNotes = 0;
    $withPrices = 0;

    foreach ($securities as $s) {
        $role = (string) ($s['price_import_role'] ?? '');
        if ($role === 'event_linked_security') {
            $eventLinked++;
        }
        $pr = (int) ($s['price_rows'] ?? 0);
        if ($pr > 0) {
            $withPrices++;
        } else {
            $withoutPrices++;
        }
        $note = isset($s['import_notes']) ? trim((string) $s['import_notes']) : '';
        if ($note !== '') {
            $withNotes++;
        }
    }

    return [
        'company_id' => (int) ($profile['company_id'] ?? 0),
        'total_securities' => $totalSecs,
        'linked_events' => count($events),
        'event_linked_securities' => $eventLinked,
        'securities_without_prices' => $withoutPrices,
        'securities_with_prices' => $withPrices,
        'securities_with_import_notes' => $withNotes,
    ];
}

/**
 * @return list<string>
 */
function komodo_company_trace_sources(): array
{
    return [
        'companies',
        'sectors',
        'industries',
        'securities',
        'exchanges',
        'vw_market_data_import_plan',
        'security_daily_prices',
        'cyber_event_securities',
        'cyber_events',
        'cyber_event_dates',
        'vw_event_study_event_readiness',
        'vw_event_research_readiness_flags',
        'vw_event_contamination_flags',
        'vw_event_impact_quality_flags',
    ];
}


<?php

declare(strict_types=1);

/**
 * Read-only cyber-events list context for the Events page.
 */

require_once __DIR__ . '/market_data_queries.php';

/**
 * @return list<string>
 */
function komodo_event_trace_sources(): array
{
    return [
        'cyber_events',
        'companies',
        'cyber_event_securities',
        'securities',
        'cyber_event_dates',
        'cyber_event_sources',
        'vw_event_study_event_readiness',
        'vw_event_research_readiness_flags',
        'vw_event_contamination_flags',
        'vw_event_impact_quality_flags',
    ];
}

/**
 * @param array<string, mixed> $baseContext
 *
 * @return array{
 *   available: bool,
 *   partial: bool,
 *   mode: string,
 *   message: string,
 *   errors: list<string>,
 *   summary: array<string, int>,
 *   distributions: array<string, list<array{label: string, count: int, raw: string}>>,
 *   attention: array<string, list<array<string, mixed>>>,
 *   rows: list<array<string, mixed>>,
 *   trace_sources: list<string>
 * }
 */
function komodo_build_events_context(?PDO $pdo, array $baseContext): array
{
    unset($baseContext);

    $empty = [
        'available' => false,
        'partial' => false,
        'mode' => 'offline',
        'message' => 'Live events list requires a database connection.',
        'errors' => [],
        'summary' => [
            'total_events' => 0,
            'events_with_disclosure' => 0,
            'events_with_first_trading_day' => 0,
            'events_with_sources' => 0,
            'events_missing_sources' => 0,
            'events_needing_impact_review' => 0,
            'events_with_overlap_or_cluster_flags' => 0,
            'research_ready_metadata' => 0,
            'cyber_event_securities_rows' => 0,
        ],
        'distributions' => [
            'event_type' => [],
            'severity' => [],
            'confidence' => [],
        ],
        'attention' => [
            'missing_source_provenance' => [],
            'needs_impact_quantification' => [],
            'overlap_or_cluster_review' => [],
            'research_ready_metadata' => [],
        ],
        'rows' => [],
        'trace_sources' => komodo_event_trace_sources(),
    ];

    if ($pdo === null) {
        return $empty;
    }

    $errors = [];
    $partial = false;

    $baseFetch = komodo_fetch_event_rows($pdo);
    if (!$baseFetch['ok']) {
        $errors[] = 'Cyber events could not be loaded.';
        $partial = true;
        $rows = [];
    } else {
        $rows = $baseFetch['rows'];
    }

    $ids = [];
    foreach ($rows as $r) {
        $id = (int) ($r['cyber_event_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    $flagMaps = [
        'study_readiness' => ['ok' => false, 'by_id' => [], 'label' => 'Event-study readiness'],
        'research_flags' => ['ok' => false, 'by_id' => [], 'label' => 'Research readiness flags'],
        'contamination' => ['ok' => false, 'by_id' => [], 'label' => 'Contamination flags'],
        'impact_quality' => ['ok' => false, 'by_id' => [], 'label' => 'Impact quality flags'],
    ];

    if ($ids !== []) {
        $fetched = komodo_fetch_event_flag_maps($pdo, $ids);
        $flagMaps = $fetched['maps'];
        foreach ($fetched['errors'] as $e) {
            $errors[] = $e;
            $partial = true;
        }
    }

    $merged = $rows === [] ? [] : komodo_merge_event_flags_into_rows($rows, $flagMaps);
    foreach ($merged as &$mr) {
        $mr['primary_readiness_key'] = komodo_event_primary_readiness_key($mr);
        $mr['primary_readiness_label'] = komodo_event_primary_readiness($mr);
        $mr['review_badge_keys'] = komodo_event_review_badge_keys($mr);
    }
    unset($mr);

    $summary = komodo_summarize_events($merged);
    $summary['cyber_event_securities_rows'] = komodo_count_cyber_event_securities_rows($pdo, $partial, $errors);

    $distributions = [
        'event_type' => komodo_event_distribution($merged, 'event_type'),
        'severity' => komodo_event_distribution($merged, 'severity_level'),
        'confidence' => komodo_event_distribution($merged, 'confidence_level'),
    ];

    $attention = komodo_event_attention_items($merged);

    $mode = $partial ? 'partial' : 'live';
    $message = $partial
        ? 'Some readiness views could not be loaded; event list and partial flags are shown.'
        : 'Live cyber events from MariaDB (read-only).';

    return [
        'available' => true,
        'partial' => $partial,
        'mode' => $mode,
        'message' => $message,
        'errors' => $errors,
        'summary' => $summary,
        'distributions' => $distributions,
        'attention' => $attention,
        'rows' => $merged,
        'trace_sources' => komodo_event_trace_sources(),
    ];
}

/**
 * @param list<string> $errors
 */
function komodo_count_cyber_event_securities_rows(PDO $pdo, bool &$partial, array &$errors): int
{
    $sql = 'SELECT COUNT(*) AS c FROM cyber_event_securities';
    try {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            $errors[] = 'Could not count cyber_event_securities rows.';
            $partial = true;

            return 0;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    } catch (Throwable) {
        $errors[] = 'Could not count cyber_event_securities rows.';
        $partial = true;

        return 0;
    }
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, error: ?string}
 */
function komodo_fetch_event_rows(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    ce.cyber_event_id,
    ce.company_id,
    c.display_name AS company_name,
    ce.event_name,
    ce.event_type,
    ce.severity_level,
    ce.confidence_level,
    sa.display_security_id,
    sa.display_ticker_symbol,
    sa.display_security_name,
    COALESCE(sa.security_link_count, 0) AS security_link_count,
    d_disc.event_date AS disclosure_date,
    d_ftd.event_date AS first_trading_day,
    COALESCE(src.source_count, 0) AS source_count
FROM cyber_events ce
JOIN companies c
    ON ce.company_id = c.company_id
LEFT JOIN (
    SELECT
        ces.cyber_event_id,
        MIN(ces.security_id) AS display_security_id,
        MIN(s.ticker_symbol) AS display_ticker_symbol,
        MIN(s.security_name) AS display_security_name,
        COUNT(DISTINCT ces.security_id) AS security_link_count
    FROM cyber_event_securities ces
    LEFT JOIN securities s
        ON s.security_id = ces.security_id
    GROUP BY ces.cyber_event_id
) sa
    ON sa.cyber_event_id = ce.cyber_event_id
LEFT JOIN (
    SELECT
        cyber_event_id,
        MAX(event_date) AS event_date
    FROM cyber_event_dates
    WHERE date_type = 'disclosure'
    GROUP BY cyber_event_id
) d_disc
    ON d_disc.cyber_event_id = ce.cyber_event_id
LEFT JOIN (
    SELECT
        cyber_event_id,
        MAX(event_date) AS event_date
    FROM cyber_event_dates
    WHERE date_type = 'first_trading_day'
    GROUP BY cyber_event_id
) d_ftd
    ON d_ftd.cyber_event_id = ce.cyber_event_id
LEFT JOIN (
    SELECT
        cyber_event_id,
        COUNT(*) AS source_count
    FROM cyber_event_sources
    GROUP BY cyber_event_id
) src
    ON src.cyber_event_id = ce.cyber_event_id
ORDER BY d_disc.event_date DESC, ce.cyber_event_id DESC
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
 * @param list<int> $eventIds
 *
 * @return array{
 *   maps: array<string, array{ok: bool, by_id: array<int, array<string, mixed>>, label: string}>,
 *   errors: list<string>
 * }
 */
function komodo_fetch_event_flag_maps(PDO $pdo, array $eventIds): array
{
    $eventIds = array_values(array_unique(array_filter($eventIds, static fn (int $id): bool => $id > 0)));
    $specs = [
        'study_readiness' => ['view' => 'vw_event_study_event_readiness', 'label' => 'Event-study readiness'],
        'research_flags' => ['view' => 'vw_event_research_readiness_flags', 'label' => 'Research readiness flags'],
        'contamination' => ['view' => 'vw_event_contamination_flags', 'label' => 'Contamination flags'],
        'impact_quality' => ['view' => 'vw_event_impact_quality_flags', 'label' => 'Impact quality flags'],
    ];

    $maps = [];
    $errors = [];

    if ($eventIds === []) {
        foreach ($specs as $key => $meta) {
            $maps[$key] = ['ok' => true, 'by_id' => [], 'label' => $meta['label']];
        }

        return ['maps' => $maps, 'errors' => []];
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

    foreach ($specs as $key => $meta) {
        $sql = match ($meta['view']) {
            'vw_event_study_event_readiness' => 'SELECT * FROM vw_event_study_event_readiness WHERE cyber_event_id IN (' . $placeholders . ')',
            'vw_event_research_readiness_flags' => 'SELECT * FROM vw_event_research_readiness_flags WHERE cyber_event_id IN (' . $placeholders . ')',
            'vw_event_contamination_flags' => 'SELECT * FROM vw_event_contamination_flags WHERE cyber_event_id IN (' . $placeholders . ')',
            'vw_event_impact_quality_flags' => 'SELECT * FROM vw_event_impact_quality_flags WHERE cyber_event_id IN (' . $placeholders . ')',
            default => null,
        };
        if ($sql === null) {
            $maps[$key] = ['ok' => false, 'by_id' => [], 'label' => $meta['label']];
            $errors[] = 'Unknown readiness view key.';

            continue;
        }
        try {
            $stmt = $pdo->prepare($sql);
            foreach ($eventIds as $i => $id) {
                $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $byId = [];
            foreach ($rows ?: [] as $r) {
                $eid = (int) ($r['cyber_event_id'] ?? 0);
                if ($eid > 0 && !isset($byId[$eid])) {
                    $byId[$eid] = $r;
                }
            }
            $maps[$key] = ['ok' => true, 'by_id' => $byId, 'label' => $meta['label']];
        } catch (Throwable) {
            $maps[$key] = ['ok' => false, 'by_id' => [], 'label' => $meta['label']];
            $errors[] = 'Could not load ' . $meta['view'] . ' (readiness flags may be incomplete).';
        }
    }

    return ['maps' => $maps, 'errors' => $errors];
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<string, array{ok: bool, by_id: array<int, array<string, mixed>>, label: string}> $flagMaps
 *
 * @return list<array<string, mixed>>
 */
function komodo_merge_event_flags_into_rows(array $rows, array $flagMaps): array
{
    foreach ($rows as &$r) {
        $id = (int) ($r['cyber_event_id'] ?? 0);
        $r['flags_study_readiness'] = ($flagMaps['study_readiness']['ok'] ?? false) ? ($flagMaps['study_readiness']['by_id'][$id] ?? null) : null;
        $r['flags_research'] = ($flagMaps['research_flags']['ok'] ?? false) ? ($flagMaps['research_flags']['by_id'][$id] ?? null) : null;
        $r['flags_contamination'] = ($flagMaps['contamination']['ok'] ?? false) ? ($flagMaps['contamination']['by_id'][$id] ?? null) : null;
        $r['flags_impact_quality'] = ($flagMaps['impact_quality']['ok'] ?? false) ? ($flagMaps['impact_quality']['by_id'][$id] ?? null) : null;
    }
    unset($r);

    return $rows;
}

/**
 * Merge flag sub-rows into one map for lookups (excludes cyber_event_id).
 *
 * @param array<string, mixed> $row
 *
 * @return array<string, mixed>
 */
function komodo_event_flatten_flags(array $row): array
{
    $flat = [];
    foreach (['flags_research', 'flags_contamination', 'flags_impact_quality', 'flags_study_readiness'] as $slot) {
        $sub = $row[$slot] ?? null;
        if (!is_array($sub)) {
            continue;
        }
        foreach ($sub as $k => $v) {
            if ($k === 'cyber_event_id') {
                continue;
            }
            $flat[(string) $k] = $v;
        }
    }

    return $flat;
}

/**
 * @param array<string, mixed> $row
 */
function komodo_event_has_impact_concern(array $row): bool
{
    $flat = komodo_event_flatten_flags($row);

    return komodo_event_row_truthy($flat, [
        'high_severity_low_quantified_impact',
        'has_high_severity_low_quantified_impact',
        'needs_impact_quantification',
        'flag_needs_impact_quantification',
    ]);
}

/**
 * @param array<string, mixed> $row
 */
function komodo_event_has_overlap_or_cluster_concern(array $row): bool
{
    $flat = komodo_event_flatten_flags($row);

    return komodo_event_row_truthy($flat, [
        'has_same_ticker_window_overlap',
        'same_ticker_window_overlap',
        'flag_same_ticker_window_overlap',
        'has_tight_3_day_cluster',
        'tight_cluster',
        'tight_3_day_cluster',
        'flag_tight_cluster',
        'has_nearby_cyber_cluster',
        'nearby_cluster',
        'flag_nearby_cluster',
        'long_window_overlap_only',
        'has_long_window_overlap_only',
        'flag_long_window_overlap_only',
    ]);
}

/**
 * @param array<string, mixed> $row
 */
function komodo_event_row_truthy(array $row, array $keyCandidates): bool
{
    foreach ($keyCandidates as $k) {
        if (!array_key_exists($k, $row)) {
            continue;
        }
        $v = $row[$k];
        if ($v === true || $v === 1) {
            return true;
        }
        if (is_string($v)) {
            $t = strtolower(trim($v));
            if (in_array($t, ['1', 'true', 'yes', 'y'], true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $row
 *
 * @return list<string> readiness_flag / flag keys for badges
 */
function komodo_event_review_badge_keys(array $row): array
{
    $flat = komodo_event_flatten_flags($row);
    $badges = [];

    if (komodo_event_row_truthy($flat, ['high_severity_low_quantified_impact', 'has_high_severity_low_quantified_impact'])) {
        $badges[] = 'high_severity_low_quantified_impact';
    } elseif (komodo_event_row_truthy($flat, ['needs_impact_quantification', 'flag_needs_impact_quantification'])) {
        $badges[] = 'needs_impact_quantification';
    }

    if (komodo_event_row_truthy($flat, ['has_same_ticker_window_overlap', 'same_ticker_window_overlap', 'flag_same_ticker_window_overlap'])) {
        $badges[] = 'short_mid_window_overlap';
    }

    if (komodo_event_row_truthy($flat, ['has_tight_3_day_cluster', 'tight_cluster', 'tight_3_day_cluster', 'flag_tight_cluster'])) {
        $badges[] = 'tight_cluster';
    }

    if (komodo_event_row_truthy($flat, ['has_nearby_cyber_cluster', 'nearby_cluster', 'flag_nearby_cluster'])) {
        $badges[] = 'nearby_cluster';
    }

    if (komodo_event_row_truthy($flat, ['long_window_overlap_only', 'has_long_window_overlap_only', 'flag_long_window_overlap_only'])) {
        $badges[] = 'long_window_overlap_only';
    }

    $sc = (int) ($row['source_count'] ?? 0);
    if ($sc > 0 && komodo_event_row_truthy($flat, ['research_ready_metadata', 'has_research_ready_metadata', 'flag_research_ready_metadata'])) {
        $badges[] = 'research_ready_metadata';
    }

    return array_values(array_unique($badges));
}

/**
 * Internal priority key (readiness_flag domain).
 *
 * @param array<string, mixed> $row
 */
function komodo_event_primary_readiness_key(array $row): string
{
    $sourceCount = (int) ($row['source_count'] ?? 0);
    if ($sourceCount === 0) {
        return 'needs_source_provenance';
    }

    $flat = komodo_event_flatten_flags($row);

    if (komodo_event_row_truthy($flat, [
        'high_severity_low_quantified_impact',
        'has_high_severity_low_quantified_impact',
    ])) {
        return 'high_severity_low_quantified_impact';
    }

    if (komodo_event_row_truthy($flat, [
        'needs_impact_quantification',
        'flag_needs_impact_quantification',
    ])) {
        return 'needs_impact_quantification';
    }

    if (komodo_event_row_truthy($flat, [
        'has_same_ticker_window_overlap',
        'same_ticker_window_overlap',
        'flag_same_ticker_window_overlap',
    ])) {
        return 'short_mid_window_overlap';
    }

    if (komodo_event_row_truthy($flat, ['has_tight_3_day_cluster', 'tight_cluster', 'tight_3_day_cluster', 'flag_tight_cluster'])) {
        return 'tight_cluster';
    }

    if (komodo_event_row_truthy($flat, ['has_nearby_cyber_cluster', 'nearby_cluster', 'flag_nearby_cluster'])) {
        return 'nearby_cluster';
    }

    if (komodo_event_row_truthy($flat, ['long_window_overlap_only', 'has_long_window_overlap_only', 'flag_long_window_overlap_only'])) {
        return 'long_window_overlap_only';
    }

    if ($sourceCount > 0 && komodo_event_row_truthy($flat, ['research_ready_metadata', 'has_research_ready_metadata', 'flag_research_ready_metadata'])) {
        return 'research_ready_metadata';
    }

    return 'needs_review';
}

/**
 * @param array<string, mixed> $row
 */
function komodo_event_primary_readiness(array $row): string
{
    $key = $row['primary_readiness_key'] ?? komodo_event_primary_readiness_key($row);

    return komodo_label($key, 'readiness_flag');
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array<string, int>
 */
function komodo_summarize_events(array $rows): array
{
    $total = count($rows);
    $withDisc = 0;
    $withFtd = 0;
    $withSrc = 0;
    $missingSrc = 0;
    $impact = 0;
    $overlapCluster = 0;
    $researchReady = 0;

    foreach ($rows as $r) {
        $disc = komodo_normalize_date_string($r['disclosure_date'] ?? null);
        $ftd = komodo_normalize_date_string($r['first_trading_day'] ?? null);
        if ($disc !== null) {
            $withDisc++;
        }
        if ($ftd !== null) {
            $withFtd++;
        }
        $sc = (int) ($r['source_count'] ?? 0);
        if ($sc > 0) {
            $withSrc++;
        } else {
            $missingSrc++;
        }

        $key = $r['primary_readiness_key'] ?? komodo_event_primary_readiness_key($r);
        if (komodo_event_has_impact_concern($r)) {
            $impact++;
        }
        if (komodo_event_has_overlap_or_cluster_concern($r)) {
            $overlapCluster++;
        }
        if ($key === 'research_ready_metadata') {
            $researchReady++;
        }
    }

    return [
        'total_events' => $total,
        'events_with_disclosure' => $withDisc,
        'events_with_first_trading_day' => $withFtd,
        'events_with_sources' => $withSrc,
        'events_missing_sources' => $missingSrc,
        'events_needing_impact_review' => $impact,
        'events_with_overlap_or_cluster_flags' => $overlapCluster,
        'research_ready_metadata' => $researchReady,
        'cyber_event_securities_rows' => 0,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return list<array{label: string, count: int, raw: string}>
 */
function komodo_event_distribution(array $rows, string $field): array
{
    $counts = [];
    foreach ($rows as $r) {
        $raw = isset($r[$field]) ? trim((string) $r[$field]) : '';
        if ($raw === '') {
            $raw = '—';
        }
        if (!isset($counts[$raw])) {
            $counts[$raw] = 0;
        }
        $counts[$raw]++;
    }

    $out = [];
    foreach ($counts as $raw => $n) {
        $label = $raw === '—' ? '—' : komodo_label_safe($raw, 'generic');
        $out[] = ['label' => $label, 'count' => $n, 'raw' => $raw];
    }

    usort($out, static function (array $a, array $b): int {
        if ($a['count'] !== $b['count']) {
            return $b['count'] <=> $a['count'];
        }

        return strcmp($a['raw'], $b['raw']);
    });

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 *
 * @return array{
 *   missing_source_provenance: list<array<string, mixed>>,
 *   needs_impact_quantification: list<array<string, mixed>>,
 *   overlap_or_cluster_review: list<array<string, mixed>>,
 *   research_ready_metadata: list<array<string, mixed>>
 * }
 */
function komodo_event_attention_items(array $rows): array
{
    $missing = [];
    $impact = [];
    $overlap = [];
    $ready = [];

    foreach ($rows as $r) {
        $item = [
            'cyber_event_id' => (int) ($r['cyber_event_id'] ?? 0),
            'event_name' => (string) ($r['event_name'] ?? ''),
            'company_name' => (string) ($r['company_name'] ?? ''),
        ];
        $sc = (int) ($r['source_count'] ?? 0);
        $pk = $r['primary_readiness_key'] ?? komodo_event_primary_readiness_key($r);

        if ($sc === 0) {
            $missing[] = $item;
        }
        if (komodo_event_has_impact_concern($r)) {
            $impact[] = $item;
        }
        if (komodo_event_has_overlap_or_cluster_concern($r)) {
            $overlap[] = $item;
        }
        if ($pk === 'research_ready_metadata') {
            $ready[] = $item;
        }
    }

    return [
        'missing_source_provenance' => $missing,
        'needs_impact_quantification' => $impact,
        'overlap_or_cluster_review' => $overlap,
        'research_ready_metadata' => $ready,
    ];
}

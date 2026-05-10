<?php

declare(strict_types=1);

/**
 * Shared display/label helpers for Komodo.
 * Primary UI labels should be analyst-friendly; technical identifiers remain available as secondary metadata.
 */

function komodo_label(string $key, string $domain = 'generic'): string
{
    $key = trim($key);
    if ($key === '') {
        return '—';
    }

    $label = komodo_label_lookup($key, $domain);
    if ($label !== null) {
        return $label;
    }

    return komodo_format_identifier($key);
}

function komodo_label_safe(?string $key, string $domain = 'generic'): string
{
    if ($key === null) {
        return '—';
    }

    $key = trim($key);
    if ($key === '') {
        return '—';
    }

    return komodo_label($key, $domain);
}

function komodo_describe(string $key, string $domain = 'generic'): ?string
{
    $key = trim($key);
    if ($key === '') {
        return null;
    }

    $desc = komodo_desc_lookup($key, $domain);
    if ($desc !== null) {
        return $desc;
    }

    return null;
}

/**
 * Fallback formatting for unknown identifiers.
 * - underscores/hyphens -> spaces
 * - collapse whitespace
 * - title-case first letter only (sentence-ish) while keeping acronyms mostly intact
 */
function komodo_format_identifier(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '—';
    }

    $s = preg_replace('/[_-]+/', ' ', $s);
    $s = $s === null ? $raw : $s;
    $s = preg_replace('/\s+/', ' ', $s);
    $s = $s === null ? $raw : $s;
    $s = trim($s);

    // Lower-case common connectors while keeping the first token capitalized.
    $tokens = explode(' ', $s);
    $out = [];
    foreach ($tokens as $i => $t) {
        $lt = strtolower($t);
        $word = $lt;
        if ($i === 0) {
            $word = ucfirst($lt);
        } elseif (in_array($lt, ['and', 'or', 'of', 'to', 'vs'], true)) {
            $word = $lt;
        } else {
            $word = ucfirst($lt);
        }
        $out[] = $word;
    }

    return implode(' ', $out);
}

function komodo_label_lookup(string $key, string $domain): ?string
{
    return match ($domain) {
        'role' => match ($key) {
            'event_linked_security' => 'Event-linked security',
            'comparison_or_unlinked_security' => 'Comparison / unlinked security',
            default => null,
        },
        'company_role' => match ($key) {
            'event_company' => 'Event company',
            'control_company' => 'Control company',
            'cybersecurity_company' => 'Cybersecurity company',
            'wildcard_volatility_control' => 'Volatility comparison company',
            default => null,
        },
        'coverage_status' => match ($key) {
            'not_started' => 'Not started',
            'has_prices' => 'Has prices',
            'has_prices_window_unknown' => 'Has prices, window unknown',
            'partial_unknown_dates' => 'Partial, unknown first/last date',
            'missing_start_window' => 'Missing start of window',
            'missing_end_window' => 'Missing end of window',
            'covers_suggested_window' => 'Covers suggested window',
            'partial' => 'Partial coverage',
            'unavailable_or_error' => 'Unavailable / error',
            default => null,
        },
        'flag', 'readiness_flag' => match ($key) {
            'needs_source_provenance' => 'Needs source provenance',
            'needs_impact_quantification' => 'Needs impact quantification',
            'needs_review' => 'Needs review',
            'short_mid_window_overlap' => 'Short/mid-window overlap',
            'long_window_overlap_only' => 'Long-window overlap only',
            'nearby_cluster' => 'Nearby cyber-event cluster',
            'tight_cluster' => 'Tight 3-day cluster',
            'research_ready_metadata' => 'Research-ready metadata',
            'high_severity_low_quantified_impact' => 'High severity, low quantified impact',
            default => null,
        },
        'db_object' => match ($key) {
            'companies' => 'Companies',
            'securities' => 'Securities',
            'cyber_events' => 'Cyber events',
            'cyber_event_dates' => 'Cyber event dates',
            'cyber_event_features' => 'Cyber event features',
            'cyber_event_impacts' => 'Cyber event impacts',
            'cyber_event_securities' => 'Cyber event securities',
            'security_daily_prices' => 'Security daily prices',
            'index_daily_prices' => 'Index daily prices',
            'cyber_event_sources' => 'Event source provenance',
            'event_study_runs' => 'Event-study runs',
            'event_study_results' => 'Event-study results',
            'vw_market_data_import_plan' => 'Market data plan (price windows)',
            'vw_security_price_import_targets' => 'Security price import targets',
            'vw_event_research_readiness_flags' => 'Research readiness flags',
            'vw_event_impact_quality_flags' => 'Impact quality flags',
            'vw_event_contamination_flags' => 'Event contamination flags',
            'vw_event_same_ticker_window_overlaps' => 'Same-ticker window overlaps',
            'vw_event_nearby_cyber_clusters' => 'Nearby cyber-event clusters',
            'vw_event_window_boundaries' => 'Event window boundaries',
            'vw_event_study_event_readiness' => 'Event-study event readiness',
            'vw_us_trading_days' => 'US trading days',
            'market_calendar' => 'Market calendar',
            default => null,
        },
        default => null,
    };
}

function komodo_desc_lookup(string $key, string $domain): ?string
{
    return match ($domain) {
        'role' => match ($key) {
            'event_linked_security' => 'Security is directly linked to at least one cyber event in the dataset.',
            'comparison_or_unlinked_security' => 'Security is available for comparison/control analysis but is not currently linked to a cyber event.',
            default => null,
        },
        'company_role' => match ($key) {
            'event_company' => 'Company is treated as an event-linked entity for this dataset context.',
            'control_company' => 'Company is treated as a control/comparison entity for this dataset context.',
            'cybersecurity_company' => 'Company is treated as a cybersecurity/security-vendor entity for this dataset context.',
            'wildcard_volatility_control' => 'Company may be used as a volatility comparison baseline (wildcard control).',
            default => null,
        },
        // Window gap text: keep numeric slack in sync with KOMODO_TRIAGE_WINDOW_SLACK_DAYS (market_data_queries.php).
        'coverage_status' => match ($key) {
            'not_started' => 'No price rows are loaded for this item yet.',
            'covers_suggested_window' => 'Loaded first/last bars align with the suggested import window, allowing ±7 calendar days slack at each end (non-trading-day / weekly bar tolerance).',
            'missing_start_window' => 'First loaded bar is more than 7 calendar days after the suggested import start date.',
            'missing_end_window' => 'Last loaded bar is more than 7 calendar days before the suggested import end date.',
            'has_prices_window_unknown' => 'Prices exist, but the suggested import dates are missing or could not be parsed.',
            'partial_unknown_dates' => 'Prices exist, but first/last trade dates could not be derived.',
            'partial' => 'Prices exist but do not fully span the suggested import window.',
            'unavailable_or_error' => 'Coverage could not be computed due to an unavailable metric or query error.',
            default => null,
        },
        'flag', 'readiness_flag' => match ($key) {
            'needs_source_provenance' => 'The event needs source rows to support research traceability.',
            'needs_impact_quantification' => 'The event lacks quantified impact and should be reviewed before analysis.',
            'needs_review' => 'The event should be reviewed before event-study analysis.',
            'high_severity_low_quantified_impact' => 'The event is marked high/critical but lacks strong numeric or operational impact evidence.',
            'long_window_overlap_only' => 'Event windows overlap in longer horizons and may contaminate study samples.',
            'nearby_cluster' => 'Event occurs near other cyber events and may represent a cluster rather than an isolated shock.',
            'tight_cluster' => 'Events are tightly clustered (3-day window) and should be reviewed for independence.',
            default => null,
        },
        'db_object' => match ($key) {
            'vw_event_research_readiness_flags' => 'Combined event-level readiness and review flags used to separate clean candidates from events needing review.',
            'vw_event_impact_quality_flags' => 'Impact-quality diagnostics for events; highlights missing/weak quantification.',
            'vw_event_contamination_flags' => 'Contamination diagnostics (overlaps, clustering, or other confounds).',
            'vw_event_same_ticker_window_overlaps' => 'Detects overlapping event windows for the same security/ticker.',
            'vw_event_nearby_cyber_clusters' => 'Detects groups of cyber events occurring close in time.',
            'vw_market_data_import_plan' => 'Suggested per-security import windows and notes used to plan price ingestion.',
            'vw_security_price_import_targets' => 'Securities in scope for planned price loads, segmented by role (event-linked vs comparison).',
            'vw_event_study_event_readiness' => 'Event readiness view for downstream event-study execution.',
            default => null,
        },
        default => null,
    };
}

/**
 * Shorter coverage badge text for catalog / summary tables (not Price audit).
 * “Span OK” = first/last loaded bars align with the plan window within slack (density is separate).
 */
function komodo_coverage_catalog_label(string $key): string
{
    $k = trim($key);
    if ($k === 'covers_suggested_window') {
        return 'Span OK';
    }

    return komodo_label($k, 'coverage_status');
}


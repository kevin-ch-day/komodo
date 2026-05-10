<?php

declare(strict_types=1);

/**
 * Small shared UI helpers for page templates (escape-heavy table snippets).
 */

require_once __DIR__ . '/dashboard_context.php';

/**
 * Truncate a note for dense tables; returns escaped strings safe for HTML and title attributes.
 *
 * @return array{0: string, 1: string, 2: bool} Escaped display, escaped full text, whether truncated
 */
function komodo_note_preview(?string $note, int $maxLen = 96): array
{
    if ($note === null || trim($note) === '') {
        return ['', '', false];
    }
    $plain = preg_replace('/\s+/', ' ', trim(strip_tags($note)));
    $plain = is_string($plain) ? $plain : (string) $note;
    $shortened = strlen($plain) > $maxLen;
    $show = $shortened ? substr($plain, 0, $maxLen) . '…' : $plain;

    return [komodo_e($show), komodo_e($plain), $shortened];
}

/**
 * Format a date-ish value to "Jan 12 2022" (short month, zero-padded day).
 */
function komodo_format_display_date(mixed $dateish): string
{
    if ($dateish === null || $dateish === '') {
        return '';
    }
    $s = (string) $dateish;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $m[1]);
    if ($dt === false) {
        return '';
    }

    return $dt->format('M d Y');
}

/**
 * Suggested window / span label: "Jan 12 2022 to Mar 02 2023".
 */
function komodo_format_display_date_range(mixed $start, mixed $end): string
{
    $a = komodo_format_display_date($start);
    $b = komodo_format_display_date($end);
    if ($a === '' || $b === '') {
        return '';
    }

    return "{$a} to {$b}";
}

/**
 * CSS class name for coverage status badges.
 *
 * @param string $kind "security" (default) or "index" for benchmark index rows
 */
function komodo_coverage_badge_css(string $status, string $kind = 'security'): string
{
    if ($kind === 'index') {
        return match ($status) {
            'not_started' => 'coverage-badge--not-started',
            'has_prices' => 'coverage-badge--ok',
            default => 'coverage-badge--unknown',
        };
    }

    return match ($status) {
        'not_started' => 'coverage-badge--not-started',
        'covers_suggested_window' => 'coverage-badge--ok',
        'has_prices' => 'coverage-badge--ok',
        'missing_start_window' => 'coverage-badge--warning',
        'missing_end_window' => 'coverage-badge--warning',
        'has_prices_window_unknown' => 'coverage-badge--unknown',
        'partial_unknown_dates' => 'coverage-badge--unknown',
        'partial' => 'coverage-badge--partial',
        default => 'coverage-badge--unknown',
    };
}

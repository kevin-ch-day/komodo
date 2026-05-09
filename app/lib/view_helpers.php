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

<?php

declare(strict_types=1);

/**
 * Safe parsing of request query parameters (read-only research portal).
 */

/**
 * Positive integer from a query string value (e.g. id drilldowns). Rejects arrays, floats, non-digits, zero.
 */
function komodo_get_positive_int_from_query(array $query, string $name): ?int
{
    $raw = $query[$name] ?? null;
    if (!is_string($raw) || $raw === '' || !ctype_digit($raw)) {
        return null;
    }
    $v = (int) $raw;

    return $v > 0 ? $v : null;
}

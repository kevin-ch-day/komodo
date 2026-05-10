<?php

declare(strict_types=1);

/**
 * Attach page-specific keys to $ctx after komodo_build_dashboard_context().
 * Add new branches here instead of growing public/index.php.
 */

require_once __DIR__ . '/request_helpers.php';
require_once __DIR__ . '/market_data_queries.php';
require_once __DIR__ . '/company_queries.php';
require_once __DIR__ . '/event_queries.php';

/**
 * @param array<string, mixed> $ctx
 * @param array<string, mixed>|null $query Defaults to $_GET when null (web); pass an array in tests/CLI if needed.
 */
function komodo_hydrate_page_context(string $pageKey, array &$ctx, ?array $query = null): void
{
    if ($query === null) {
        $query = $_GET;
    }

    /** @var ?PDO */
    $pdo = $ctx['db_status']['pdo'] ?? null;

    match ($pageKey) {
        'market-data', 'price-import-queue', 'price-coverage', 'price-audit', 'data-gaps' => $ctx['market_data'] = komodo_build_market_data_context($pdo, $ctx),
        'companies' => $ctx['companies'] = komodo_build_companies_context($pdo, $ctx),
        'company' => $ctx['company'] = komodo_hydrate_company_page_context($pdo, $ctx, $query),
        'events' => $ctx['events'] = komodo_build_events_context($pdo, $ctx),
        default => null,
    };
}

/**
 * @param array<string, mixed> $ctx
 * @param array<string, mixed> $query
 *
 * @return array<string, mixed>
 */
function komodo_hydrate_company_page_context(?PDO $pdo, array $ctx, array $query): array
{
    $companyId = komodo_get_positive_int_from_query($query, 'company_id');
    if ($companyId === null) {
        return komodo_company_context_invalid_id();
    }

    return komodo_build_company_context($pdo, $ctx, $companyId);
}

<?php

declare(strict_types=1);

/**
 * Whitelisted page routes — single source of truth for `public/index.php` and `tools/komodo_smoke.php`.
 *
 * Template paths are relative to the repository root (forward slashes).
 *
 * @return array<string, array{template: string, title: string}>
 */
function komodo_page_routes(): array
{
    return [
        'dashboard' => ['template' => 'app/pages/dashboard.php', 'title' => 'Dashboard'],
        'companies' => ['template' => 'app/pages/companies.php', 'title' => 'Companies'],
        'company' => ['template' => 'app/pages/company.php', 'title' => 'Company'],
        'dataset' => ['template' => 'app/pages/dataset.php', 'title' => 'Dataset'],
        'events' => ['template' => 'app/pages/events.php', 'title' => 'Events'],
        'market-data' => ['template' => 'app/pages/market-data.php', 'title' => 'Market Data Summary'],
        'price-import-queue' => ['template' => 'app/pages/price-import-queue.php', 'title' => 'Price Worklist'],
        'price-coverage' => ['template' => 'app/pages/price-coverage.php', 'title' => 'Coverage Summary'],
        'price-audit' => ['template' => 'app/pages/price-audit.php', 'title' => 'Price Audit'],
        'research-quality' => ['template' => 'app/pages/research-quality.php', 'title' => 'Research quality'],
        'data-gaps' => ['template' => 'app/pages/data-gaps.php', 'title' => 'Data gaps'],
    ];
}

/**
 * 404 page metadata (not a normal `?page=` route).
 *
 * @return array{template: string, title: string}
 */
function komodo_not_found_page(): array
{
    return [
        'template' => 'app/pages/not-found.php',
        'title' => 'Page not found',
    ];
}

/**
 * Human-readable sidebar label per route (workflow-oriented wording).
 */
function komodo_sidebar_route_label(string $routeKey): string
{
    return match ($routeKey) {
        'dashboard' => 'Dashboard',
        'companies' => 'Companies',
        'events' => 'Events',
        'dataset' => 'Dataset',
        'market-data' => 'Market Data Summary',
        'price-import-queue' => 'Price Worklist',
        'price-coverage' => 'Coverage Summary',
        'price-audit' => 'Price Audit',
        'research-quality' => 'Research Quality',
        'data-gaps' => 'Data Gaps',
        default => ucwords(strtolower(komodo_page_routes()[$routeKey]['title'] ?? $routeKey)),
    };
}

/**
 * Sidebar sections (workflow groups). Keys must exist in komodo_page_routes().
 *
 * @return list<array{id: string, heading: string, keys: list<string>}>
 */
function komodo_sidebar_nav_groups(): array
{
    return [
        [
            'id' => 'nav-overview',
            'heading' => 'Overview',
            'keys' => ['dashboard'],
        ],
        [
            'id' => 'nav-dataset',
            'heading' => 'Dataset',
            'keys' => ['companies', 'events', 'dataset'],
        ],
        [
            'id' => 'nav-market-data',
            'heading' => 'Market Data',
            'keys' => ['market-data', 'price-import-queue', 'price-coverage', 'price-audit'],
        ],
        [
            'id' => 'nav-quality',
            'heading' => 'Quality',
            'keys' => ['research-quality', 'data-gaps'],
        ],
    ];
}

/**
 * Flat sidebar route order (smoke tests, link checkers). Derived from groups.
 *
 * @return list<string>
 */
function komodo_sidebar_nav_keys(): array
{
    $routes = komodo_page_routes();
    $out = [];
    foreach (komodo_sidebar_nav_groups() as $group) {
        foreach ($group['keys'] as $key) {
            if (isset($routes[$key])) {
                $out[] = $key;
            }
        }
    }

    return $out;
}

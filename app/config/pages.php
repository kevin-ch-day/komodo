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
        'market-data' => ['template' => 'app/pages/market-data.php', 'title' => 'Market data'],
        'research-quality' => ['template' => 'app/pages/research-quality.php', 'title' => 'Research quality'],
        'data-gaps' => ['template' => 'app/pages/data-gaps.php', 'title' => 'Data gaps'],
        'pipeline' => ['template' => 'app/pages/pipeline.php', 'title' => 'Pipeline'],
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
 * Sidebar navigation order. Keys must exist in komodo_page_routes().
 * Omit drilldown-only routes (e.g. company detail).
 *
 * @return list<string>
 */
function komodo_sidebar_nav_keys(): array
{
    return [
        'dashboard',
        'companies',
        'dataset',
        'events',
        'market-data',
        'research-quality',
        'data-gaps',
        'pipeline',
    ];
}

/**
 * Presentation label for sidebar links (doc titles are sentence case in browser chrome).
 */
function komodo_sidebar_link_label(string $routeTitle): string
{
    return ucwords(strtolower($routeTitle));
}

/**
 * @return list<array{key: string, label: string}>
 */
function komodo_sidebar_nav_items(): array
{
    $routes = komodo_page_routes();
    $out = [];
    foreach (komodo_sidebar_nav_keys() as $key) {
        if (!isset($routes[$key])) {
            continue;
        }
        $out[] = [
            'key' => $key,
            'label' => komodo_sidebar_link_label($routes[$key]['title']),
        ];
    }

    return $out;
}

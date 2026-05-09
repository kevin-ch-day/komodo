<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/lib/dashboard_context.php';

/**
 * Allowed page keys only — never include paths from $_GET.
 *
 * @var array<string, string>
 */
$pageMap = [
    'dashboard' => __DIR__ . '/../app/pages/dashboard.php',
    'dataset' => __DIR__ . '/../app/pages/dataset.php',
    'events' => __DIR__ . '/../app/pages/events.php',
    'market-data' => __DIR__ . '/../app/pages/market-data.php',
    'research-quality' => __DIR__ . '/../app/pages/research-quality.php',
    'data-gaps' => __DIR__ . '/../app/pages/data-gaps.php',
    'pipeline' => __DIR__ . '/../app/pages/pipeline.php',
];

/** @var array<string, string> */
$docTitles = [
    'dashboard' => 'Dashboard',
    'dataset' => 'Dataset',
    'events' => 'Events',
    'market-data' => 'Market data',
    'research-quality' => 'Research quality',
    'data-gaps' => 'Data gaps',
    'pipeline' => 'Pipeline',
    'not-found' => 'Page not found',
];

$pageParam = isset($_GET['page']) ? $_GET['page'] : null;

$notFound = false;
$pageKey = 'dashboard';

if ($pageParam !== null) {
    if (is_array($pageParam)) {
        $notFound = true;
    } elseif (!is_string($pageParam)) {
        $notFound = true;
    } elseif ($pageParam === '') {
        $pageKey = 'dashboard';
    } elseif (!isset($pageMap[$pageParam])) {
        $notFound = true;
    } else {
        $pageKey = $pageParam;
    }
}

$ctx = komodo_build_dashboard_context();

if ($notFound) {
    http_response_code(404);
    $current_page = 'not-found';
    $doc_title_short = $docTitles['not-found'];
    ob_start();
    require __DIR__ . '/../app/pages/not-found.php';
    $komodo_main_html = ob_get_clean();
} else {
    $current_page = $pageKey;
    $doc_title_short = $docTitles[$pageKey];
    ob_start();
    require $pageMap[$pageKey];
    $komodo_main_html = ob_get_clean();
}

require __DIR__ . '/../app/partials/layout.php';

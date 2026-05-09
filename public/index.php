<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/lib/label_helpers.php';
require_once __DIR__ . '/../app/config/pages.php';
require_once __DIR__ . '/../app/lib/dashboard_context.php';
require_once __DIR__ . '/../app/lib/view_helpers.php';
require_once __DIR__ . '/../app/lib/page_context.php';

$repoRoot = dirname(__DIR__);
/** @var array<string, string> */
$pageMap = [];
/** @var array<string, string> */
$docTitles = [];
foreach (komodo_page_routes() as $routeKey => $meta) {
    $pageMap[$routeKey] = $repoRoot . '/' . $meta['template'];
    $docTitles[$routeKey] = $meta['title'];
}
$nfMeta = komodo_not_found_page();
$docTitles['not-found'] = $nfMeta['title'];

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

if (!$notFound) {
    komodo_hydrate_page_context($pageKey, $ctx);
}

if ($notFound) {
    http_response_code(404);
    $current_page = 'not-found';
    $doc_title_short = $docTitles['not-found'];
    ob_start();
    require $repoRoot . '/' . $nfMeta['template'];
    $komodo_main_html = ob_get_clean();
} else {
    $current_page = $pageKey;
    $doc_title_short = $docTitles[$pageKey];
    ob_start();
    require $pageMap[$pageKey];
    $komodo_main_html = ob_get_clean();
}

require __DIR__ . '/../app/partials/layout.php';

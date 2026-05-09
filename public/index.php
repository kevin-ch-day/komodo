<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/lib/label_helpers.php';
require_once __DIR__ . '/../app/lib/dashboard_context.php';
require_once __DIR__ . '/../app/lib/market_data_queries.php';
require_once __DIR__ . '/../app/lib/company_queries.php';

/**
 * Allowed page keys only — never include paths from $_GET.
 *
 * @var array<string, string>
 */
$pageMap = [
    'dashboard' => __DIR__ . '/../app/pages/dashboard.php',
    'companies' => __DIR__ . '/../app/pages/companies.php',
    'company' => __DIR__ . '/../app/pages/company.php',
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
    'companies' => 'Companies',
    'company' => 'Company',
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

if (!$notFound && $pageKey === 'market-data') {
    /** @var ?PDO */
    $marketPdo = $ctx['db_status']['pdo'] ?? null;
    $ctx['market_data'] = komodo_build_market_data_context($marketPdo, $ctx);
}

if (!$notFound && $pageKey === 'companies') {
    /** @var ?PDO */
    $companiesPdo = $ctx['db_status']['pdo'] ?? null;
    $ctx['companies'] = komodo_build_companies_context($companiesPdo, $ctx);
}

if (!$notFound && $pageKey === 'company') {
    /** @var ?PDO */
    $companyPdo = $ctx['db_status']['pdo'] ?? null;
    $rawCompanyId = $_GET['company_id'] ?? null;
    $companyId = null;
    if (is_string($rawCompanyId) && ctype_digit($rawCompanyId)) {
        $v = (int) $rawCompanyId;
        if ($v > 0) {
            $companyId = $v;
        }
    }

    if ($companyId === null) {
        $ctx['company'] = [
            'available' => false,
            'partial' => false,
            'mode' => 'invalid',
            'message' => 'Invalid company id.',
            'errors' => [],
            'not_found' => false,
            'company_id' => 0,
            'profile' => null,
            'securities' => [],
            'events' => [],
            'summary' => null,
            'trace_sources' => komodo_company_trace_sources(),
        ];
    } else {
        $ctx['company'] = komodo_build_company_context($companyPdo, $ctx, $companyId);
    }
}

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

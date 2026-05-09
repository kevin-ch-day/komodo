<?php

declare(strict_types=1);

/**
 * CLI structural smoke test for Komodo (v0.0.2).
 * Route keys come from app/config/pages.php (same source as public/index.php).
 */

$root = dirname(__DIR__);
require_once $root . '/app/config/pages.php';

$fail = false;

$f = static function (string $msg) use (&$fail): void {
    echo 'FAIL: ' . $msg . PHP_EOL;
    $fail = true;
};

$p = static function (string $msg): void {
    echo 'PASS: ' . $msg . PHP_EOL;
};

$relPath = static function (string $rel) use ($root): string {
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
};

$coreFiles = [
    'public/index.php',
    'index.php',
    'app/config/pages.php',
    'app/config/database.php',
    'app/config/local.example.php',
    'app/lib/dashboard_queries.php',
    'app/lib/company_queries.php',
    'app/lib/market_data_queries.php',
    'app/lib/label_helpers.php',
    'app/lib/view_helpers.php',
    'app/lib/request_helpers.php',
    'app/lib/page_context.php',
    'app/lib/event_queries.php',
    'app/lib/dashboard_context.php',
    'app/partials/layout.php',
    'app/partials/sidebar.php',
    'app/partials/footer.php',
    'app/pages/dashboard.php',
    'app/pages/companies.php',
    'app/pages/company.php',
    'app/pages/dataset.php',
    'app/pages/events.php',
    'app/pages/market-data.php',
    'app/pages/research-quality.php',
    'app/pages/data-gaps.php',
    'app/pages/pipeline.php',
    'app/pages/not-found.php',
    'assets/css/style.css',
];

foreach ($coreFiles as $rel) {
    $path = $relPath($rel);
    if (is_file($path)) {
        $p('expected file exists: ' . $rel);
    } else {
        $f('missing file: ' . $rel);
    }
}

$pageMap = [];
foreach (komodo_page_routes() as $key => $meta) {
    $pageMap[$key] = $relPath($meta['template']);
}
$nf = komodo_not_found_page();
$nfAbs = $relPath($nf['template']);
if (is_file($nfAbs)) {
    $p('not-found template exists: ' . $nf['template']);
} else {
    $f('missing not-found template: ' . $nf['template']);
}

foreach ($pageMap as $key => $abs) {
    if (isset($pageMap[$key]) && is_file($abs)) {
        $p('route key "' . $key . '" maps to existing file');
    } else {
        $f('route key "' . $key . '" broken');
    }
}

$unknownKeys = ['__komodo_unknown_page__', 'totally-invalid-route'];
foreach ($unknownKeys as $uk) {
    if (!isset($pageMap[$uk])) {
        $p('unknown page key "' . $uk . '" is not routeable');
    } else {
        $f('unknown page key "' . $uk . '" incorrectly appears in map');
    }
}

$routes = komodo_page_routes();
foreach (komodo_sidebar_nav_keys() as $navKey) {
    if (isset($routes[$navKey])) {
        $p('sidebar nav key "' . $navKey . '" exists in komodo_page_routes()');
    } else {
        $f('sidebar nav key "' . $navKey . '" missing from komodo_page_routes()');
    }
}

$gitignore = $relPath('.gitignore');
if (is_readable($gitignore)) {
    $gi = file_get_contents($gitignore);
    if ($gi !== false && preg_match('/(^|\/)local\\.php\b|app\/config\/local\\.php\b/m', $gi)) {
        $p('.gitignore mentions app local.php');
    } else {
        $f('.gitignore should ignore app/config/local.php (or local.php)');
    }
} else {
    $f('.gitignore not readable');
}

if (is_dir($relPath('.git'))) {
    $cwd = @getcwd();
    $tracked = false;
    if (@chdir($root)) {
        $output = [];
        exec('git ls-files -- app/config/local.php', $output);
        $tracked = $output !== [];
        if ($cwd !== false) {
            @chdir($cwd);
        }
        if ($tracked) {
            $f('app/config/local.php is tracked by git — should stay gitignored only');
        } else {
            $p('git: app/config/local.php is not tracked');
        }
    } else {
        $p('skip: cannot chdir to repo root for git check');
    }
} else {
    $p('skip: .git absent — git tracking check not run');
}

$localPhp = $relPath('app/config/local.php');
if (is_readable($localPhp)) {
    $p('optional app/config/local.php is present');
} else {
    $p('optional app/config/local.php absent (offline OK)');
}

exit($fail ? 1 : 0);

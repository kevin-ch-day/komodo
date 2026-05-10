<?php

declare(strict_types=1);

/**
 * Local database settings (example only).
 *
 * Copy this file to local.php in the same directory and replace placeholders.
 * Do not commit local.php — it is listed in .gitignore.
 *
 * Debugging: set environment variable KOMODO_DEBUG=1 (or enable display_errors in php.ini)
 * to show message and file:line on application error pages (500). Leave off in production.
 *
 * If PHP or the app will not start at all, open public/fallback.html in the browser — it is
 * plain HTML with no PHP and a link back to index.php.
 */
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'gecko_research_database_prod',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
];

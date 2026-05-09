<?php

declare(strict_types=1);

/**
 * CLI database status + whitelist COUNT(*) layer probe.
 */

$base = dirname(__DIR__);
require_once $base . '/app/config/database.php';
require_once $base . '/app/lib/dashboard_queries.php';

$exitOk = static function (): void {
    exit(0);
};

$exitDegraded = static function (): void {
    exit(2);
};

$status = komodo_get_database_status();
$state = $status['status'];
$msg = $status['message'];
$pdo = $status['pdo'];

echo 'DB status: ' . $state . PHP_EOL;
echo 'Note: ' . $msg . PHP_EOL;

if ($pdo === null) {
    echo 'Disconnected — offline/unreachable/misconfigured is acceptable for CLI.' . PHP_EOL;
    echo 'Skipping COUNT(*) probes.' . PHP_EOL;
    $exitOk();
}

if ($state !== 'connected') {
    fwrite(STDERR, 'Internal inconsistency: PDO set but status is not connected.' . PHP_EOL);
    exit(1);
}

$tableCounts = komodo_get_table_counts_safe($pdo);
$viewCounts = komodo_get_view_counts_safe($pdo);

echo PHP_EOL . str_repeat('-', 72) . PHP_EOL;
printf("%-42s %12s %10s\n", 'Identifier', 'Count', 'Status');
echo str_repeat('-', 72) . PHP_EOL;

$anyUnavailable = false;

$rowOut = static function (string $id, array $meta): bool {
    $c = $meta['count'];
    $st = $meta['status'] ?? '?';
    $countStr = $st === 'ok' && $c !== null ? (string) $c : '—';
    printf("%-42s %12s %10s\n", $id, $countStr, $st);
    return ($st !== 'ok');
};

foreach ($tableCounts as $name => $meta) {
    if ($rowOut($name, $meta)) {
        $anyUnavailable = true;
    }
}
foreach ($viewCounts as $name => $meta) {
    if ($rowOut($name, $meta)) {
        $anyUnavailable = true;
    }
}

echo str_repeat('-', 72) . PHP_EOL;

if ($anyUnavailable) {
    echo 'WARNING: One or more counts are unavailable (degraded).' . PHP_EOL;
    $exitDegraded();
}

echo 'All whitelist counts returned ok.' . PHP_EOL;
exit(0);

<?php

declare(strict_types=1);

/**
 * CLI: remove stale local import CSVs that use the naming pattern
 *   SYMBOL_<exportTimestamp>_<rangeTimestamp>.csv
 *
 * For each directory + SYMBOL group, files with an older <exportTimestamp> than
 * the newest in that group are redundant (importers merge by date; newer exports
 * supersede older downloads).
 *
 * Default: dry-run (lists files that would be deleted). Use --execute to unlink.
 *
 * Typical roots: data/indexes (per index subfolder), data/securities (under repo root).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "cleanup_stale_import_csvs.php must be run from the command line.\n");
    exit(1);
}

/** @var array<string, string|true> $opts */
$opts = [];
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (!str_starts_with($arg, '--')) {
        fwrite(STDERR, "Unexpected argument: {$arg}\n");
        exit(1);
    }
    $rest = substr($arg, 2);
    $eq = strpos($rest, '=');
    if ($eq === false) {
        $opts[$rest] = true;
    } else {
        $opts[substr($rest, 0, $eq)] = substr($rest, $eq + 1);
    }
}

$execute = isset($opts['execute']);
$quiet = isset($opts['quiet']);
$root = isset($opts['root']) && is_string($opts['root']) && $opts['root'] !== ''
    ? $opts['root']
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';

if (!is_dir($root)) {
    fwrite(STDERR, "Not a directory: {$root}\n");
    exit(1);
}

/**
 * @return array{sym: string, export: string, range: string}|null
 */
function komodo_cleanup_parse_csv_name(string $basename): ?array
{
    if (!str_ends_with(strtolower($basename), '.csv')) {
        return null;
    }
    $name = substr($basename, 0, -4);
    $parts = explode('_', $name, 3);
    if (count($parts) !== 3) {
        return null;
    }
    [$sym, $export, $range] = $parts;
    if ($sym === '' || !preg_match('/^\d+$/', $export) || !preg_match('/^\d+$/', $range)) {
        return null;
    }

    return ['sym' => strtoupper($sym), 'export' => $export, 'range' => $range];
}

/** @var array<string, list<string>> $groups */
$groups = [];
$skipped = [];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
);
foreach ($it as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }
    if (strtolower($fileInfo->getExtension()) !== 'csv') {
        continue;
    }
    $path = $fileInfo->getPathname();
    $parsed = komodo_cleanup_parse_csv_name($fileInfo->getBasename());
    if ($parsed === null) {
        $skipped[] = $path;
        continue;
    }
    $dir = $fileInfo->getPath();
    $key = $dir . "\0" . $parsed['sym'];
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $path;
}

/** @var list<string> $stale */
$stale = [];

foreach ($groups as $key => $paths) {
    if (count($paths) < 2) {
        continue;
    }
    /** @var array<string, string> $exportByPath */
    $exportByPath = [];
    $maxExport = '';
    foreach ($paths as $p) {
        $bn = basename($p);
        $parsed = komodo_cleanup_parse_csv_name($bn);
        if ($parsed === null) {
            continue;
        }
        $ex = $parsed['export'];
        $exportByPath[$p] = $ex;
        if ($maxExport === '' || strcmp($ex, $maxExport) > 0) {
            $maxExport = $ex;
        }
    }
    foreach ($paths as $p) {
        $ex = $exportByPath[$p] ?? '';
        if ($ex !== '' && $ex !== $maxExport) {
            $stale[] = $p;
        }
    }
}

sort($stale, SORT_STRING);

if (!$quiet) {
    echo "Komodo stale import CSV cleanup\n";
    echo 'Root: ' . str_replace('\\', '/', $root) . "\n";
    echo 'Mode: ' . ($execute ? 'EXECUTE (deleting)' : 'DRY-RUN') . "\n";
    echo 'Groups scanned: ' . count($groups) . "\n";
    echo 'Non-matching CSV names (skipped): ' . count($skipped) . "\n";
    echo 'Stale files (older export id in same folder+symbol): ' . count($stale) . "\n\n";
}

if ($stale === []) {
    if (!$quiet) {
        echo "Nothing to remove.\n";
    }
    exit(0);
}

foreach ($stale as $p) {
    if (!$quiet) {
        echo ($execute ? 'DELETE ' : 'WOULD DELETE ') . str_replace('\\', '/', $p) . "\n";
    }
    if ($execute) {
        if (!@unlink($p)) {
            fwrite(STDERR, "Failed to delete: {$p}\n");
        }
    }
}

if (!$quiet) {
    echo "\n" . ($execute ? 'Done.' : 'No files removed. Re-run with --execute to delete.') . "\n";
}

exit(0);

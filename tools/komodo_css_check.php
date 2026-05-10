<?php

declare(strict_types=1);

/**
 * CSS bundle maintenance: validates assets/css/style.css @import chain.
 *
 *   php tools/komodo_css_check.php        # exit 0 = all imports exist, no orphans
 *   php tools/komodo_css_check.php list # print import order + line counts
 *
 * Smoke tests require this file and call komodo_css_validate_bundle().
 */

/**
 * @return array{errors: list<string>, imports: list<string>}
 */
function komodo_css_validate_bundle(string $repoRoot): array
{
    $errors = [];
    $cssDir = $repoRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css';
    $stylePath = $cssDir . DIRECTORY_SEPARATOR . 'style.css';

    if (!is_file($stylePath)) {
        return ['errors' => ['missing assets/css/style.css'], 'imports' => []];
    }

    $raw = file_get_contents($stylePath);
    if ($raw === false) {
        return ['errors' => ['cannot read assets/css/style.css'], 'imports' => []];
    }

    preg_match_all('/@import\s+url\(\s*["\']([^"\']+)["\']\s*\)\s*;/', $raw, $matches);
    /** @var list<string> $imports */
    $imports = $matches[1] ?? [];

    if ($imports === []) {
        $errors[] = 'style.css has no @import url("...") entries';
    }

    foreach ($imports as $file) {
        if ($file === '' || str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
            $errors[] = 'reject import path (use flat filename only): ' . $file;

            continue;
        }
        $part = $cssDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($part)) {
            $errors[] = 'missing imported file: assets/css/' . $file;
        }
    }

    $allowed = array_merge(['style.css'], $imports);
    foreach (glob($cssDir . DIRECTORY_SEPARATOR . '*.css') ?: [] as $abs) {
        $base = basename((string) $abs);
        if (!in_array($base, $allowed, true)) {
            $errors[] = 'orphan stylesheet (add @import to style.css or remove): assets/css/' . $base;
        }
    }

    return ['errors' => $errors, 'imports' => $imports];
}

/**
 * @param list<string> $imports
 *
 * @return list<array{file: string, lines: int, bytes: int}>
 */
function komodo_css_import_stats(string $repoRoot, array $imports): array
{
    $cssDir = $repoRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css';
    $out = [];
    foreach ($imports as $file) {
        $path = $cssDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            $out[] = ['file' => $file, 'lines' => 0, 'bytes' => 0];

            continue;
        }
        $content = file_get_contents($path);
        $bytes = $content === false ? 0 : strlen($content);
        $lines = $content === false ? 0 : substr_count($content, "\n") + ($content !== '' ? 1 : 0);
        $out[] = ['file' => $file, 'lines' => $lines, 'bytes' => $bytes];
    }

    return $out;
}

if (PHP_SAPI === 'cli' && pathinfo((string) ($argv[0] ?? ''), PATHINFO_FILENAME) === 'komodo_css_check') {
    $root = dirname(__DIR__);
    $cmd = $argv[1] ?? 'check';
    $result = komodo_css_validate_bundle($root);

    if ($cmd === 'list') {
        echo 'assets/css/style.css — import chain' . PHP_EOL;
        $stats = komodo_css_import_stats($root, $result['imports']);
        $n = 0;
        foreach ($stats as $row) {
            ++$n;
            printf(
                "  %2d. %-36s %5d lines  %s\n",
                $n,
                $row['file'],
                $row['lines'],
                number_format((float) $row['bytes']) . ' B',
            );
        }
        $totalLines = array_sum(array_column($stats, 'lines'));
        $totalBytes = array_sum(array_column($stats, 'bytes'));
        printf('  %36s %5d lines  %s%s', '(partials subtotal)', $totalLines, number_format((float) $totalBytes) . ' B', PHP_EOL);
        foreach ($result['errors'] as $err) {
            echo 'FAIL: ' . $err . PHP_EOL;
        }
        exit($result['errors'] !== [] ? 1 : 0);
    }

    foreach ($result['errors'] as $err) {
        echo 'FAIL: ' . $err . PHP_EOL;
    }
    if ($result['errors'] !== []) {
        exit(1);
    }
    echo 'PASS: CSS bundle — ' . count($result['imports']) . ' partials, no orphans.' . PHP_EOL;
    exit(0);
}

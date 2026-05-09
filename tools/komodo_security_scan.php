<?php

declare(strict_types=1);

/**
 * Lightweight local tripwire — not a full security audit.
 * Scans app/ and public/ PHP for risky SQL wording and dynamic includes.
 */

$root = dirname(__DIR__);
$dirs = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'public',
];

$sqlPattern = '/\b(INSERT|UPDATE|DELETE|ALTER|DROP|CREATE|TRUNCATE|REPLACE)\b/i';

$dynIncludePattern = '/\b(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)/i';

$localLeakPatterns = [
    '/file_get_contents\s*\(\s*[^)]*local\.php/i',
    '/readfile\s*\(\s*[^)]*local\.php/i',
];

echo 'Komodo security tripwire scan' . PHP_EOL;
echo 'Roots: app/, public/*.php only' . PHP_EOL . PHP_EOL;

/** @var list<array{type: string, file: string, line: int, excerpt: string}> $findings */
$findings = [];

$allowFooterSqlMention = static function (string $file, string $line): bool {
        $norm = str_replace('\\', '/', $file);
        if (!str_contains($norm, '/partials/footer.php')) {
            return false;
        }
        if (str_contains($line, 'read-only')
            && str_contains($line, 'INSERT')
            && str_contains($line, 'UPDATE')
            && str_contains($line, 'DELETE')
        ) {
            return true;
        }

        return false;
};

$trimLine = static function (string $line): string {
        $semi = strpos($line, '//');
        if ($semi !== false) {
            $line = substr($line, 0, $semi);
        }

        return $line;
};

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $fileinfo) {
        if (!$fileinfo->isFile() || strtolower($fileinfo->getExtension()) !== 'php') {
            continue;
        }
        $path = $fileinfo->getPathname();
        $rel = substr($path, strlen($root) + 1);
        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        // Strip block comments loosely to reduce comment false positives for SQL verbs.
        $stripped = preg_replace('/\\/\\*[\\s\\S]*?\\*\\//m', '', $contents);
        if ($stripped === null) {
            $stripped = $contents;
        }

        foreach (preg_split('/\r\n|\r|\n/', $stripped) ?: [] as $num => $line) {
            $lineno = $num + 1;
            $logical = ($trimLine)($line);
            if (trim($logical) === '' || preg_match('/^\s*[#*]|^\s*\*/', $line)) {
                continue;
            }

            if (preg_match($sqlPattern, $logical)
                && !($allowFooterSqlMention)($path, $line)) {
                $findings[] = [
                    'type' => 'sql_keyword',
                    'file' => $rel,
                    'line' => $lineno,
                    'excerpt' => trim(substr($line, 0, 120)),
                ];
            }

            if (preg_match($dynIncludePattern, $logical)) {
                $findings[] = [
                    'type' => 'dynamic_include',
                    'file' => $rel,
                    'line' => $lineno,
                    'excerpt' => trim(substr($line, 0, 120)),
                ];
            }

            foreach ($localLeakPatterns as $lp) {
                if (preg_match($lp, $logical)) {
                    $findings[] = [
                        'type' => 'local_read',
                        'file' => $rel,
                        'line' => $lineno,
                        'excerpt' => trim(substr($line, 0, 120)),
                    ];
                }
            }
        }
    }
}

if ($findings === []) {
    echo 'No findings (exit 0).' . PHP_EOL;
    exit(0);
}

fwrite(STDOUT, 'WARNINGS:' . PHP_EOL);
foreach ($findings as $hit) {
    echo sprintf(
        '  [%s] %s:%d  %s' . PHP_EOL,
        $hit['type'],
        str_replace('\\', '/', $hit['file']),
        $hit['line'],
        $hit['excerpt']
    );
}

exit(1);

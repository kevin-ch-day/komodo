<?php

declare(strict_types=1);

/**
 * CLI-only: import local CSV files into index_daily_prices (UPSERT).
 * Not callable from the web app — Komodo remains read-only in the browser.
 *
 * Output: default concise. --quiet, --verbose, --debug (debug > verbose > quiet).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "import_index_prices.php must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../app/config/database.php';

const KOMODO_IDX_LOG_QUIET = 0;
const KOMODO_IDX_LOG_NORMAL = 1;
const KOMODO_IDX_LOG_VERBOSE = 2;
const KOMODO_IDX_LOG_DEBUG = 3;

// --- Argument parsing -------------------------------------------------

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

$hasDry = isset($opts['dry-run']);
$hasExec = isset($opts['execute']);
if ($hasDry && $hasExec) {
    fwrite(STDERR, "Use only one of: --dry-run OR --execute (not both).\n");
    exit(1);
}

// Writes only with explicit --execute; default / --dry-run = no DB writes
$execute = $hasExec;
$dryRun = !$execute;
$idxLogLevel = KOMODO_IDX_LOG_NORMAL;
if (isset($opts['debug'])) {
    $idxLogLevel = KOMODO_IDX_LOG_DEBUG;
} elseif (isset($opts['verbose'])) {
    $idxLogLevel = KOMODO_IDX_LOG_VERBOSE;
} elseif (isset($opts['quiet'])) {
    $idxLogLevel = KOMODO_IDX_LOG_QUIET;
}

$file = isset($opts['file']) && is_string($opts['file']) ? $opts['file'] : null;
$dir = isset($opts['dir']) && is_string($opts['dir']) ? $opts['dir'] : null;
$indexCode = isset($opts['index-code']) && is_string($opts['index-code']) ? strtoupper(trim($opts['index-code'])) : null;

$maxRows = 0;
if (isset($opts['max-rows'])) {
    $mr = $opts['max-rows'];
    if ($mr === true || $mr === '') {
        fwrite(STDERR, "Invalid --max-rows: pass a number, e.g. --max-rows=10\n");
        exit(1);
    }
    if (!is_string($mr) || !preg_match('/^-?\d+$/', $mr)) {
        fwrite(STDERR, "Invalid --max-rows: use a non-negative integer, e.g. --max-rows=10\n");
        exit(1);
    }
    $maxRows = max(0, (int) $mr);
}

if (($file === null) === ($dir === null)) {
    fwrite(STDERR, "Specify exactly one of: --file=<path> or --dir=<path>\n");
    exit(1);
}

if ($indexCode === null || $indexCode === '') {
    fwrite(STDERR, "Required: --index-code=DJIA|SP500|NASDAQ_COMP\n");
    exit(1);
}

/** @var array<string, int> $INDEX_MAP */
$INDEX_MAP = [
    'DJIA' => 1,
    'SP500' => 2,
    'NASDAQ_COMP' => 3,
];

if (!isset($INDEX_MAP[$indexCode])) {
    fwrite(STDERR, "Unknown --index-code: {$indexCode}. Allowed: DJIA, SP500, NASDAQ_COMP\n");
    exit(1);
}

$marketIndexId = $INDEX_MAP[$indexCode];

$csvFiles = [];
if ($file !== null) {
    if (!is_readable($file)) {
        fwrite(STDERR, "File not readable: {$file}\n");
        exit(1);
    }
    $csvFiles[] = $file;
} else {
    if (!is_dir($dir)) {
        fwrite(STDERR, "Not a directory: {$dir}\n");
        exit(1);
    }
    $globbed = glob($dir . DIRECTORY_SEPARATOR . '*.csv', GLOB_NOSORT);
    if ($globbed === false || $globbed === []) {
        fwrite(STDERR, "No .csv files in: {$dir}\n");
        exit(1);
    }
    sort($globbed, SORT_STRING);
    $csvFiles = $globbed;
}

// --- Header canonical keys ------------------------------------------

/**
 * Map trimmed header cell -> canonical field name.
 */
function komodo_import_normalize_header(string $raw): ?string
{
    $h = strtolower(trim($raw));
    $h = str_replace('*', '', $h);
    $h = preg_replace('/\s+/', ' ', $h) ?? $h;

    return match (true) {
        in_array($h, ['date', 'trade date', 'trade_date'], true) => 'trade_date',
        in_array($h, ['open', 'open value'], true) => 'open',
        in_array($h, ['high', 'high value'], true) => 'high',
        in_array($h, ['low', 'low value'], true) => 'low',
        in_array($h, ['close', 'close/last', 'last'], true) => 'close',
        in_array($h, ['adj close', 'adjusted close', 'adjusted close value', 'adjusted_close'], true) => 'adj_close',
        in_array($h, ['volume'], true) => 'volume',
        default => null,
    };
}

/**
 * @return array{0: ?string, 1: list<string>}
 */
function komodo_import_parse_date(string $raw): array
{
    $s = trim($raw);
    if ($s === '') {
        return [null, ['Empty date']];
    }
    // ISO 8601: 2025-11-10T06:00:00.000Z (common in exports)
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T\s]/', $s, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if (!checkdate($mo, $d, $y)) {
            return [null, ['Invalid date in ISO timestamp']];
        }

        return [sprintf('%04d-%02d-%02d', $y, $mo, $d), []];
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        if (!checkdate($mo, $d, $y)) {
            return [null, ['Invalid YYYY-MM-DD']];
        }

        return [sprintf('%04d-%02d-%02d', $y, $mo, $d), []];
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
        $mo = (int) $m[1];
        $d = (int) $m[2];
        $y = (int) $m[3];
        if (!checkdate($mo, $d, $y)) {
            return [null, ['Invalid MM/DD/YYYY']];
        }

        return [sprintf('%04d-%02d-%02d', $y, $mo, $d), []];
    }

    return [null, ['Unparseable date: ' . $s]];
}

/**
 * @return array{0: float|string|null, 1: list<string>}
 */
function komodo_import_parse_decimal(?string $raw, bool $required, string $label): array
{
    if ($raw === null) {
        return $required ? [null, ["Missing {$label}"]] : [null, []];
    }
    $s = trim(str_replace([',', '$', "\xC2\xA0"], '', $raw));
    if ($s === '' || strtoupper($s) === 'NULL') {
        return $required ? [null, ["Missing {$label}"]] : [null, []];
    }
    if (!is_numeric($s)) {
        return [null, ["Non-numeric {$label}: {$s}"]];
    }
    $f = (float) $s;
    if ($f < 0) {
        return [null, ["Negative {$label} not allowed"]];
    }

    return [$f, []];
}

/**
 * @return array{0: ?int, 1: list<string>}
 */
function komodo_import_parse_volume(?string $raw): array
{
    if ($raw === null) {
        return [null, []];
    }
    $s = trim(str_replace([',', '$'], '', $raw));
    if ($s === '' || strtoupper($s) === 'NULL') {
        return [null, []];
    }
    if (!preg_match('/^-?\d+$/', $s)) {
        return [null, ['Volume must be integer or empty']];
    }
    $n = (int) $s;
    if ($n < 0) {
        return [null, ['Negative volume not allowed']];
    }

    return [$n, []];
}

/**
 * Bind DECIMAL columns as strings so native PDO prepares behave consistently.
 */
function komodo_import_decimal_param(?float $v): ?string
{
    if ($v === null) {
        return null;
    }

    return sprintf('%.6f', $v);
}

/**
 * @param list<list<string>> $rows
 * @return array{headers: list<string>, colMap: array<string, int>}
 */
function komodo_import_map_headers(array $rows): array
{
    if ($rows === []) {
        return [[], []];
    }
    $headerRow = $rows[0];
    $colMap = [];
    foreach ($headerRow as $i => $cell) {
        $canon = komodo_import_normalize_header((string) $cell);
        if ($canon !== null && !isset($colMap[$canon])) {
            $colMap[$canon] = $i;
        }
    }

    return ['headers' => $headerRow, 'colMap' => $colMap];
}

/**
 * @param array<string, int> $colMap
 * @param list<string> $row
 * @return array{0: ?string, 1: array<string, mixed>, 2: list<string>, 3: list<string>}
 */
function komodo_import_build_normalized_row(array $colMap, array $row, string $todayYmd): array
{
    $warnings = [];

    $g = static function (string $key) use ($colMap, $row): ?string {
        if (!isset($colMap[$key])) {
            return null;
        }
        $i = $colMap[$key];

        return isset($row[$i]) ? (string) $row[$i] : null;
    };

    [$ymd, $de] = komodo_import_parse_date((string) ($g('trade_date') ?? ''));
    if ($ymd === null) {
        return [null, [], $de, []];
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    if ($dt instanceof DateTimeImmutable) {
        $dow = (int) $dt->format('N');
        if ($dow >= 6) {
            $warnings[] = "Weekend date: {$ymd}";
        }
    }
    if ($ymd < '2013-01-01') {
        $warnings[] = "Date before 2013-01-01: {$ymd}";
    }
    if ($ymd > $todayYmd) {
        $warnings[] = "Date after today ({$todayYmd}): {$ymd}";
    }

    [$open, $oe] = komodo_import_parse_decimal($g('open'), false, 'open');
    [$high, $he] = komodo_import_parse_decimal($g('high'), false, 'high');
    [$low, $le] = komodo_import_parse_decimal($g('low'), false, 'low');
    [$close, $ce] = komodo_import_parse_decimal($g('close'), true, 'close');
    $errs = array_merge($oe, $he, $le, $ce);
    if ($errs !== []) {
        return [null, [], $errs, []];
    }

    $adjRaw = $g('adj_close');
    if ($adjRaw === null || trim(str_replace(',', '', $adjRaw)) === '') {
        $adj = $close;
    } else {
        [$adjParsed, $ae] = komodo_import_parse_decimal($adjRaw, true, 'adjusted_close');
        $errs = array_merge($errs, $ae);
        if ($errs !== []) {
            return [null, [], $errs, []];
        }
        $adj = $adjParsed;
    }

    if ($high !== null && $low !== null && (float) $high < (float) $low) {
        return [null, [], ['high < low'], []];
    }

    [$vol, $ve] = komodo_import_parse_volume($g('volume'));
    if ($ve !== []) {
        return [null, [], $ve, []];
    }

    $norm = [
        'trade_date' => $ymd,
        'open_value' => $open,
        'high_value' => $high,
        'low_value' => $low,
        'close_value' => $close,
        'adjusted_close_value' => $adj,
        'volume' => $vol,
    ];

    return [$ymd, $norm, [], $warnings];
}

/**
 * @return list<list<string>>
 */
function komodo_import_read_csv_rows(string $path): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }
    $out = [];
    while (($r = fgetcsv($fh)) !== false) {
        $out[] = $r;
    }
    fclose($fh);

    return $out;
}

// --- Parse all files --------------------------------------------------

$todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');

$allWarnings = [];
$allRejected = [];
$mergedByDate = [];
$duplicateOverwrites = 0;
$filesMeta = [];

/** @var list<array{path: string, rows: list<list<string>>, colMap: array<string, int>, headers: list<string>}> $filePayloads */
$filePayloads = [];

foreach ($csvFiles as $csvPath) {
    $rows = komodo_import_read_csv_rows($csvPath);
    if ($rows === []) {
        $filesMeta[] = ['path' => $csvPath, 'headers' => [], 'colMap' => [], 'data_rows' => 0, 'error' => 'Empty or unreadable'];
        continue;
    }
    ['headers' => $headers, 'colMap' => $colMap] = komodo_import_map_headers($rows);
    $need = ['trade_date', 'close'];
    $missing = null;
    foreach ($need as $n) {
        if (!isset($colMap[$n])) {
            $missing = $n;
            break;
        }
    }
    if ($missing !== null) {
        $filesMeta[] = [
            'path' => $csvPath,
            'headers' => $headers,
            'colMap' => $colMap,
            'data_rows' => 0,
            'error' => "Missing required column mapping for: {$missing}",
        ];
        continue;
    }
    $filesMeta[] = [
        'path' => $csvPath,
        'headers' => $headers,
        'colMap' => $colMap,
        'data_rows' => 0,
        'error' => null,
    ];
    $filePayloads[] = [
        'path' => $csvPath,
        'rows' => $rows,
        'colMap' => $colMap,
        'headers' => $headers,
    ];
}

$fatalFile = false;
foreach ($filesMeta as $fm) {
    if ($fm['error'] !== null) {
        $fatalFile = true;
    }
}
if ($fatalFile) {
    if ($idxLogLevel === KOMODO_IDX_LOG_QUIET) {
        fwrite(STDERR, "Komodo index {$indexCode}: FATAL (CSV/header); use default output for paths.\n");
    } else {
        echo "Komodo index import — {$indexCode}\n";
        echo 'Mode: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . "\n";
        foreach ($filesMeta as $fm) {
            if ($fm['error'] !== null) {
                echo 'ERROR: ' . $fm['error'] . ' — ' . $fm['path'] . "\n";
            }
        }
        if ($idxLogLevel === KOMODO_IDX_LOG_DEBUG) {
            foreach ($filesMeta as $fm) {
                echo '  - ' . $fm['path'] . "\n";
            }
        }
    }
    fwrite(STDERR, "Aborting: fix CSV headers or paths.\n");
    exit(1);
}

foreach ($filePayloads as $payload) {
    $csvPath = $payload['path'];
    $rows = $payload['rows'];
    $colMap = $payload['colMap'];
    if (!isset($colMap['open'])) {
        $allWarnings[] = basename($csvPath) . ': no Open column — open_value will be NULL where applicable';
    }
    if (!isset($colMap['high']) || !isset($colMap['low'])) {
        $allWarnings[] = basename($csvPath) . ': missing High/Low — nullable';
    }

    $dataRowCount = 0;
    for ($ri = 1, $rc = count($rows); $ri < $rc; $ri++) {
        $row = $rows[$ri];
        if ($row === [] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
            continue;
        }
        $dataRowCount++;
        [$ymd, $norm, $errs, $w] = komodo_import_build_normalized_row($colMap, $row, $todayYmd);
        foreach ($w as $w1) {
            $allWarnings[] = basename($csvPath) . " row " . ($ri + 1) . ": {$w1}";
        }
        if ($ymd === null || $norm === []) {
            foreach ($errs as $e) {
                $allRejected[] = basename($csvPath) . " row " . ($ri + 1) . ": {$e}";
            }
            continue;
        }
        if (isset($mergedByDate[$ymd])) {
            $duplicateOverwrites++;
        }
        $mergedByDate[$ymd] = $norm;
    }

    foreach ($filesMeta as $i => $fm) {
        if ($fm['path'] === $csvPath && $fm['error'] === null) {
            $filesMeta[$i]['data_rows'] = $dataRowCount;
            break;
        }
    }
}

ksort($mergedByDate, SORT_STRING);
$validRows = array_values($mergedByDate);
if ($maxRows > 0) {
    $validRows = array_slice($validRows, 0, $maxRows);
}

$rowsReadTotal = 0;
foreach ($filesMeta as $fm) {
    if ($fm['error'] === null) {
        $rowsReadTotal += $fm['data_rows'];
    }
}

if ($validRows === []) {
    fwrite(STDERR, "\nNo valid rows to import.\n");
    exit(1);
}

$localCfg = komodo_load_local_config();
$normCfg = is_array($localCfg) ? komodo_normalize_db_config($localCfg) : null;
$dbDisplay = (is_array($normCfg) && ($normCfg['database'] ?? '') !== '') ? (string) $normCfg['database'] : '(unknown)';
$nParsed = count($validRows);
$nRej = count($allRejected);
$nWarn = count($allWarnings);
$rangeStr = $validRows[0]['trade_date'] . ' → ' . $validRows[$nParsed - 1]['trade_date'];

$emitIdxConciseDry = static function () use ($indexCode, $marketIndexId, $dbDisplay, $csvFiles, $nParsed, $nRej, $nWarn, $rangeStr, $maxRows, $duplicateOverwrites): void {
    echo "Komodo index import — {$indexCode}\n";
    echo "Mode: DRY-RUN\n";
    echo "Database: {$dbDisplay}\n";
    echo "Index: {$indexCode} → market_index_id={$marketIndexId}\n";
    echo 'Files: ' . count($csvFiles) . "\n";
    echo "Rows parsed: {$nParsed}\n";
    echo "Rejected: {$nRej}\n";
    echo "Warnings: {$nWarn}\n";
    echo "Range: {$rangeStr}\n";
    if ($maxRows > 0) {
        echo "max-rows: {$maxRows}\n";
    }
    echo "Same-date overwrites: {$duplicateOverwrites}\n";
    echo "Next: run with --execute to upsert into index_daily_prices.\n";
};

$emitIdxVerboseDry = static function () use ($filesMeta, $allWarnings, $allRejected, $rowsReadTotal, $nParsed, $maxRows, $duplicateOverwrites): void {
    echo "\n--- Verbose detail ---\n";
    foreach ($filesMeta as $fm) {
        echo '  - ' . $fm['path'] . "\n";
        if ($fm['error'] !== null) {
            echo '    ERROR: ' . $fm['error'] . "\n";
            continue;
        }
        echo '    Data rows (non-empty): ' . $fm['data_rows'] . "\n";
    }
    echo "Rows read (body lines): {$rowsReadTotal}\n";
    if ($allWarnings !== []) {
        echo "\nWarnings:\n";
        foreach ($allWarnings as $w) {
            echo "  - {$w}\n";
        }
    }
    if ($allRejected !== []) {
        echo "\nRejected:\n";
        foreach (array_slice($allRejected, 0, 50) as $r) {
            echo "  - {$r}\n";
        }
        if (count($allRejected) > 50) {
            echo '  ... and ' . (count($allRejected) - 50) . " more\n";
        }
    }
};

if ($idxLogLevel === KOMODO_IDX_LOG_DEBUG) {
    echo "=== Komodo index CSV import (debug) ===\n";
    if ($execute) {
        echo "*** EXECUTE MODE — DATABASE WRITES ENABLED ***\n";
    } else {
        echo "*** DRY-RUN — no database writes (add --execute to insert/upsert) ***\n";
    }
    echo 'Mode: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . "\n";
    echo "Index: {$indexCode} => market_index_id={$marketIndexId}\n";
    if ($maxRows > 0) {
        echo "max-rows: {$maxRows}\n";
    }
    echo "Files (" . count($csvFiles) . "):\n";
    foreach ($filesMeta as $fm) {
        echo '  - ' . $fm['path'] . "\n";
        if ($fm['error'] !== null) {
            echo '    ERROR: ' . $fm['error'] . "\n";
            continue;
        }
        echo '    Headers: ' . implode(' | ', array_map('strval', $fm['headers'])) . "\n";
        echo '    Mapped columns: ' . json_encode($fm['colMap'], JSON_THROW_ON_ERROR) . "\n";
        echo '    Data rows (non-empty): ' . $fm['data_rows'] . "\n";
    }
    echo "\nParsed valid rows (after cross-file dedupe" . ($maxRows > 0 ? ', max-rows applied' : '') . "): {$nParsed}\n";
    echo "Rejected lines: {$nRej}\n";
    echo "Same-date overwrites (later file wins): {$duplicateOverwrites}\n";
    if ($allWarnings !== []) {
        echo "\nWarnings:\n";
        foreach ($allWarnings as $w) {
            echo "  - {$w}\n";
        }
    }
    if ($allRejected !== []) {
        echo "\nRejected:\n";
        foreach ($allRejected as $r) {
            echo "  - {$r}\n";
        }
    }
    $first = $validRows[0]['trade_date'];
    $last = $validRows[$nParsed - 1]['trade_date'];
    echo "\nDate range (valid batch): {$first} .. {$last}\n";
    echo "First 5 normalized rows:\n";
    foreach (array_slice($validRows, 0, 5) as $vr) {
        echo '  ' . json_encode($vr, JSON_THROW_ON_ERROR) . "\n";
    }
    echo "\n--- Concise summary ---\n";
}

if (!$execute) {
    if ($idxLogLevel === KOMODO_IDX_LOG_QUIET) {
        echo "Komodo index {$indexCode}: DRY-RUN rows={$nParsed} rej={$nRej} warn={$nWarn}\n";
        exit(0);
    }
    $emitIdxConciseDry();
    if ($idxLogLevel === KOMODO_IDX_LOG_VERBOSE) {
        $emitIdxVerboseDry();
    }
    exit(0);
}

// --- Execute --------------------------------------------------------

$pdo = get_pdo();
if ($pdo === null) {
    fwrite(STDERR, "Database not available. Configure app/config/local.php and ensure MariaDB is running.\n");
    exit(1);
}

if ($idxLogLevel === KOMODO_IDX_LOG_DEBUG && is_array($normCfg)) {
    echo "\nConfig (app/config/local.php, password omitted):\n";
    echo '  host: ' . $normCfg['host'] . "\n";
    echo '  port: ' . $normCfg['port'] . "\n";
    echo '  database: ' . $normCfg['database'] . "\n";
    echo '  user: ' . $normCfg['username'] . "\n";
    $identStmt = $pdo->query('SELECT DATABASE() AS db_name, @@hostname AS server_host');
    if ($identStmt !== false) {
        $ident = $identStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($ident)) {
            echo "\nLive session (via get_pdo()):\n";
            echo '  SELECT DATABASE(): ' . ($ident['db_name'] ?? '') . "\n";
            echo '  @@hostname: ' . ($ident['server_host'] ?? '') . "\n";
        }
    }
}

$sql = <<<'SQL'
INSERT INTO index_daily_prices (
    market_index_id,
    trade_date,
    open_value,
    high_value,
    low_value,
    close_value,
    adjusted_close_value,
    volume
) VALUES (
    :market_index_id,
    :trade_date,
    :open_value,
    :high_value,
    :low_value,
    :close_value,
    :adjusted_close_value,
    :volume
) ON DUPLICATE KEY UPDATE
    open_value = VALUES(open_value),
    high_value = VALUES(high_value),
    low_value = VALUES(low_value),
    close_value = VALUES(close_value),
    adjusted_close_value = VALUES(adjusted_close_value),
    volume = VALUES(volume)
SQL;

$stmt = $pdo->prepare($sql);
$attempted = 0;
$rowCountSum = 0;

try {
    $pdo->beginTransaction();
    foreach ($validRows as $vr) {
        $attempted++;
        $stmt->execute([
            ':market_index_id' => $marketIndexId,
            ':trade_date' => $vr['trade_date'],
            ':open_value' => komodo_import_decimal_param($vr['open_value']),
            ':high_value' => komodo_import_decimal_param($vr['high_value']),
            ':low_value' => komodo_import_decimal_param($vr['low_value']),
            ':close_value' => komodo_import_decimal_param($vr['close_value']),
            ':adjusted_close_value' => komodo_import_decimal_param($vr['adjusted_close_value']),
            ':volume' => $vr['volume'],
        ]);
        $rowCountSum += $stmt->rowCount();
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Import failed (rolled back): " . $e->getMessage() . "\n");
    exit(1);
}

$cntStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM index_daily_prices WHERE market_index_id = ?');
$cntStmt->execute([$marketIndexId]);
$cntRow = $cntStmt->fetch(PDO::FETCH_ASSOC);
$afterCount = is_array($cntRow) ? (int) ($cntRow['c'] ?? 0) : 0;

if ($idxLogLevel === KOMODO_IDX_LOG_QUIET) {
    echo "Komodo index {$indexCode}: EXECUTE COMMITTED upserts={$attempted} db={$afterCount} rej={$nRej} warn={$nWarn}\n";
    exit(0);
}

echo "Komodo index import — {$indexCode}\n";
echo "Mode: EXECUTE\n";
echo "Status: COMMITTED\n";
echo "Database: {$dbDisplay}\n";
echo "Index: {$indexCode} → market_index_id={$marketIndexId}\n";
echo 'Files: ' . count($csvFiles) . "\n";
echo "Rows parsed: {$nParsed}\n";
echo "Rejected: {$nRej}\n";
echo "Warnings: {$nWarn}\n";
echo "Upserts: {$attempted}\n";
echo "DB total: {$afterCount}\n";
echo "Range: {$rangeStr}\n";
if ($maxRows > 0) {
    echo "max-rows: {$maxRows}\n";
}
echo "Next: refresh Market Data / related views as needed.\n";

if ($idxLogLevel === KOMODO_IDX_LOG_VERBOSE || $idxLogLevel === KOMODO_IDX_LOG_DEBUG) {
    echo "\n--- Post-import ---\n";
    echo "UPSERT statements executed: {$attempted}\n";
    echo "PDO rowCount() sum: {$rowCountSum} — often 1=new row, 2=updated row, 0=no change\n";
    echo "\n--- Coverage by index ---\n";
    $cov = $pdo->query(
        <<<'SQL'
SELECT
    mi.index_code,
    mi.index_name,
    COUNT(idp.trade_date) AS price_rows,
    MIN(idp.trade_date) AS first_trade_date,
    MAX(idp.trade_date) AS last_trade_date
FROM market_indexes mi
LEFT JOIN index_daily_prices idp
    ON mi.market_index_id = idp.market_index_id
GROUP BY
    mi.market_index_id,
    mi.index_code,
    mi.index_name
ORDER BY mi.index_code
SQL
    );
    if ($cov !== false) {
        while ($r = $cov->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode($r, JSON_THROW_ON_ERROR) . "\n";
        }
    }
}

exit(0);

<?php

declare(strict_types=1);

/**
 * CLI-only: import local security CSV files into security_daily_prices (UPSERT).
 * Not callable from the web app.
 *
 * Output: default concise summary. Modifiers: --quiet, --verbose, --debug (debug wins over verbose over quiet).
 *
 * Flags:
 *   --all                Batch: every ticker found from CSV filenames in --dir (default: data/securities).
 *   --tickers=A,B,C      Batch: only these tickers (same dir scan).
 *   --fail-fast          Batch: stop on first ticker failure (default: continue, summarize failures).
 *   --archive-on-success After a successful execute commit, move that ticker’s CSVs to data/imported/securities/YYYYMMDD_HHMMSS/.
 *   --verbose-warnings   With --verbose/--debug: list every parser warning (default: summary / examples only).
 *   --fail-on-warnings   Exit 2 when warnings occurred (dry-run or after COMMIT) — for scripts/CI.
 *
 * No positional args: defaults to --all --dry-run on data/securities (repo-relative).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "import_security_prices.php must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../app/config/database.php';

/** Output verbosity (highest wins: debug > verbose > quiet > normal). */
const KOMODO_SEC_LOG_QUIET = 0;
const KOMODO_SEC_LOG_NORMAL = 1;
const KOMODO_SEC_LOG_VERBOSE = 2;
const KOMODO_SEC_LOG_DEBUG = 3;

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

if (isset($opts['tickers']) && !is_string($opts['tickers'])) {
    fwrite(STDERR, "Invalid --tickers: use --tickers=A,B,C\n");
    exit(1);
}

$hasDry = isset($opts['dry-run']);
$hasExec = isset($opts['execute']);
if ($hasDry && $hasExec) {
    fwrite(STDERR, "Use only one of: --dry-run OR --execute (not both).\n");
    exit(1);
}

$execute = $hasExec;
$failOnWarnings = isset($opts['fail-on-warnings']);
$verboseWarnings = isset($opts['verbose-warnings']);
$logLevel = KOMODO_SEC_LOG_NORMAL;
if (isset($opts['debug'])) {
    $logLevel = KOMODO_SEC_LOG_DEBUG;
} elseif (isset($opts['verbose'])) {
    $logLevel = KOMODO_SEC_LOG_VERBOSE;
} elseif (isset($opts['quiet'])) {
    $logLevel = KOMODO_SEC_LOG_QUIET;
}
$file = isset($opts['file']) && is_string($opts['file']) ? $opts['file'] : null;
$dir = isset($opts['dir']) && is_string($opts['dir']) ? $opts['dir'] : null;
$ticker = isset($opts['ticker']) && is_string($opts['ticker']) ? strtoupper(trim($opts['ticker'])) : null;
$flagAll = isset($opts['all']);
$tickersOpt = isset($opts['tickers']) && is_string($opts['tickers']) ? $opts['tickers'] : null;
$failFast = isset($opts['fail-fast']);
$archiveOnSuccess = isset($opts['archive-on-success']);

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

$repoRoot = dirname(__DIR__);
$defaultSecDir = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'securities';

$argc = count($argv);
$explicitBatch = $flagAll || ($tickersOpt !== null && $tickersOpt !== '');
/** No ticker/file and not an explicit batch: default to scanning all tickers (dry-run unless --execute with explicit batch). */
$implicitBatch = $file === null && ($ticker === null || $ticker === '') && !$explicitBatch && ($argc === 1 || !$execute);

if ($flagAll && $tickersOpt !== null && $tickersOpt !== '') {
    fwrite(STDERR, "Use only one of: --all OR --tickers=... (not both).\n");
    exit(1);
}
if ($ticker !== null && $ticker !== '' && ($flagAll || ($tickersOpt !== null && $tickersOpt !== ''))) {
    fwrite(STDERR, "Do not combine --ticker with --all or --tickers.\n");
    exit(1);
}
if ($file !== null && ($flagAll || ($tickersOpt !== null && $tickersOpt !== ''))) {
    fwrite(STDERR, "Do not combine --file with --all or --tickers.\n");
    exit(1);
}

$batchMode = false;
$batchTickerOrder = [];

if ($file !== null) {
    if ($ticker === null || $ticker === '') {
        fwrite(STDERR, "With --file, required: --ticker=SYMBOL\n");
        exit(1);
    }
    if ($dir !== null) {
        fwrite(STDERR, "Do not pass --dir with --file (use --file only).\n");
        exit(1);
    }
    $batchMode = false;
} elseif ($ticker !== null && $ticker !== '') {
    $batchMode = false;
    $dir = $dir ?? $defaultSecDir;
} elseif ($explicitBatch || $implicitBatch) {
    $batchMode = true;
    $dir = $dir ?? $defaultSecDir;
    if ($tickersOpt !== null && $tickersOpt !== '') {
        foreach (explode(',', $tickersOpt) as $p) {
            $u = strtoupper(trim($p));
            if ($u !== '') {
                $batchTickerOrder[] = $u;
            }
        }
        if ($batchTickerOrder === []) {
            fwrite(STDERR, "Invalid --tickers: list one or more symbols.\n");
            exit(1);
        }
    }
} else {
    if ($execute && !$flagAll && $tickersOpt === null) {
        fwrite(STDERR, "Batch --execute requires --all or --tickers=... (or use --ticker for a single symbol).\n");
        exit(1);
    }
    fwrite(STDERR, "Specify --ticker=SYMBOL, or --all, or --tickers=A,B,C (or run with no args for --all dry-run).\n");
    exit(1);
}

if ($batchMode && $dir === null) {
    fwrite(STDERR, "Internal error: batch mode without directory.\n");
    exit(1);
}

/**
 * Basename must start with TICKER_ (case-insensitive) for directory scans.
 */
function komodo_sec_csv_matches_ticker(string $path, string $tickerUpper): bool
{
    $base = basename($path);
    $prefix = $tickerUpper . '_';

    return str_starts_with(strtoupper($base), $prefix);
}

/**
 * Ticker from pattern TICKER_*.csv (substring before first underscore), or null if invalid.
 */
function komodo_sec_ticker_from_csv_basename(string $path): ?string
{
    $base = basename($path);
    if (!str_ends_with(strtolower($base), '.csv')) {
        return null;
    }
    $u = strpos($base, '_');
    if ($u === false || $u < 1) {
        return null;
    }

    return strtoupper(substr($base, 0, $u));
}

/**
 * @return array{groups: list<array{ticker: string, paths: list<string>}>, skipped: list<string>}
 */
function komodo_sec_discover_ticker_groups(string $scanDir, ?array $onlyTickersOrdered, int $logLevel): array
{
    /** @var list<string> $skipped */
    $skipped = [];
    if (!is_dir($scanDir)) {
        fwrite(STDERR, "Not a directory: {$scanDir}\n");

        return ['groups' => [], 'skipped' => []];
    }
    $globbed = glob($scanDir . DIRECTORY_SEPARATOR . '*.csv', GLOB_NOSORT);
    if ($globbed === false || $globbed === []) {
        fwrite(STDERR, "No .csv files in: {$scanDir}\n");

        return ['groups' => [], 'skipped' => []];
    }
    /** @var array<string, list<string>> $by */
    $by = [];
    foreach ($globbed as $p) {
        $t = komodo_sec_ticker_from_csv_basename($p);
        if ($t === null) {
            $skipped[] = $p;
            if ($logLevel >= KOMODO_SEC_LOG_VERBOSE) {
                fwrite(STDERR, "Skip (no TICKER_*.csv pattern): {$p}\n");
            }

            continue;
        }
        if (!isset($by[$t])) {
            $by[$t] = [];
        }
        $by[$t][] = $p;
    }
    foreach ($by as $t => $paths) {
        sort($by[$t], SORT_STRING);
    }
    if ($by === [] && $globbed !== [] && $onlyTickersOrdered === null) {
        fwrite(STDERR, "No filenames matched TICKER_*.csv pattern in: {$scanDir}\n");

        return ['groups' => [], 'skipped' => $skipped];
    }
    if ($onlyTickersOrdered !== null) {
        $out = [];
        $set = $by;
        foreach ($onlyTickersOrdered as $want) {
            if (!isset($set[$want])) {
                fwrite(STDERR, "No CSV files found for ticker \"{$want}\" in directory.\n");
                $out[] = ['ticker' => $want, 'paths' => []];

                continue;
            }
            $out[] = ['ticker' => $want, 'paths' => $set[$want]];
        }

        return ['groups' => $out, 'skipped' => $skipped];
    }
    $keys = array_keys($by);
    sort($keys, SORT_STRING);
    $out = [];
    foreach ($keys as $t) {
        $out[] = ['ticker' => $t, 'paths' => $by[$t]];
    }

    return ['groups' => $out, 'skipped' => $skipped];
}

$csvFiles = [];
if (!$batchMode) {
    if ($file !== null) {
        if (!is_readable($file)) {
            fwrite(STDERR, "File not readable: {$file}\n");
            exit(1);
        }
        $csvFiles[] = $file;
    } else {
        if (!is_dir((string) $dir)) {
            fwrite(STDERR, "Not a directory: {$dir}\n");
            exit(1);
        }
        $globbed = glob($dir . DIRECTORY_SEPARATOR . '*.csv', GLOB_NOSORT);
        if ($globbed === false || $globbed === []) {
            fwrite(STDERR, "No .csv files in: {$dir}\n");
            exit(1);
        }
        foreach ($globbed as $p) {
            if (komodo_sec_csv_matches_ticker($p, (string) $ticker)) {
                $csvFiles[] = $p;
            }
        }
        sort($csvFiles, SORT_STRING);
        if ($csvFiles === []) {
            fwrite(STDERR, "No CSV files matching ticker prefix \"{$ticker}_\" in: {$dir}\n");
            exit(1);
        }
    }
}

// --- Header helpers (aligned with index importer) ---------------------

function komodo_sec_normalize_header(string $raw): ?string
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
function komodo_sec_parse_date(string $raw): array
{
    $s = trim($raw);
    if ($s === '') {
        return [null, ['Empty date']];
    }
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
 * @return array{0: float|null, 1: list<string>}
 */
function komodo_sec_parse_decimal(?string $raw, bool $required, string $label): array
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
function komodo_sec_parse_volume(?string $raw): array
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

function komodo_sec_decimal_param(?float $v): ?string
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
function komodo_sec_map_headers(array $rows): array
{
    if ($rows === []) {
        return [[], []];
    }
    $headerRow = $rows[0];
    $colMap = [];
    foreach ($headerRow as $i => $cell) {
        $canon = komodo_sec_normalize_header((string) $cell);
        if ($canon !== null && !isset($colMap[$canon])) {
            $colMap[$canon] = $i;
        }
    }

    return ['headers' => $headerRow, 'colMap' => $colMap];
}

/**
 * @param array<string, int> $colMap
 * @param list<string> $row
 * @param array{start_date: ?string, end_date: ?string} $secWindow
 * @return array{0: ?string, 1: array<string, mixed>, 2: list<string>, 3: list<string>}
 */
function komodo_sec_build_normalized_row(
    array $colMap,
    array $row,
    string $todayYmd,
    array $secWindow,
): array {
    $warnings = [];

    $g = static function (string $key) use ($colMap, $row): ?string {
        if (!isset($colMap[$key])) {
            return null;
        }
        $i = $colMap[$key];

        return isset($row[$i]) ? (string) $row[$i] : null;
    };

    [$ymd, $de] = komodo_sec_parse_date((string) ($g('trade_date') ?? ''));
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

    $start = $secWindow['start_date'];
    $end = $secWindow['end_date'];
    if ($start !== null && $start !== '' && $ymd < $start) {
        $warnings[] = "trade_date {$ymd} before security start_date {$start}";
    }
    if ($end !== null && $end !== '' && $ymd > $end) {
        $warnings[] = "trade_date {$ymd} after security end_date {$end}";
    }

    [$open, $oe] = komodo_sec_parse_decimal($g('open'), false, 'open');
    [$high, $he] = komodo_sec_parse_decimal($g('high'), false, 'high');
    [$low, $le] = komodo_sec_parse_decimal($g('low'), false, 'low');
    [$close, $ce] = komodo_sec_parse_decimal($g('close'), true, 'close');
    $errs = array_merge($oe, $he, $le, $ce);
    if ($errs !== []) {
        return [null, [], $errs, []];
    }

    $adjRaw = $g('adj_close');
    if ($adjRaw === null || trim(str_replace(',', '', $adjRaw)) === '') {
        $adj = $close;
    } else {
        [$adjParsed, $ae] = komodo_sec_parse_decimal($adjRaw, true, 'adjusted_close');
        $errs = array_merge($errs, $ae);
        if ($errs !== []) {
            return [null, [], $errs, []];
        }
        $adj = $adjParsed;
    }

    if ($high !== null && $low !== null && (float) $high < (float) $low) {
        return [null, [], ['high < low'], []];
    }

    [$vol, $ve] = komodo_sec_parse_volume($g('volume'));
    if ($ve !== []) {
        return [null, [], $ve, []];
    }

    $norm = [
        'trade_date' => $ymd,
        'open_price' => $open,
        'high_price' => $high,
        'low_price' => $low,
        'close_price' => $close,
        'adjusted_close' => $adj,
        'volume' => $vol,
    ];

    return [$ymd, $norm, [], $warnings];
}

/**
 * @return list<list<string>>
 */
function komodo_sec_read_csv_rows(string $path): array
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

/**
 * @param list<string> $allWarnings
 *
 * @return array<string, list<string>>
 */
function komodo_sec_bucket_warnings(array $allWarnings): array
{
    $buckets = [
        'before_security_start' => [],
        'after_security_end' => [],
        'weekend_date' => [],
        'before_2013' => [],
        'future_date' => [],
        'other' => [],
    ];
    foreach ($allWarnings as $w) {
        if (str_contains($w, 'before security start_date')) {
            $buckets['before_security_start'][] = $w;
        } elseif (str_contains($w, 'after security end_date')) {
            $buckets['after_security_end'][] = $w;
        } elseif (str_contains($w, 'Weekend date')) {
            $buckets['weekend_date'][] = $w;
        } elseif (str_contains($w, 'Date before 2013')) {
            $buckets['before_2013'][] = $w;
        } elseif (str_contains($w, 'Date after today')) {
            $buckets['future_date'][] = $w;
        } else {
            $buckets['other'][] = $w;
        }
    }

    return $buckets;
}

/**
 * @param array{s: string, e: string}|null $plan
 */
function komodo_sec_format_plan_window(?array $plan): string
{
    if ($plan === null || (($plan['s'] ?? '') === '' && ($plan['e'] ?? '') === '')) {
        return '—';
    }
    $a = ($plan['s'] ?? '') !== '' ? $plan['s'] : '—';
    $b = ($plan['e'] ?? '') !== '' ? $plan['e'] : '—';

    return "{$a} → {$b}";
}

/**
 * Slack-aware coverage vs vw_market_data_import_plan for a bar span.
 *
 * @param array{s: string, e: string}|null $plan
 *
 * @return array{tag: 'ok'|'no_plan'|'no_bars'|'partial', coverage_line: string, but_phrase: string}
 */
function komodo_sec_plan_coverage_parts(?array $plan, ?string $spanFirst, ?string $spanLast, int $slackDays): array
{
    if ($plan === null || (($plan['s'] ?? '') === '' && ($plan['e'] ?? '') === '')) {
        return ['tag' => 'no_plan', 'coverage_line' => 'NO PLAN (skipped)', 'but_phrase' => ''];
    }
    if ($spanFirst === null || $spanLast === null || $spanFirst === '' || $spanLast === '') {
        return ['tag' => 'no_bars', 'coverage_line' => 'NO BARS', 'but_phrase' => 'no price bars in span'];
    }
    $ps = $plan['s'];
    $pe = $plan['e'];
    $missParts = [];
    $butParts = [];
    if ($ps !== '' && strcmp($spanFirst, $ps) > 0) {
        $gap = komodo_sec_calendar_day_diff($ps, $spanFirst);
        if ($gap > $slackDays) {
            $missParts[] = "missing start by {$gap} days";
            $butParts[] = "missing start by {$gap} days";
        }
    }
    if ($pe !== '' && strcmp($spanLast, $pe) < 0) {
        $gap = komodo_sec_calendar_day_diff($spanLast, $pe);
        if ($gap > $slackDays) {
            $missParts[] = "missing end by {$gap} days";
            $butParts[] = "missing end by {$gap} days";
        }
    }
    if ($missParts === []) {
        return ['tag' => 'ok', 'coverage_line' => 'OK', 'but_phrase' => ''];
    }

    return [
        'tag' => 'partial',
        'coverage_line' => 'PARTIAL — ' . implode('; ', $missParts),
        'but_phrase' => implode('; ', $butParts),
    ];
}

/**
 * Short labels for concise warning buckets (operator-facing).
 *
 * @return array<string, string>
 */
function komodo_sec_warning_bucket_short_labels(): array
{
    return [
        'before_security_start' => 'trade_date before securities.start_date',
        'after_security_end' => 'trade_date after securities.end_date',
        'weekend_date' => 'weekend trade_date',
        'before_2013' => 'date before 2013-01-01',
        'future_date' => 'date after today',
        'other' => 'other',
    ];
}

/**
 * @param array<string, list<string>> $buckets
 * @param list<string> $allWarnings
 */
function komodo_sec_emit_warnings_for_level(
    int $logLevel,
    bool $verboseWarningsFlag,
    int $warningCount,
    array $allWarnings,
    array $buckets,
): void {
    if ($logLevel === KOMODO_SEC_LOG_QUIET || $warningCount === 0) {
        return;
    }
    $short = komodo_sec_warning_bucket_short_labels();
    if ($logLevel === KOMODO_SEC_LOG_NORMAL) {
        echo "Warnings: {$warningCount}\n";
        foreach ($short as $key => $label) {
            $n = count($buckets[$key] ?? []);
            if ($n > 0) {
                echo "- {$n}× {$label}\n";
            }
        }

        return;
    }
    $showAll = $logLevel === KOMODO_SEC_LOG_DEBUG || ($logLevel === KOMODO_SEC_LOG_VERBOSE && $verboseWarningsFlag);
    echo "\nWarnings:\n";
    if ($showAll) {
        foreach ($allWarnings as $w) {
            echo "  - {$w}\n";
        }

        return;
    }
    echo "Warning summary (by kind):\n";
    foreach ($short as $key => $label) {
        $list = $buckets[$key] ?? [];
        $n = count($list);
        if ($n === 0) {
            continue;
        }
        echo "  • {$n}× {$label}\n";
        foreach (array_slice($list, 0, 2) as $ex) {
            echo "      e.g. {$ex}\n";
        }
        if ($n > 2) {
            echo '      … +' . ($n - 2) . " similar\n";
        }
    }
    if (!$verboseWarningsFlag) {
        echo "  (use --verbose or --debug for examples/details; add --verbose-warnings with those for every line)\n";
    }
}

/** Calendar-day tolerance vs plan start/end (weekly files, holidays, lagging extracts). */
const KOMODO_IMPORT_PLAN_DAY_SLACK = 10;

function komodo_sec_sql_date_prefix(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    $s = (string) $v;

    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m) ? $m[1] : null;
}

/**
 * Signed whole calendar days from date A to date B (Y-m-d).
 */
function komodo_sec_calendar_day_diff(string $fromYmd, string $toYmd): int
{
    $a = DateTimeImmutable::createFromFormat('Y-m-d', $fromYmd);
    $b = DateTimeImmutable::createFromFormat('Y-m-d', $toYmd);
    if ($a === false || $b === false) {
        return 0;
    }

    return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
}

/**
 * @return array{0: ?string, 1: ?string}
 */
function komodo_sec_fetch_price_min_max(PDO $pdo, int $securityId): array
{
    try {
        $st = $pdo->prepare('SELECT MIN(trade_date) AS mn, MAX(trade_date) AS mx FROM security_daily_prices WHERE security_id = ?');
        $st->execute([$securityId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) {
            return [null, null];
        }

        return [
            komodo_sec_sql_date_prefix($r['mn'] ?? null),
            komodo_sec_sql_date_prefix($r['mx'] ?? null),
        ];
    } catch (Throwable) {
        return [null, null];
    }
}

/**
 * @return array{s: string, e: string}|null
 */
function komodo_sec_fetch_suggested_window(PDO $pdo, string $tickerUpper): ?array
{
    try {
        $st = $pdo->prepare(
            'SELECT suggested_import_start_date AS s, suggested_import_end_date AS e
             FROM vw_market_data_import_plan
             WHERE ticker_symbol = :t
             LIMIT 1'
        );
        $st->execute([':t' => $tickerUpper]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) {
            return null;
        }
        $s = komodo_sec_sql_date_prefix($r['s'] ?? null) ?? '';
        $e = komodo_sec_sql_date_prefix($r['e'] ?? null) ?? '';
        if ($s === '' && $e === '') {
            return null;
        }

        return ['s' => $s, 'e' => $e];
    } catch (Throwable) {
        return null;
    }
}

/**
 * Compare one date span to vw_market_data_import_plan (telemetry; uses calendar-day slack).
 *
 * @param array{s: string, e: string}|null $plan
 *
 * @return list<string>
 */
function komodo_sec_plan_vs_span_lines(
    ?array $plan,
    ?string $spanFirst,
    ?string $spanLast,
    string $spanLabel,
    int $slackDays,
    bool $forDryRun,
    bool $includePlanHeader = true,
): array {
    $lines = [];
    if ($plan === null || ($plan['s'] === '' && $plan['e'] === '')) {
        $lines[] = 'Plan window: not in vw_market_data_import_plan (skipped suggested-window check).';
        if ($spanFirst !== null && $spanLast !== null && $spanFirst !== '' && $spanLast !== '') {
            $lines[] = "{$spanLabel}: {$spanFirst} .. {$spanLast}";
        } else {
            $lines[] = "{$spanLabel}: (no MIN/MAX — empty or unavailable)";
        }

        return $lines;
    }
    $ps = $plan['s'];
    $pe = $plan['e'];
    if ($includePlanHeader) {
        $lines[] = 'Suggested import window (plan): ' . ($ps !== '' ? $ps : '—') . ' .. ' . ($pe !== '' ? $pe : '—');
    }

    if ($spanFirst === null || $spanLast === null || $spanFirst === '' || $spanLast === '') {
        $lines[] = "{$spanLabel}: (no MIN/MAX — empty or unavailable)";

        return $lines;
    }

    $lines[] = "{$spanLabel}: {$spanFirst} .. {$spanLast}";

    $missStart = false;
    $missEnd = false;
    $softStartNote = '';
    $softEndNote = '';

    if ($ps !== '' && strcmp($spanFirst, $ps) > 0) {
        $gap = komodo_sec_calendar_day_diff($ps, $spanFirst);
        if ($gap > $slackDays) {
            $missStart = true;
        } else {
            $softStartNote = "first bar is {$gap} calendar day(s) after plan start (within ±{$slackDays}-day slack — OK for weekly/holiday alignment)";
        }
    }

    if ($pe !== '' && strcmp($spanLast, $pe) < 0) {
        $gap = komodo_sec_calendar_day_diff($spanLast, $pe);
        if ($gap > $slackDays) {
            $missEnd = true;
        } else {
            $softEndNote = "last bar is {$gap} calendar day(s) before plan end (within ±{$slackDays}-day slack — OK if extract lags or last bar is last trade day)";
        }
    }

    if ($softStartNote !== '') {
        $lines[] = '  • ' . $softStartNote;
    }
    if ($softEndNote !== '') {
        $lines[] = '  • ' . $softEndNote;
    }

    if (!$missStart && !$missEnd) {
        $lines[] = 'Vs plan: span aligns within rules above (telemetry — still confirm bar frequency: daily vs weekly).';
        $lines[] = $forDryRun
            ? 'Dry-run: no database writes; --execute applies this batch.'
            : 'Import committed; extend CSV if you still need more history after manual review.';

        return $lines;
    }

    $parts = [];
    if ($missStart) {
        $d = $ps !== '' ? (string) komodo_sec_calendar_day_diff($ps, $spanFirst) : '?';
        $parts[] = "first bar is {$d} calendar day(s) after suggested start (beyond ±{$slackDays}-day slack)";
    }
    if ($missEnd) {
        $d = $pe !== '' ? (string) komodo_sec_calendar_day_diff($spanLast, $pe) : '?';
        $parts[] = "last bar is {$d} calendar day(s) before suggested end (beyond ±{$slackDays}-day slack)";
    }
    $lines[] = 'Vs plan: ' . implode('; ', $parts) . '.';
    $lines[] = $forDryRun
        ? 'Dry-run: no rows written yet — fix CSV coverage or plan dates, then --execute.'
        : 'Rows were committed, but the suggested window is not fully covered in calendar terms — add/refresh prices if needed.';

    return $lines;
}

/**
 * @param array<string, list<string>> $buckets
 */
function komodo_sec_determine_outcome(bool $committed, int $rejected, int $warningCount, array $buckets): string
{
    if ($rejected > 0) {
        return $committed ? 'IMPORTED WITH GAPS (some CSV lines rejected)' : 'NOT IMPORTED (rejected rows)';
    }
    if (!$committed) {
        return 'NOT IMPORTED';
    }
    if ($warningCount > 0) {
        if (count($buckets['before_security_start'] ?? []) > 0) {
            return 'IMPORTED — REVIEW (dates before security start_date; check listing window)';
        }

        return 'IMPORTED — REVIEW (see warnings)';
    }

    return 'IMPORTED — CLEAN (no rejects, no parser warnings)';
}

/**
 * @param array<string, list<string>> $buckets
 * @param list<array<string, mixed>> $validRows
 * @param list<array{path: string, headers: list<string>, colMap: array<string, int>, data_rows: int, error: ?string}> $filesMeta
 * @param list<string> $allRejected
 * @param list<string> $allWarnings
 * @param array<string, mixed>|null $postImportRow execute-only summary row for verbose JSON (or null)
 */
function komodo_sec_print_final_verdict(
    int $logLevel,
    bool $verboseWarnings,
    string $dbDisplay,
    bool $dryRun,
    bool $committed,
    string $ticker,
    int $securityId,
    int $batchDistinctDates,
    int $upsertAttempts,
    int $rowCountSum,
    int $totalRowsInTable,
    int $rejected,
    int $warningCount,
    array $buckets,
    ?array $planWindow,
    ?string $barsAfterImportFirst,
    ?string $barsAfterImportLast,
    bool $failOnWarnings,
    ?string $dryCurrentFirst,
    ?string $dryCurrentLast,
    ?string $dryBatchFirst,
    ?string $dryBatchLast,
    int $fileCount,
    array $filesMeta,
    array $validRows,
    int $duplicateOverwrites,
    int $rowsReadTotal,
    string $secName,
    ?int $companyId,
    ?string $startDateStr,
    ?string $endDateStr,
    int $maxRows,
    array $allRejected,
    array $allWarnings,
    ?array $normCfgFull,
    ?array $postImportRow,
    ?PDO $pdoConn,
): int {
    $slack = KOMODO_IMPORT_PLAN_DAY_SLACK;
    $covSpanFirst = $dryRun ? $dryBatchFirst : $barsAfterImportFirst;
    $covSpanLast = $dryRun ? $dryBatchLast : $barsAfterImportLast;
    $covParts = komodo_sec_plan_coverage_parts($planWindow, $covSpanFirst, $covSpanLast, $slack);
    $windowStr = komodo_sec_format_plan_window($planWindow);
    $rangeFirst = $covSpanFirst ?? '';
    $rangeLast = $covSpanLast ?? '';
    $rangeStr = ($rangeFirst !== '' && $rangeLast !== '') ? "{$rangeFirst} → {$rangeLast}" : '—';

    if ($logLevel === KOMODO_SEC_LOG_QUIET) {
        $tag = $dryRun ? 'DRY-RUN' : 'EXECUTE';
        $st = $dryRun
            ? ($rejected > 0 ? 'NOT_READY' : ($warningCount > 0 ? 'REVIEW' : 'OK'))
            : ($committed ? 'COMMITTED' : 'NOT_COMMITTED');
        if ($dryRun) {
            echo "Komodo security {$ticker}: {$tag} {$st} rows={$batchDistinctDates} rej={$rejected} warn={$warningCount}\n";
        } else {
            echo "Komodo security {$ticker}: {$tag} {$st} upserts={$upsertAttempts} db={$totalRowsInTable} rej={$rejected} warn={$warningCount}\n";
        }
        if ($failOnWarnings && $warningCount > 0) {
            fwrite(STDERR, "Exit 2: --fail-on-warnings (warnings present).\n");

            return 2;
        }

        return 0;
    }

    if ($logLevel === KOMODO_SEC_LOG_DEBUG) {
        echo "=== Komodo security CSV import (debug) ===\n";
        if ($dryRun) {
            echo "*** DRY-RUN — no INSERT/UPSERT (add --execute to write) ***\n";
        } else {
            echo "*** EXECUTE MODE — DATABASE WRITES ENABLED ***\n";
        }
        $cid = $companyId !== null ? (string) $companyId : 'null';
        $sdDisp = $startDateStr !== null && $startDateStr !== '' ? $startDateStr : 'null';
        $edDisp = $endDateStr !== null && $endDateStr !== '' ? $endDateStr : 'null';
        echo "Resolved: security_id={$securityId}, ticker_symbol={$ticker}, security_name={$secName}, company_id={$cid}, start_date={$sdDisp}, end_date={$edDisp}\n";
        if ($maxRows > 0) {
            echo "max-rows: {$maxRows}\n";
        }
        echo "Files scanned ({$fileCount}):\n";
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
        echo "\nRows read (non-empty body lines): {$rowsReadTotal}\n";
        echo 'Parsed valid rows (after cross-file dedupe' . ($maxRows > 0 ? ', max-rows applied' : '') . "): {$batchDistinctDates}\n";
        echo "Rejected lines: {$rejected}\n";
        echo "Same-date overwrites (later file wins): {$duplicateOverwrites}\n";
        komodo_sec_emit_warnings_for_level(KOMODO_SEC_LOG_DEBUG, $verboseWarnings, $warningCount, $allWarnings, $buckets);
        if ($allRejected !== []) {
            echo "\nRejected:\n";
            foreach ($allRejected as $r) {
                echo "  - {$r}\n";
            }
        }
        if ($validRows !== []) {
            $first = $validRows[0]['trade_date'];
            $last = $validRows[count($validRows) - 1]['trade_date'];
            echo "\nDate range (valid batch): {$first} .. {$last}\n";
            echo "First 5 normalized rows:\n";
            foreach (array_slice($validRows, 0, 5) as $vr) {
                echo '  ' . json_encode($vr, JSON_THROW_ON_ERROR) . "\n";
            }
        }
        if ($dryRun) {
            echo "\nDB (ticker resolution only; no writes this run):\n";
        } else {
            echo "\nDB connection (config excerpt):\n";
        }
        if (is_array($normCfgFull)) {
            echo '  host: ' . $normCfgFull['host'] . "\n";
            echo '  port: ' . $normCfgFull['port'] . "\n";
            echo '  database: ' . $normCfgFull['database'] . "\n";
            echo '  user: ' . $normCfgFull['username'] . "\n";
        }
        echo "  database (display): {$dbDisplay}\n";
        if ($pdoConn instanceof PDO) {
            try {
                $identStmt = $pdoConn->query('SELECT DATABASE() AS db_name, @@hostname AS server_host');
                if ($identStmt !== false) {
                    $ident = $identStmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($ident)) {
                        echo '  SELECT DATABASE(): ' . ($ident['db_name'] ?? '') . "\n";
                        echo '  @@hostname: ' . ($ident['server_host'] ?? '') . "\n";
                    }
                }
            } catch (Throwable) {
                // ignore
            }
        }
        if ($dryRun) {
            $hasPlan = $planWindow !== null && (($planWindow['s'] ?? '') !== '' || ($planWindow['e'] ?? '') !== '');
            if (!$hasPlan) {
                echo "\n";
                foreach (komodo_sec_plan_vs_span_lines(
                    $planWindow,
                    $dryBatchFirst,
                    $dryBatchLast,
                    'Parsed batch from file(s)',
                    $slack,
                    true,
                    true,
                ) as $ln) {
                    echo $ln . "\n";
                }
            } else {
                echo "\n--- Plan vs existing DB (no writes this run) ---\n";
                foreach (komodo_sec_plan_vs_span_lines(
                    $planWindow,
                    $dryCurrentFirst,
                    $dryCurrentLast,
                    'Existing security_daily_prices',
                    $slack,
                    true,
                    true,
                ) as $ln) {
                    echo $ln . "\n";
                }
                echo "\n--- Plan vs this CSV batch (if you --execute) ---\n";
                foreach (komodo_sec_plan_vs_span_lines(
                    $planWindow,
                    $dryBatchFirst,
                    $dryBatchLast,
                    'Parsed batch from file(s)',
                    $slack,
                    true,
                    false,
                ) as $ln) {
                    echo $ln . "\n";
                }
            }
        } else {
            echo "\n";
            foreach (komodo_sec_plan_vs_span_lines(
                $planWindow,
                $barsAfterImportFirst,
                $barsAfterImportLast,
                'Bars in DB after this commit',
                $slack,
                false,
                true,
            ) as $ln) {
                echo $ln . "\n";
            }
            echo "\n--- Post-import rowCount note ---\n";
            echo "UPSERT statements executed: {$upsertAttempts}\n";
            echo "Sum of PDO rowCount() per statement: {$rowCountSum} (MariaDB: often 1=new row, 2=updated row, 0=no change)\n";
            echo "Table total rows for this security_id: {$totalRowsInTable} (full history in DB, not new rows only for this run).\n";
        }
        echo "\n--- Concise summary ---\n";
    }

    echo "Komodo security import — {$ticker}\n";
    echo 'Mode: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE') . "\n";
    if (!$dryRun) {
        echo 'Status: ' . ($committed ? 'COMMITTED' : 'not committed') . "\n";
    }
    echo "Database: {$dbDisplay}\n";
    echo "Ticker: {$ticker} → security_id={$securityId}\n";
    if ($dryRun) {
        echo "Files: {$fileCount}\n";
    }
    echo "Rows parsed: {$batchDistinctDates}\n";
    echo "Rejected: {$rejected}\n";

    if ($logLevel === KOMODO_SEC_LOG_NORMAL) {
        if ($warningCount === 0) {
            echo "Warnings: 0\n";
        } else {
            komodo_sec_emit_warnings_for_level(KOMODO_SEC_LOG_NORMAL, $verboseWarnings, $warningCount, $allWarnings, $buckets);
        }
    } else {
        echo "Warnings: {$warningCount}\n";
    }

    if (!$dryRun) {
        echo "Upserts: {$upsertAttempts}\n";
        echo "DB total: {$totalRowsInTable}\n";
    }
    echo "Range: {$rangeStr}\n";
    echo "Window: {$windowStr}\n";
    if ($dryRun) {
        $dryOutcome = $rejected > 0 ? 'NOT READY (' . $rejected . ' rejected)' : ($warningCount > 0 ? 'READY — REVIEW WARNINGS FIRST' : 'READY TO EXECUTE');
        $preview = $dryOutcome;
        if ($covParts['but_phrase'] !== '' && $rejected === 0) {
            $preview .= ', but ' . $covParts['but_phrase'];
        }
        echo "Preview: {$preview}\n";
    } else {
        echo 'Coverage: ' . $covParts['coverage_line'] . "\n";
    }
    echo "Next: refresh Price Import Triage / Price Coverage.\n";

    if ($logLevel === KOMODO_SEC_LOG_VERBOSE) {
        echo "\n--- Verbose detail ---\n";
        echo 'Files (' . $fileCount . "):\n";
        foreach ($filesMeta as $fm) {
            echo '  - ' . $fm['path'] . "\n";
            if ($fm['error'] !== null) {
                echo '    ERROR: ' . $fm['error'] . "\n";
                continue;
            }
            echo '    Data rows (non-empty): ' . $fm['data_rows'] . "\n";
        }
        echo "Rows read (body lines): {$rowsReadTotal}\n";
        echo "Same-date overwrites (later file wins): {$duplicateOverwrites}\n";
        if ($maxRows > 0) {
            echo "max-rows cap applied: {$maxRows}\n";
        }
        komodo_sec_emit_warnings_for_level(KOMODO_SEC_LOG_VERBOSE, $verboseWarnings, $warningCount, $allWarnings, $buckets);
        if ($allRejected !== []) {
            echo "\nRejected:\n";
            foreach (array_slice($allRejected, 0, 50) as $r) {
                echo "  - {$r}\n";
            }
            if (count($allRejected) > 50) {
                echo '  ... and ' . (count($allRejected) - 50) . " more\n";
            }
        }
        $slackV = KOMODO_IMPORT_PLAN_DAY_SLACK;
        if ($dryRun) {
            $hasPlan = $planWindow !== null && (($planWindow['s'] ?? '') !== '' || ($planWindow['e'] ?? '') !== '');
            if ($hasPlan) {
                echo "\n--- Plan vs existing DB ---\n";
                foreach (komodo_sec_plan_vs_span_lines(
                    $planWindow,
                    $dryCurrentFirst,
                    $dryCurrentLast,
                    'Existing security_daily_prices',
                    $slackV,
                    true,
                    true,
                ) as $ln) {
                    echo $ln . "\n";
                }
                echo "\n--- Plan vs this CSV batch ---\n";
                foreach (komodo_sec_plan_vs_span_lines(
                    $planWindow,
                    $dryBatchFirst,
                    $dryBatchLast,
                    'Parsed batch from file(s)',
                    $slackV,
                    true,
                    false,
                ) as $ln) {
                    echo $ln . "\n";
                }
            }
        } else {
            echo "\n--- Post-import ---\n";
            echo "UPSERT statements executed: {$upsertAttempts}\n";
            echo "PDO rowCount() sum: {$rowCountSum} — often 1=new row, 2=updated row; sum can exceed batch size when overwriting dates.\n";
            echo "security_daily_prices rows for this security (full history): {$totalRowsInTable}\n";
            if (is_array($postImportRow)) {
                echo 'Post-import ticker summary: ' . json_encode($postImportRow, JSON_THROW_ON_ERROR) . "\n";
            }
            echo "\n--- Plan vs bars after commit ---\n";
            foreach (komodo_sec_plan_vs_span_lines(
                $planWindow,
                $barsAfterImportFirst,
                $barsAfterImportLast,
                'Bars in DB after this commit',
                $slackV,
                false,
                true,
            ) as $ln) {
                echo $ln . "\n";
            }
        }
    }

    if ($failOnWarnings && $warningCount > 0) {
        fwrite(STDERR, "Exit 2: --fail-on-warnings (warnings present).\n");

        return 2;
    }

    return 0;
}

function komodo_sec_display_path(string $path, string $repoRoot): string
{
    $rp = @realpath($repoRoot);
    $pp = @realpath($path);
    $rpn = $rp !== false ? str_replace('\\', '/', $rp) : null;
    $ppn = $pp !== false ? str_replace('\\', '/', $pp) : null;
    if ($rpn !== null && $ppn !== null && str_starts_with($ppn, $rpn . '/')) {
        return substr($ppn, strlen($rpn) + 1);
    }

    return str_replace('\\', '/', $path);
}

/**
 * @param list<string> $paths
 */
function komodo_sec_archive_imported_csvs(array $paths, string $repoRoot, string $stamp): void
{
    if ($paths === []) {
        return;
    }
    $destDir = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'imported' . DIRECTORY_SEPARATOR . 'securities' . DIRECTORY_SEPARATOR . $stamp;
    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) {
        fwrite(STDERR, "Archive: could not create directory: {$destDir}\n");

        return;
    }
    foreach ($paths as $p) {
        if (!is_string($p) || !is_file($p)) {
            continue;
        }
        $target = $destDir . DIRECTORY_SEPARATOR . basename($p);
        if (!@rename($p, $target)) {
            fwrite(STDERR, "Archive warning: could not move {$p} → {$target}\n");
        }
    }
}

/**
 * @param list<string> $csvFiles
 *
 * @return array{silent: bool, ok: bool, exit_code: int, ticker: string, row: ?array<string, mixed>, verdict: ?list<mixed>}
 */
function komodo_sec_import_ticker_run(
    PDO $pdo,
    string $ticker,
    array $csvFiles,
    bool $execute,
    int $maxRows,
    int $logLevel,
    bool $verboseWarnings,
    bool $failOnWarnings,
    bool $silentBatch,
    bool $archiveOnSuccess,
    string $repoRoot,
    ?string $archiveStamp,
): array {
    $sqlResolve = <<<'SQL'
SELECT
    security_id,
    ticker_symbol,
    security_name,
    company_id,
    start_date,
    end_date,
    is_active
FROM securities
WHERE ticker_symbol = :ticker
  AND is_active = 1
ORDER BY start_date DESC, security_id DESC
SQL;

    try {
        $st = $pdo->prepare($sqlResolve);
        $st->execute([':ticker' => $ticker]);
        $candidates = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $msg = 'Ticker resolution failed: ' . $e->getMessage() . "\n";
        if (!$silentBatch) {
            fwrite(STDERR, $msg);
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => 0,
                'rejected' => 0,
                'warnings' => 0,
                'range' => '—',
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'DB resolve error',
            ],
            'verdict' => null,
        ];
    }

    if ($candidates === []) {
        $msg = "No active securities row for ticker \"{$ticker}\" (is_active = 1).\n";
        if (!$silentBatch) {
            fwrite(STDERR, $msg);
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => 0,
                'rejected' => 0,
                'warnings' => 0,
                'range' => '—',
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'No active security for ticker',
            ],
            'verdict' => null,
        ];
    }
    if (count($candidates) > 1) {
        $msg = "Multiple active securities rows for ticker \"{$ticker}\" — resolve manually (company/listing ambiguity).\n";
        if (!$silentBatch) {
            fwrite(STDERR, $msg);
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => 0,
                'rejected' => 0,
                'warnings' => 0,
                'range' => '—',
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'Multiple active securities rows',
            ],
            'verdict' => null,
        ];
    }

    $secRow = $candidates[0];
    $securityId = (int) $secRow['security_id'];
    $secName = (string) ($secRow['security_name'] ?? '');
    $companyId = $secRow['company_id'] !== null ? (int) $secRow['company_id'] : null;
    $startDateStr = isset($secRow['start_date']) && $secRow['start_date'] !== null
        ? (string) $secRow['start_date']
        : null;
    $endDateStr = isset($secRow['end_date']) && $secRow['end_date'] !== null
        ? (string) $secRow['end_date']
        : null;
    if ($startDateStr !== null && preg_match('/^(\d{4}-\d{2}-\d{2})/', $startDateStr, $mm)) {
        $startDateStr = $mm[1];
    }
    if ($endDateStr !== null && preg_match('/^(\d{4}-\d{2}-\d{2})/', $endDateStr, $mm)) {
        $endDateStr = $mm[1];
    }

    $secWindow = ['start_date' => $startDateStr, 'end_date' => $endDateStr];

    $todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');
    $allWarnings = [];
    $allRejected = [];
    $mergedByDate = [];
    $duplicateOverwrites = 0;
    $filesMeta = [];

    /** @var list<array{path: string, rows: list<list<string>>, colMap: array<string, int>, headers: list<string>}> $filePayloads */
    $filePayloads = [];

    foreach ($csvFiles as $csvPath) {
        $rows = komodo_sec_read_csv_rows($csvPath);
        if ($rows === []) {
            $filesMeta[] = ['path' => $csvPath, 'headers' => [], 'colMap' => [], 'data_rows' => 0, 'error' => 'Empty or unreadable'];
            continue;
        }
        ['headers' => $headers, 'colMap' => $colMap] = komodo_sec_map_headers($rows);
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
        if (!$silentBatch) {
            if ($logLevel === KOMODO_SEC_LOG_QUIET) {
                fwrite(STDERR, "Komodo security {$ticker}: FATAL (CSV/header); use default output for paths.\n");
            } else {
                echo "Komodo security import — {$ticker}\n";
                echo 'Mode: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . "\n";
                foreach ($filesMeta as $fm) {
                    if ($fm['error'] !== null) {
                        echo 'ERROR: ' . $fm['error'] . ' — ' . $fm['path'] . "\n";
                    }
                }
                if ($logLevel === KOMODO_SEC_LOG_DEBUG) {
                    foreach ($filesMeta as $fm) {
                        echo '  - ' . $fm['path'] . "\n";
                    }
                }
            }
            fwrite(STDERR, "Aborting: fix CSV headers or paths.\n");
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => 0,
                'rejected' => 0,
                'warnings' => 0,
                'range' => '—',
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'CSV/header fatal',
            ],
            'verdict' => null,
        ];
    }

    foreach ($filePayloads as $payload) {
        $csvPath = $payload['path'];
        $rows = $payload['rows'];
        $colMap = $payload['colMap'];
        if (!isset($colMap['open'])) {
            $allWarnings[] = basename($csvPath) . ': no Open column — open_price will be NULL where applicable';
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
            [$ymd, $norm, $errs, $w] = komodo_sec_build_normalized_row($colMap, $row, $todayYmd, $secWindow);
            foreach ($w as $w1) {
                $allWarnings[] = basename($csvPath) . ' row ' . ($ri + 1) . ": {$w1}";
            }
            if ($ymd === null || $norm === []) {
                foreach ($errs as $e) {
                    $allRejected[] = basename($csvPath) . ' row ' . ($ri + 1) . ": {$e}";
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

    $warnBuckets = komodo_sec_bucket_warnings($allWarnings);

    $rowsReadTotal = 0;
    foreach ($filesMeta as $fm) {
        if ($fm['error'] === null) {
            $rowsReadTotal += $fm['data_rows'];
        }
    }

    if ($validRows === []) {
        if (!$silentBatch) {
            fwrite(STDERR, "\nNo valid rows to import.\n");
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => 0,
                'rejected' => count($allRejected),
                'warnings' => count($allWarnings),
                'range' => '—',
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'No valid rows',
            ],
            'verdict' => null,
        ];
    }

    $localLoaded = komodo_load_local_config();
    $normCfg = is_array($localLoaded) ? komodo_normalize_db_config($localLoaded) : null;
    $dbDisplay = (is_array($normCfg) && ($normCfg['database'] ?? '') !== '') ? (string) $normCfg['database'] : '(unknown)';

    $rangeLo = $validRows[0]['trade_date'] ?? '';
    $rangeHi = $validRows[count($validRows) - 1]['trade_date'] ?? '';
    $rangeBatch = ($rangeLo !== '' && $rangeHi !== '') ? "{$rangeLo}..{$rangeHi}" : '—';

    if (!$execute) {
        $planDry = komodo_sec_fetch_suggested_window($pdo, $ticker);
        [$dryDbFirst, $dryDbLast] = komodo_sec_fetch_price_min_max($pdo, $securityId);
        $batchFirst = $validRows[0]['trade_date'] ?? null;
        $batchLast = $validRows[count($validRows) - 1]['trade_date'] ?? null;
        $verdict = [
            $logLevel, $verboseWarnings, $dbDisplay, true, false, $ticker, $securityId,
            count($validRows), 0, 0, 0,
            count($allRejected), count($allWarnings), $warnBuckets,
            $planDry, null, null, $failOnWarnings,
            $dryDbFirst, $dryDbLast, $batchFirst, $batchLast,
            count($csvFiles), $filesMeta, $validRows, $duplicateOverwrites, $rowsReadTotal,
            $secName, $companyId, $startDateStr, $endDateStr, $maxRows,
            $allRejected, $allWarnings, $normCfg, null, $pdo,
        ];
        if (!$silentBatch) {
            exit(komodo_sec_print_final_verdict(...$verdict));
        }
        $dryLabel = count($allRejected) > 0 ? 'NOT_READY' : 'READY';
        $warnExit = $failOnWarnings && count($allWarnings) > 0;

        return [
            'silent' => true,
            'ok' => true,
            'exit_code' => $warnExit ? 2 : 0,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => $dryLabel,
                'exec_label' => '—',
                'rows_parsed' => count($validRows),
                'rejected' => count($allRejected),
                'warnings' => count($allWarnings),
                'range' => $rangeBatch,
                'upserts' => 0,
                'db_total' => null,
                'reason' => null,
            ],
            'verdict' => $verdict,
        ];
    }

    $upsertSql = <<<'SQL'
INSERT INTO security_daily_prices (
    security_id,
    trade_date,
    open_price,
    high_price,
    low_price,
    close_price,
    adjusted_close,
    volume
) VALUES (
    :security_id,
    :trade_date,
    :open_price,
    :high_price,
    :low_price,
    :close_price,
    :adjusted_close,
    :volume
) ON DUPLICATE KEY UPDATE
    open_price = VALUES(open_price),
    high_price = VALUES(high_price),
    low_price = VALUES(low_price),
    close_price = VALUES(close_price),
    adjusted_close = VALUES(adjusted_close),
    volume = VALUES(volume)
SQL;

    $stmt = $pdo->prepare($upsertSql);
    $attempted = 0;
    $rowCountSum = 0;

    try {
        $pdo->beginTransaction();
        foreach ($validRows as $vr) {
            $attempted++;
            $stmt->execute([
                ':security_id' => $securityId,
                ':trade_date' => $vr['trade_date'],
                ':open_price' => komodo_sec_decimal_param($vr['open_price']),
                ':high_price' => komodo_sec_decimal_param($vr['high_price']),
                ':low_price' => komodo_sec_decimal_param($vr['low_price']),
                ':close_price' => komodo_sec_decimal_param($vr['close_price']),
                ':adjusted_close' => komodo_sec_decimal_param($vr['adjusted_close']),
                ':volume' => $vr['volume'],
            ]);
            $rowCountSum += $stmt->rowCount();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = 'Import failed (rolled back): ' . $e->getMessage() . "\n";
        if (!$silentBatch) {
            fwrite(STDERR, $msg);
            exit(1);
        }

        return [
            'silent' => true,
            'ok' => false,
            'exit_code' => 1,
            'ticker' => $ticker,
            'row' => [
                'ticker' => $ticker,
                'dry_label' => 'FAILED',
                'exec_label' => 'FAILED',
                'rows_parsed' => count($validRows),
                'rejected' => count($allRejected),
                'warnings' => count($allWarnings),
                'range' => $rangeBatch,
                'upserts' => 0,
                'db_total' => null,
                'reason' => 'DB upsert error',
            ],
            'verdict' => null,
        ];
    }

    $cntStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM security_daily_prices WHERE security_id = ?');
    $cntStmt->execute([$securityId]);
    $cntRow = $cntStmt->fetch(PDO::FETCH_ASSOC);
    $afterCount = is_array($cntRow) ? (int) ($cntRow['c'] ?? 0) : 0;

    $postSql = <<<'SQL'
SELECT
    s.ticker_symbol,
    s.security_name,
    COUNT(sdp.trade_date) AS price_rows,
    MIN(sdp.trade_date) AS first_trade_date,
    MAX(sdp.trade_date) AS last_trade_date
FROM securities s
LEFT JOIN security_daily_prices sdp
    ON s.security_id = sdp.security_id
WHERE s.security_id = :security_id
GROUP BY s.security_id, s.ticker_symbol, s.security_name
SQL;
    $post = $pdo->prepare($postSql);
    $post->execute([':security_id' => $securityId]);
    $postRow = $post->fetch(PDO::FETCH_ASSOC);
    $dbFirstBar = is_array($postRow) ? komodo_sec_sql_date_prefix($postRow['first_trade_date'] ?? null) : null;
    $dbLastBar = is_array($postRow) ? komodo_sec_sql_date_prefix($postRow['last_trade_date'] ?? null) : null;
    $planLive = komodo_sec_fetch_suggested_window($pdo, $ticker);

    if ($silentBatch && $archiveOnSuccess && $archiveStamp !== null && $archiveStamp !== '') {
        komodo_sec_archive_imported_csvs($csvFiles, $repoRoot, $archiveStamp);
    }

    $verdict = [
        $logLevel, $verboseWarnings, $dbDisplay, false, true, $ticker, $securityId,
        count($validRows), $attempted, $rowCountSum, $afterCount,
        count($allRejected), count($allWarnings), $warnBuckets,
        $planLive, $dbFirstBar, $dbLastBar, $failOnWarnings,
        null, null, null, null,
        count($csvFiles), $filesMeta, $validRows, $duplicateOverwrites, $rowsReadTotal,
        $secName, $companyId, $startDateStr, $endDateStr, $maxRows,
        $allRejected, $allWarnings, $normCfg, is_array($postRow) ? $postRow : null, $pdo,
    ];
    if (!$silentBatch) {
        if ($archiveOnSuccess && $archiveStamp !== null && $archiveStamp !== '') {
            komodo_sec_archive_imported_csvs($csvFiles, $repoRoot, $archiveStamp);
        }
        exit(komodo_sec_print_final_verdict(...$verdict));
    }
    $warnExit = $failOnWarnings && count($allWarnings) > 0;

    return [
        'silent' => true,
        'ok' => true,
        'exit_code' => $warnExit ? 2 : 0,
        'ticker' => $ticker,
        'row' => [
            'ticker' => $ticker,
            'dry_label' => 'READY',
            'exec_label' => 'COMMITTED',
            'rows_parsed' => count($validRows),
            'rejected' => count($allRejected),
            'warnings' => count($allWarnings),
            'range' => $rangeBatch,
            'upserts' => $attempted,
            'db_total' => $afterCount,
            'reason' => null,
        ],
        'verdict' => $verdict,
    ];
}

// --- CLI runner -------------------------------------------------------

$pdo = get_pdo();
if ($pdo === null) {
    fwrite(STDERR, "Database not available. Configure app/config/local.php and ensure MariaDB is running.\n");
    exit(1);
}

if ($batchMode) {
    if (isset($opts['tickers'])) {
        $tv = $opts['tickers'];
        if ($tv === true || $tv === '') {
            fwrite(STDERR, "Invalid --tickers: use --tickers=A,B,C\n");
            exit(1);
        }
    }
    $onlyOrdered = ($tickersOpt !== null && $tickersOpt !== '') ? $batchTickerOrder : null;
    $disc = komodo_sec_discover_ticker_groups((string) $dir, $onlyOrdered, $logLevel);
    $groups = $disc['groups'];
    $skipped = $disc['skipped'];
    if ($groups === []) {
        exit(1);
    }

    $archiveStamp = ($archiveOnSuccess && $execute) ? (new DateTimeImmutable('now'))->format('Ymd_His') : null;
    $dirDisp = komodo_sec_display_path((string) $dir, $repoRoot);

    $results = [];
    foreach ($groups as $g) {
        $sym = $g['ticker'];
        $paths = $g['paths'];
        if ($paths === []) {
            $results[] = [
                'silent' => true,
                'ok' => false,
                'exit_code' => 1,
                'ticker' => $sym,
                'row' => [
                    'ticker' => $sym,
                    'dry_label' => 'FAILED',
                    'exec_label' => 'FAILED',
                    'rows_parsed' => 0,
                    'rejected' => 0,
                    'warnings' => 0,
                    'range' => '—',
                    'upserts' => 0,
                    'db_total' => null,
                    'reason' => 'No CSV files',
                ],
                'verdict' => null,
            ];
            if ($failFast) {
                break;
            }

            continue;
        }

        $r = komodo_sec_import_ticker_run(
            $pdo,
            $sym,
            $paths,
            $execute,
            $maxRows,
            $logLevel,
            $verboseWarnings,
            $failOnWarnings,
            true,
            $archiveOnSuccess,
            $repoRoot,
            $archiveStamp,
        );
        $results[] = $r;
        if ($failFast && !$r['ok']) {
            break;
        }
    }

    $nTickers = count($groups);
    $failed = 0;
    $committed = 0;
    $ready = 0;
    $notReady = 0;
    $warnTickers = 0;
    $rejectedSum = 0;
    $warnSum = 0;
    /** @var list<string> $failedTickers */
    $failedTickers = [];
    foreach ($results as $r) {
        $row = $r['row'];
        if ($row === null) {
            continue;
        }
        if (!$r['ok']) {
            $failed++;
            $failedTickers[] = (string) ($row['ticker'] ?? $r['ticker']);
        } elseif ($execute) {
            if (($row['exec_label'] ?? '') === 'COMMITTED') {
                $committed++;
            }
        } else {
            if (($row['dry_label'] ?? '') === 'READY') {
                $ready++;
            } elseif (($row['dry_label'] ?? '') === 'NOT_READY') {
                $notReady++;
            }
        }
        $rej = (int) ($row['rejected'] ?? 0);
        $war = (int) ($row['warnings'] ?? 0);
        $rejectedSum += $rej;
        $warnSum += $war;
        if ($war > 0 && $r['ok']) {
            $warnTickers++;
        }
    }

    if ($logLevel === KOMODO_SEC_LOG_QUIET) {
        echo 'Komodo security import batch: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . " dir={$dirDisp} tickers={$nTickers} failed={$failed}";
        if ($skipped !== []) {
            echo ' skipped_files=' . count($skipped);
        }
        echo "\n";
    } else {
        echo "Komodo security import batch\n";
        echo 'Mode: ' . ($execute ? 'EXECUTE' : 'DRY-RUN') . "\n";
        echo "Directory: {$dirDisp}\n";
        echo "Tickers detected: {$nTickers}\n";
        if ($skipped !== [] && $logLevel >= KOMODO_SEC_LOG_VERBOSE) {
            echo 'Non-matching CSV filenames skipped: ' . count($skipped) . "\n";
        }
        echo "\n";

        if ($execute) {
            echo str_pad('Ticker', 8) . str_pad('Result', 12) . str_pad('Rows parsed', 12) . str_pad('Upserts', 10) . str_pad('Rejected', 10) . str_pad('Warnings', 10) . str_pad('DB total', 10) . "Range\n";
        } else {
            echo str_pad('Ticker', 8) . str_pad('Status', 12) . str_pad('Rows', 8) . str_pad('Rejected', 10) . str_pad('Warnings', 10) . "Range\n";
        }
        foreach ($results as $r) {
            $row = $r['row'] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $t = (string) $row['ticker'];
            $range = (string) $row['range'];
            $rp = (int) $row['rows_parsed'];
            $rej = (int) $row['rejected'];
            $war = (int) $row['warnings'];
            if ($execute) {
                $lab = (string) $row['exec_label'];
                $up = (int) $row['upserts'];
                $dbt = $row['db_total'];
                $dbS = $dbt === null ? '—' : (string) $dbt;
                echo str_pad($t, 8) . str_pad($lab, 12) . str_pad((string) $rp, 12) . str_pad((string) $up, 10) . str_pad((string) $rej, 10) . str_pad((string) $war, 10) . str_pad($dbS, 10) . $range . "\n";
            } else {
                $lab = (string) $row['dry_label'];
                echo str_pad($t, 8) . str_pad($lab, 12) . str_pad((string) $rp, 8) . str_pad((string) $rej, 10) . str_pad((string) $war, 10) . $range . "\n";
            }
        }

        echo "\nSummary:\n";
        if ($execute) {
            echo "Committed: {$committed}\n";
            echo "Failed: {$failed}\n";
            if ($failedTickers !== []) {
                echo 'Failed tickers: ' . implode(', ', $failedTickers) . "\n";
            }
            echo "Rejected rows: {$rejectedSum}\n";
            echo "Warnings: {$warnSum}\n";
            try {
                $tc = $pdo->query('SELECT COUNT(*) AS c FROM security_daily_prices');
                $tr = $tc !== false ? $tc->fetch(PDO::FETCH_ASSOC) : null;
                $tot = is_array($tr) ? (int) ($tr['c'] ?? 0) : 0;
                echo "Total DB rows after batch: {$tot}\n";
            } catch (Throwable) {
                echo "Total DB rows after batch: (unavailable)\n";
            }
        } else {
            echo "Ready: {$ready}\n";
            if ($notReady > 0) {
                echo "Not ready (rejected rows): {$notReady}\n";
            }
            echo "Warnings: {$warnTickers} tickers / {$warnSum} lines\n";
            echo "Failed: {$failed}\n";
            if ($failedTickers !== []) {
                echo 'Failed tickers: ' . implode(', ', $failedTickers) . "\n";
            }
            if ($skipped !== []) {
                echo 'Non-matching CSV filenames skipped: ' . count($skipped) . "\n";
            }
            echo "\nNo writes. Re-run with --execute to import.\n";
        }
    }

    if ($logLevel >= KOMODO_SEC_LOG_VERBOSE) {
        foreach ($results as $r) {
            if (!is_array($r['verdict'] ?? null)) {
                continue;
            }
            /** @var list<mixed> $v */
            $v = $r['verdict'];
            echo "\n=== " . $r['ticker'] . " ===\n";
            komodo_sec_print_final_verdict(...$v);
        }
    }

    $code = 0;
    foreach ($results as $r) {
        if (!$r['ok']) {
            $code = 1;
            break;
        }
    }
    if ($code === 0 && $failOnWarnings) {
        foreach ($results as $r) {
            if (($r['exit_code'] ?? 0) === 2) {
                $code = 2;
                break;
            }
        }
    }
    if ($code === 2) {
        fwrite(STDERR, "Exit 2: --fail-on-warnings (one or more tickers had warnings).\n");
    }
    exit($code);
}

$singleArchiveStamp = ($archiveOnSuccess && $execute) ? (new DateTimeImmutable('now'))->format('Ymd_His') : null;
komodo_sec_import_ticker_run(
    $pdo,
    (string) $ticker,
    $csvFiles,
    $execute,
    $maxRows,
    $logLevel,
    $verboseWarnings,
    $failOnWarnings,
    false,
    $archiveOnSuccess,
    $repoRoot,
    $singleArchiveStamp,
);

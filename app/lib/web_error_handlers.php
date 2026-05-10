<?php

declare(strict_types=1);

/**
 * Web entry error handling for public/index.php.
 * Logs server-side; responds with a minimal HTML 500 unless debug is enabled.
 *
 * Static fallback (no PHP): public/fallback.html — use when the app crashes hard.
 */

function komodo_web_error_escape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function komodo_web_show_error_details(): bool
{
    $env = getenv('KOMODO_DEBUG');
    if (is_string($env) && $env !== '') {
        $v = strtolower(trim($env));
        if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
    }

    $disp = ini_get('display_errors');
    if (!is_string($disp) || $disp === '') {
        return false;
    }
    $disp = strtolower(trim($disp));

    return in_array($disp, ['1', 'on', 'true', 'yes', 'stdout', 'stderr'], true);
}

function komodo_web_clear_output_buffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/**
 * @param array{type: int, message: string, file: string, line: int}|null $last
 */
function komodo_web_render_error_page(int $httpStatus, string $logPrefix, ?Throwable $ex = null, ?array $last = null): void
{
    komodo_web_clear_output_buffers();

    if (!headers_sent()) {
        http_response_code($httpStatus);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    $show = komodo_web_show_error_details();
    $summary = 'Something went wrong while loading this page.';
    $detail = '';

    if ($ex !== null) {
        error_log($logPrefix . ' ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        if ($show) {
            $detail = $ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine();
        }
    } elseif ($last !== null) {
        error_log($logPrefix . ' ' . $last['message'] . ' in ' . $last['file'] . ':' . $last['line']);
        if ($show) {
            $detail = $last['message'] . "\n" . $last['file'] . ':' . $last['line'];
        }
    } else {
        error_log($logPrefix . ' (no details)');
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#16151b">';
    echo '<title>Komodo — error</title>';
    echo '<style>body{font-family:system-ui,Segoe UI,sans-serif;margin:2rem;background:#0f0e12;color:#e8e6ef;}';
    echo 'pre{white-space:pre-wrap;background:#16151b;padding:1rem;border-radius:6px;font-size:0.875rem;}';
    echo 'a{color:#8ab4ff;}</style></head><body>';
    echo '<h1>Application error</h1><p>' . komodo_web_error_escape($summary) . '</p>';
    if ($show && $detail !== '') {
        echo '<pre>' . komodo_web_error_escape($detail) . '</pre>';
    } else {
        echo '<p>If this keeps happening, check the server error log.</p>';
    }
    echo '<p><a href="?page=dashboard">Try the app again</a> · <a href="fallback.html">Static help page</a> (works without PHP)</p>';
    echo '</body></html>';
}

function komodo_register_web_error_handlers(): void
{
    set_exception_handler(static function (Throwable $e): void {
        komodo_web_render_error_page(500, 'Komodo: uncaught exception', $e, null);
    });

    register_shutdown_function(static function (): void {
        $last = error_get_last();
        if ($last === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($last['type'], $fatalTypes, true)) {
            return;
        }
        komodo_web_render_error_page(500, 'Komodo: fatal error', null, $last);
    });
}

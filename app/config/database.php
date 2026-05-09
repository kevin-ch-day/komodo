<?php

declare(strict_types=1);

/**
 * PDO factory for Komodo. Read-only consumer; credentials live in local.php only.
 */

/**
 * Structured database status for the UI. Cached once per request.
 *
 * @return array{pdo: ?PDO, status: string, message: string}
 */
function komodo_get_database_status(): array
{
    static $cached = null;
    if ($cached !== null) {
        /** @var array{pdo: ?PDO, status: string, message: string} $cached */
        return $cached;
    }

    $path = __DIR__ . '/local.php';

    if (!is_readable($path)) {
        $cached = [
            'pdo' => null,
            'status' => 'not_configured',
            'message' => 'Database connection not configured.',
        ];
        return $cached;
    }

    /** @var mixed $loaded */
    $loaded = require $path;
    if (!is_array($loaded)) {
        error_log('Komodo: database config returned invalid contents.');
        $cached = [
            'pdo' => null,
            'status' => 'misconfigured',
            'message' => 'Database configuration is invalid.',
        ];
        return $cached;
    }

    $config = komodo_normalize_db_config($loaded);
    if ($config === null) {
        error_log('Komodo: database config failed validation.');
        $cached = [
            'pdo' => null,
            'status' => 'misconfigured',
            'message' => 'Database configuration is invalid.',
        ];
        return $cached;
    }

    $charset = $config['charset'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
        error_log('Komodo: charset validation failed.');
        $cached = [
            'pdo' => null,
            'status' => 'misconfigured',
            'message' => 'Database configuration is invalid.',
        ];
        return $cached;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $charset
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        $cached = [
            'pdo' => $pdo,
            'status' => 'connected',
            'message' => 'Live data from database.',
        ];
        return $cached;
    } catch (PDOException $e) {
        error_log('Komodo: PDO connection failed.');
        $cached = [
            'pdo' => null,
            'status' => 'unreachable',
            'message' => 'Database unavailable.',
        ];
        return $cached;
    }
}

/**
 * @return array<string, mixed>|null
 */
function komodo_load_local_config(): ?array
{
    $path = __DIR__ . '/local.php';
    if (!is_readable($path)) {
        return null;
    }

    /** @var mixed $loaded */
    $loaded = require $path;
    if (!is_array($loaded)) {
        return null;
    }

    return $loaded;
}

/**
 * @param array<string, mixed> $config
 */
function komodo_normalize_db_config(array $config): ?array
{
    $keys = ['host', 'port', 'database', 'username', 'password', 'charset'];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $config)) {
            return null;
        }
    }

    $host = $config['host'];
    $database = $config['database'];
    $username = $config['username'];
    $password = $config['password'];
    $charset = $config['charset'];
    $port = $config['port'];

    if (!is_string($host) || $host === '') {
        return null;
    }
    if (!is_string($database) || $database === '') {
        return null;
    }
    if (!is_string($username)) {
        return null;
    }
    if (!is_string($password)) {
        return null;
    }
    if (!is_string($charset) || $charset === '') {
        return null;
    }

    $portNum = is_int($port) ? $port : filter_var($port, FILTER_VALIDATE_INT);
    if ($portNum === false || $portNum < 1 || $portNum > 65535) {
        return null;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
        return null;
    }

    if (strpos($host, ';') !== false || strpos($username, ';') !== false) {
        return null;
    }

    return [
        'host' => $host,
        'port' => $portNum,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => $charset,
    ];
}

/**
 * Returns PDO from the cached status probe, or null when not connected.
 */
function get_pdo(): ?PDO
{
    return komodo_get_database_status()['pdo'];
}

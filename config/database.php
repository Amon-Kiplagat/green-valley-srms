<?php

declare(strict_types=1);

// Load .env file once, populate getenv() and $_ENV.
function load_env(string $path): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $value = preg_split('/\s+#/', $value)[0];
            $value = trim($value);
        }
        if (strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

// Singleton PDO connection. Reads DATABASE_URL or DB_* env vars.
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    load_env(dirname(__DIR__) . '/.env');

    $url = env('DATABASE_URL');
    if ($url !== null) {
        $u    = parse_url($url);
        $host = $u['host'];
        $port = (string)($u['port'] ?? 5432);
        $name = ltrim($u['path'], '/');
        $user = urldecode($u['user']);
        $pass = urldecode($u['pass'] ?? '');
        $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $host, $port, $name);
    } else {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '5432');
        $name = env('DB_NAME', 'green_valley');
        $user = env('DB_USER', 'postgres');
        $pass = env('DB_PASS', '');
        $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if (env('APP_ENV', 'production') === 'development') {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        http_response_code(500);
        die('A database error occurred. Please contact the administrator.');
    }

    return $pdo;
}
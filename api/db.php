<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'cancer_app';
const DB_USER = 'root';
const DB_PASS = '';

const LOGIN_RATE_LIMIT_FILE = 'cancer_app_login_rate_limit.json';
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_ATTEMPT_WINDOW_SECONDS = 300;
const LOGIN_LOCK_SECONDS = 600;

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
        jsonResponse(415, [
            'success' => false,
            'message' => 'Content-Type は application/json を指定してください。',
        ]);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    if (strlen($raw) > 10000) {
        jsonResponse(413, [
            'success' => false,
            'message' => 'リクエストサイズが大きすぎます。',
        ]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, [
            'success' => false,
            'message' => 'JSONの形式が不正です。',
        ]);
    }

    return $decoded;
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function enforceSameOriginForStateChange(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    if (!is_string($originHost) || $originHost === '') {
        jsonResponse(403, [
            'success' => false,
            'message' => '不正なオリジンです。',
        ]);
    }

    $requestHostHeader = $_SERVER['HTTP_HOST'] ?? '';
    if ($requestHostHeader === '') {
        return;
    }

    $requestHost = explode(':', $requestHostHeader)[0];
    if (strtolower($originHost) !== strtolower($requestHost)) {
        jsonResponse(403, [
            'success' => false,
            'message' => '許可されていないオリジンです。',
        ]);
    }
}

function buildLoginRateLimitKey(string $username): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', strtolower($username) . '|' . $ip);
}

function checkLoginRateLimit(string $key): array
{
    $now = time();
    $fp = fopen(getLoginRateLimitFilePath(), 'c+');
    if ($fp === false) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $state = readRateLimitState($fp);
        $entry = $state[$key] ?? ['attempts' => [], 'lock_until' => 0];

        $entry['attempts'] = array_values(array_filter(
            is_array($entry['attempts']) ? $entry['attempts'] : [],
            static fn ($ts): bool => is_int($ts) && $ts > ($now - LOGIN_ATTEMPT_WINDOW_SECONDS)
        ));

        $lockUntil = (int) ($entry['lock_until'] ?? 0);
        if ($lockUntil > $now) {
            $state[$key] = $entry;
            writeRateLimitState($fp, $state);
            return ['allowed' => false, 'retry_after' => $lockUntil - $now];
        }

        if (count($entry['attempts']) >= LOGIN_MAX_ATTEMPTS) {
            $entry['attempts'] = [];
            $entry['lock_until'] = $now + LOGIN_LOCK_SECONDS;
            $state[$key] = $entry;
            writeRateLimitState($fp, $state);
            return ['allowed' => false, 'retry_after' => LOGIN_LOCK_SECONDS];
        }

        $entry['lock_until'] = 0;
        $state[$key] = $entry;
        writeRateLimitState($fp, $state);
        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function registerLoginFailure(string $key): void
{
    $now = time();
    $fp = fopen(getLoginRateLimitFilePath(), 'c+');
    if ($fp === false) {
        return;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return;
        }

        $state = readRateLimitState($fp);
        $entry = $state[$key] ?? ['attempts' => [], 'lock_until' => 0];
        $attempts = is_array($entry['attempts']) ? $entry['attempts'] : [];
        $attempts = array_values(array_filter(
            $attempts,
            static fn ($ts): bool => is_int($ts) && $ts > ($now - LOGIN_ATTEMPT_WINDOW_SECONDS)
        ));
        $attempts[] = $now;

        if (count($attempts) >= LOGIN_MAX_ATTEMPTS) {
            $entry['attempts'] = [];
            $entry['lock_until'] = $now + LOGIN_LOCK_SECONDS;
        } else {
            $entry['attempts'] = $attempts;
            $entry['lock_until'] = 0;
        }

        $state[$key] = $entry;
        writeRateLimitState($fp, $state);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function clearLoginFailures(string $key): void
{
    $fp = fopen(getLoginRateLimitFilePath(), 'c+');
    if ($fp === false) {
        return;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return;
        }

        $state = readRateLimitState($fp);
        unset($state[$key]);
        writeRateLimitState($fp, $state);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function getPasswordVerifyDummyHash(): string
{
    static $hash = null;
    if (!is_string($hash)) {
        $hash = password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
    }

    return $hash;
}

function getLoginRateLimitFilePath(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . LOGIN_RATE_LIMIT_FILE;
}

function readRateLimitState($fp): array
{
    rewind($fp);
    $content = stream_get_contents($fp);
    if (!is_string($content) || $content === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function writeRateLimitState($fp, array $state): void
{
    // 期限切れロック情報を落としてファイル肥大化を防ぐ。
    $now = time();
    foreach ($state as $key => $entry) {
        $lockUntil = (int) ($entry['lock_until'] ?? 0);
        $attempts = $entry['attempts'] ?? [];
        if ($lockUntil <= $now && (!is_array($attempts) || count($attempts) === 0)) {
            unset($state[$key]);
        }
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) json_encode($state, JSON_UNESCAPED_UNICODE));
    fflush($fp);
}

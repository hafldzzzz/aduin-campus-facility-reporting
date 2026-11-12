<?php
// Shared bootstrap for web requests and the PHP CLI. No framework is required.
function appConfig(string $key, $default = null)
{
    static $local;
    if ($local === null) {
        $file = getenv('ADUIN_CONFIG_FILE') ?: dirname(__DIR__) . '/config/local.php';
        $local = is_file($file) ? require $file : [];
        if (!is_array($local)) {
            throw new RuntimeException('Invalid private configuration.');
        }
    }
    $value = getenv($key);
    return $value !== false ? $value : ($local[$key] ?? $default);
}

function appBool(string $key, bool $default = false): bool
{
    return filter_var(appConfig($key, $default), FILTER_VALIDATE_BOOLEAN);
}

function safeLog(string $event, array $context = []): void
{
    // Never pass exception messages, SQL, payloads, tokens or phone numbers here.
    $safe = array_intersect_key($context, array_flip(['code', 'user_id', 'pengaduan_id', 'job_id', 'attempts', 'status', 'http_status', 'severity', 'count']));
    foreach ($safe as $key => $value) {
        $safe[$key] = is_numeric($value) ? (int) $value : preg_replace('/[^a-zA-Z0-9_-]/', '', substr((string) $value, 0, 40));
    }
    error_log('[ADUIN] ' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $event) . ' ' . json_encode($safe));
}

function appBasePath(): string
{
    $configured = appConfig('APP_BASE_URL', '');
    if ($configured !== '') {
        return rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
    }
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = preg_replace('~/(admin|user|petugas|bin)$~', '', $directory);
    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function appPath(string $path): string
{
    return appBasePath() . '/' . ltrim($path, '/');
}

function isHttps(): bool
{
    // Proxy must set HTTPS itself; arbitrary forwarded headers are untrusted.
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function cspNonce(): string
{
    static $nonce;
    return $nonce ??= base64_encode(random_bytes(24));
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');
set_exception_handler(function (Throwable $exception): void {
    safeLog('request.exception', ['code' => $exception->getCode()]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Operasi gagal. Periksa konfigurasi dan log server yang disanitasi.\n");
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Terjadi kesalahan pada sistem. Silakan coba lagi.';
});
set_error_handler(function (int $severity): bool {
    if (error_reporting() & $severity) {
        safeLog('php.warning', ['severity' => $severity]);
    }
    return true;
});

require_once __DIR__ . '/security.php';

if (PHP_SAPI !== 'cli') {
    ob_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . cspNonce()
        . "' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    } elseif (appBool('APP_FORCE_HTTPS')) {
        $base = (string) appConfig('APP_BASE_URL', '');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https' || !parse_url($base, PHP_URL_HOST)) {
            http_response_code(503);
            exit('Konfigurasi HTTPS belum lengkap.');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            http_response_code(400);
            exit('Gunakan koneksi HTTPS.');
        }
        $origin = 'https://' . parse_url($base, PHP_URL_HOST);
        if ($port = parse_url($base, PHP_URL_PORT)) { $origin .= ':' . $port; }
        $target = $_SERVER['REQUEST_URI'] ?? appBasePath() . '/';
        if (!str_starts_with($target, '/') || preg_match('/[\r\n\\\\]/', $target)) {
            $target = appBasePath() . '/';
        }
        header('Location: ' . $origin . '/' . ltrim($target, '/'), true, 302);
        exit;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    session_name('ADUINSESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => appBasePath() . '/', 'secure' => isHttps(),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    $idle = max(300, (int) appConfig('SESSION_IDLE_TIMEOUT', 1800));
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle) {
        clearSession();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

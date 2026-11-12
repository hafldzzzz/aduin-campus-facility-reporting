<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Public portfolio copy: configuration is private and never tied to a hosting account.
$host = trim((string) appConfig('DB_HOST', ''));
$name = trim((string) appConfig('DB_NAME', ''));
$user = trim((string) appConfig('DB_USER', ''));
if ($host === '' || $name === '' || $user === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Konfigurasi belum tersedia. Salin config/example.local.php menjadi config/local.php dan ikuti README.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, (string) appConfig('DB_PASS', ''), $name, (int) appConfig('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");
$conn->query("SET SESSION sql_mode = IF(FIND_IN_SET('STRICT_TRANS_TABLES', @@SESSION.sql_mode), @@SESSION.sql_mode, CONCAT_WS(',', @@SESSION.sql_mode, 'STRICT_TRANS_TABLES'))");

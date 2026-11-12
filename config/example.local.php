<?php
// Template publik. Salin ke local.php; masukkan kredensial database LOKAL sendiri.
return [
    'APP_ENV' => 'local',
    'APP_BASE_URL' => 'http://aduin.test',
    'APP_FORCE_HTTPS' => false,
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'aduin_demo',
    'DB_USER' => '',
    'DB_PASS' => '',
    'SESSION_IDLE_TIMEOUT' => 1800,
    'RATE_LIMIT_DIR' => dirname(__DIR__) . '/storage/rate-limits',
    'WA_ENABLED' => false,
];

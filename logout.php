<?php
require_once __DIR__ . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    clearSession();
    header('Location: login.php', true, 303);
    exit;
}
// Existing logout URL remains valid; GET never changes authentication state.
header('Location: ' . (currentUser() ? 'pengaduan.php' : 'login.php'));
exit;

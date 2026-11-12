<?php
require_once __DIR__ . '/config/database.php';
$user = requireLogin();
$kind = getString('kind');
$file = getString('file');
if (!in_array($kind, ['pengaduan','perbaikan'], true) || uploadUrl($file, $kind, '') === '') {
    http_response_code(404);
    exit;
}
$params = [$file];
$types = 's';
$sql = $kind === 'pengaduan'
    ? 'SELECT p.id_pengaduan FROM pengaduan p WHERE p.foto = ?'
    : 'SELECT p.id_pengaduan FROM pengaduan p INNER JOIN penanganan h ON h.id_pengaduan = p.id_pengaduan WHERE h.foto_perbaikan = ?';
if ($user['role'] === 'pengguna') {
    $sql .= ' AND p.id_user = ?';
    $types .= 'i';
    $params[] = $user['id_user'];
} elseif ($user['role'] === 'petugas') {
    $sql .= ' AND p.id_petugas = ?';
    $types .= 'i';
    $params[] = $user['id_user'];
}
if (!dbOne($conn, $sql . ' LIMIT 1', $types, $params)) {
    forbid();
}
$directory = realpath(__DIR__ . '/uploads/' . $kind);
$path = realpath(__DIR__ . '/uploads/' . $kind . '/' . $file);
if (!$directory || !$path || strncmp($path, $directory . DIRECTORY_SEPARATOR, strlen($directory . DIRECTORY_SEPARATOR)) !== 0) {
    http_response_code(404);
    exit;
}
$mime = is_file($path) ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : '';
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="photo.' . ($mime === 'image/jpeg' ? 'jpg' : substr($mime, 6)) . '"');
readfile($path);

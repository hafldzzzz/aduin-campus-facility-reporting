<?php
require_once __DIR__ . '/config/database.php';
$user = requireLogin();
$destination = ['pengguna'=>'user/buat-pengaduan.php','admin'=>'admin/pengaduan.php','petugas'=>'petugas/tugas.php'];
header('Location: ' . $destination[$user['role']]);
exit;

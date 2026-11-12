<?php
declare(strict_types=1);
// Only new synthetic data. This script never reads an old database or SQL dump.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!in_array(appConfig('APP_ENV'), ['local', 'test'], true)
    || !in_array(strtolower((string) appConfig('DB_HOST')), ['localhost', '127.0.0.1', '::1'], true)
    || !preg_match('/\Aaduin_(?:demo|test_[a-z0-9_]+)\z/D', (string) appConfig('DB_NAME'))
    || appBool('WA_ENABLED')) {
    fwrite(STDERR, "Demo ditolak: gunakan lingkungan lokal, database demo baru, dan WhatsApp nonaktif.\n"); exit(1);
}
// Validate before opening any database connection, including an accidental remote config.
require_once dirname(__DIR__) . '/config/database.php';
$tables = ['users', 'kategori', 'pengaduan', 'riwayat_status', 'penanganan',
    'complaint_submission_requests', 'whatsapp_admin_optins', 'whatsapp_notification_outbox'];
$existingTables = array_column($conn->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
if ($conn->query('SELECT DATABASE()')->fetch_row()[0] !== appConfig('DB_NAME')) {
    fwrite(STDERR, "Demo ditolak: database koneksi tidak cocok.\n"); exit(1);
}
sort($existingTables); $expectedTables = $tables; sort($expectedTables);
if ($existingTables !== $expectedTables) {
    fwrite(STDERR, "Demo ditolak: struktur harus persis berasal dari database/schema.sql.\n"); exit(1);
}
foreach ($tables as $table) {
    if ((int) $conn->query('SELECT COUNT(*) FROM `' . $table . '`')->fetch_row()[0] !== 0) {
        fwrite(STDERR, "Demo ditolak: seluruh tabel harus kosong. Tidak ada data yang diganti.\n"); exit(1);
    }
}
$accounts = [
    ['Admin Demo', 'admin@example.invalid', 'admin', 'Unit Demo'],
    ['Mahasiswa Demo', 'mahasiswa@example.invalid', 'pengguna', 'Fakultas Demo'],
    ['Petugas Demo', 'petugas@example.invalid', 'petugas', 'Unit Fasilitas Demo'],
];
$credentials = [];
$accountIds = [];
$categoryIds = [];
$conn->begin_transaction();
try {
    foreach ($accounts as [$name, $email, $role, $faculty]) {
        $password = bin2hex(random_bytes(12));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbStatement($conn, 'INSERT INTO users (nama,email,password,no_wa,role,fakultas) VALUES (?,?,?,NULL,?,?)', 'sssss', [$name,$email,$hash,$role,$faculty]);
        $accountIds[$role] = (int) $conn->insert_id;
        $credentials[] = [$email, $password];
    }
    foreach (['Kelistrikan', 'Sanitasi', 'Ruang Kuliah', 'Jaringan Internet'] as $category) {
        dbStatement($conn, 'INSERT INTO kategori (nama_kategori) VALUES (?)', 's', [$category]);
        $categoryIds[] = (int) $conn->insert_id;
    }
    $month = (new DateTimeImmutable('first day of this month'))->format('Y-m');
    $scenarios = [
        [1, 'Ruang Simulasi A', 'Lampu pada ruang simulasi perlu diperiksa.', 'Menunggu', null],
        [2, 'Gedung Simulasi B', 'Keran pada fasilitas simulasi perlu diperbaiki.', 'Diproses', 3],
        [3, 'Kelas Simulasi C', 'Kursi simulasi telah diperbaiki oleh petugas demo.', 'Selesai', 3],
        [4, 'Lab Simulasi D', 'Sinyal jaringan pada lab simulasi perlu diperiksa.', 'Menunggu', 3],
        [1, 'Aula Simulasi E', 'Stopkontak simulasi sedang diperiksa petugas demo.', 'Diproses', 3],
        [2, 'Taman Simulasi F', 'Saluran air simulasi selesai dibersihkan.', 'Selesai', 3],
    ];
    foreach ($scenarios as $index => [$category, $location, $description, $status, $officer]) {
        $category = $categoryIds[$category - 1];
        $officer = $officer === null ? null : $accountIds['petugas'];
        $number = sprintf('DEMO-%s-%03d', str_replace('-', '', $month), $index + 1);
        $date = $month . '-01 ' . sprintf('%02d:00:00', 8 + $index);
        dbStatement($conn, 'INSERT INTO pengaduan (no_laporan,id_user,id_kategori,id_petugas,lokasi,deskripsi,status,tanggal_lapor) VALUES (?,?,?,?,?,?,?,?)', 'siiissss', [$number,$accountIds['pengguna'],$category,$officer,$location,$description,$status,$date]);
        $id = (int) $conn->insert_id;
        $history = ['Menunggu'];
        if ($status !== 'Menunggu') { $history[] = 'Diproses'; }
        if ($status === 'Selesai') { $history[] = 'Selesai'; }
        foreach ($history as $position => $event) {
            $eventDate = (new DateTimeImmutable($date))->modify('+' . $position . ' minutes')->format('Y-m-d H:i:s');
            dbStatement($conn, 'INSERT INTO riwayat_status (id_pengaduan,status,keterangan,created_at) VALUES (?,?,?,?)', 'isss', [$id,$event,'Simulasi perubahan status: ' . $event,$eventDate]);
        }
        if ($officer !== null && $status !== 'Menunggu') {
            dbStatement($conn, 'INSERT INTO penanganan (id_pengaduan,id_petugas,catatan,tanggal_penanganan) VALUES (?,?,?,?)', 'iiss', [$id,$officer,'Catatan penanganan sintetis untuk portofolio.',$date]);
        }
    }
    $conn->commit();
} catch (Throwable $exception) { $conn->rollback(); throw $exception; }
echo "DEMO sintetis berhasil dibuat. Password acak ditampilkan sekali; simpan secara privat.\n";
foreach ($credentials as [$email, $password]) { echo $email . ' : ' . $password . PHP_EOL; }
echo "Tidak ada nomor WhatsApp, foto, token, atau data produksi yang dibuat.\n";

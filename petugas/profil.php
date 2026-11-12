<?php
require_once "../config/database.php";
requireRole('petugas');
$id_user = (int) $_SESSION['id_user'];
$petugas = dbOne($conn, "SELECT id_user, nama, email, no_wa, fakultas, role, created_at FROM users WHERE id_user = ? AND role = 'petugas' LIMIT 1", 'i', [$id_user]);
if (!$petugas) {
    http_response_code(403);
    exit('Akses tidak diizinkan.');
}
$nama = $petugas['nama'];
$email = $petugas['email'];
$no_wa = $petugas['no_wa'] ?? '';
$fakultas = $petugas['fakultas'] ?? '';
$success = '';
$error = '';
$profileLimits = [
    'nama' => min(200, dbTextLimit($conn, 'users', 'nama', 100)),
    'email' => min(254, dbTextLimit($conn, 'users', 'email', 254)),
    'no_wa' => min(30, dbTextLimit($conn, 'users', 'no_wa', 30)),
    'fakultas' => min(200, dbTextLimit($conn, 'users', 'fakultas', 150)),
];
$textLength = static function (string $value): int {
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = postString('action');
    if ($action === 'update_profile') {
        $namaBaru = trim(postString('nama'));
        $emailBaru = trim(postString('email'));
        $waBaru = trim(postString('no_wa'));
        $fakultasBaru = trim(postString('fakultas'));
        if ($namaBaru === '' || $emailBaru === '') {
            $error = 'Nama dan email wajib diisi.';
        } elseif ($textLength($namaBaru) > $profileLimits['nama'] || strlen($emailBaru) > $profileLimits['email'] || strlen($waBaru) > $profileLimits['no_wa'] || $textLength($fakultasBaru) > $profileLimits['fakultas']) {
            $error = 'Data profil terlalu panjang. Periksa kembali isian Anda.';
        } elseif (!filter_var($emailBaru, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif ($waBaru !== '' && !preg_match('/^\+?[0-9][0-9 ()-]{5,19}$/D', $waBaru)) {
            $error = 'Format nomor WhatsApp tidak valid.';
        } else {
            $existingEmail = dbOne($conn, 'SELECT id_user FROM users WHERE email = ? AND id_user != ? LIMIT 1', 'si', [$emailBaru, $id_user]);
            if ($existingEmail) {
                $error = 'Email tersebut sudah digunakan oleh akun lain.';
            } else {
                try {
                    dbStatement($conn, "UPDATE users SET nama = ?, email = ?, no_wa = ?, fakultas = ? WHERE id_user = ? AND role = 'petugas'", 'ssssi', [$namaBaru, $emailBaru, $waBaru, $fakultasBaru, $id_user]);
                    $nama = $_SESSION['nama'] = $namaBaru;
                    $email = $_SESSION['email'] = $emailBaru;
                    $no_wa = $_SESSION['no_wa'] = $waBaru;
                    $fakultas = $_SESSION['fakultas'] = $fakultasBaru;
                    $success = 'Profil berhasil diperbarui.';
                } catch (Throwable $exception) {
                    safeLog('profile_update_failed', ['user_id' => $id_user]);
                    $error = 'Profil gagal diperbarui. Silakan coba lagi.';
                }
            }
        }
    } elseif ($action === 'update_password') {
        $passwordLama = postString('password_lama');
        $passwordBaru = postString('password_baru');
        $konfirmasiPassword = postString('konfirmasi_password');
        if ($passwordLama === '' || $passwordBaru === '' || $konfirmasiPassword === '') {
            $error = 'Semua kolom password wajib diisi.';
        } elseif (strlen($passwordBaru) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif (strlen($passwordBaru) > 72 || strlen($passwordLama) > 4096) {
            $error = 'Password terlalu panjang. Maksimal 72 byte untuk password baru.';
        } elseif ($passwordBaru !== $konfirmasiPassword) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $account = dbOne($conn, "SELECT password FROM users WHERE id_user = ? AND role = 'petugas' LIMIT 1", 'i', [$id_user]);
            if (!$account || !password_verify($passwordLama, $account['password'])) {
                $error = 'Password lama tidak sesuai.';
            } else {
                try {
                    $passwordHash = password_hash($passwordBaru, PASSWORD_DEFAULT);
                    dbStatement($conn, "UPDATE users SET password = ? WHERE id_user = ? AND role = 'petugas'", 'si', [$passwordHash, $id_user]);
                    $_SESSION['auth_version'] = hash('sha256', $passwordHash);
                    session_regenerate_id(true);
                    $success = 'Password berhasil diperbarui.';
                } catch (Throwable $exception) {
                    safeLog('password_update_failed', ['user_id' => $id_user]);
                    $error = 'Password gagal diperbarui.';
                }
            }
        }
    } else {
        $error = 'Permintaan tidak valid.';
    }
}
/* =========================================================
   STATISTIK PETUGAS
========================================================= */

$stmtStats =
    mysqli_prepare(
        $conn,
        "
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Menunggu') AS menunggu,
            SUM(status = 'Diproses') AS diproses,
            SUM(status = 'Selesai') AS selesai
        FROM pengaduan
        WHERE id_petugas = ?
        "
    );


mysqli_stmt_bind_param(
    $stmtStats,
    "i",
    $id_user
);


mysqli_stmt_execute(
    $stmtStats
);


$resultStats =
    mysqli_stmt_get_result(
        $stmtStats
    );


$stats =
    mysqli_fetch_assoc(
        $resultStats
    );


$totalTugas =
    (int)($stats['total'] ?? 0);


$totalMenunggu =
    (int)($stats['menunggu'] ?? 0);


$totalDiproses =
    (int)($stats['diproses'] ?? 0);


$totalSelesai =
    (int)($stats['selesai'] ?? 0);


$progress =
    $totalTugas > 0
        ? round(
            ($totalSelesai / $totalTugas) * 100
        )
        : 0;


/* =========================================================
   TANGGAL BERGABUNG
========================================================= */

function formatTanggal($tanggal)
{
    if (!$tanggal) {
        return '-';
    }


    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];


    $timestamp =
        strtotime($tanggal);


    return date(
        'd',
        $timestamp
    )
    . ' '
    . $bulan[
        (int)date(
            'm',
            $timestamp
        )
    ]
    . ' '
    . date(
        'Y',
        $timestamp
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Profil Petugas | ADUIN
    </title>


    <link
        rel="icon"
        type="image/png"
        href="../assets/img/logo-aduin.png"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <link
        rel="stylesheet"
        href="https://unpkg.com/lenis@1.3.26/dist/lenis.css"
    >


    <script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lucide@latest"></script>


    <script nonce="<?= e(cspNonce()) ?>" src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>

</head>


<body>


<?php

$basePath = '../';

$activePage = 'profil';

include '../includes/navbar.php';

?>


<main class="staff-profile-page">

    <div class="staff-profile-shell">


        <!-- =====================================================
             HEADER
        ===================================================== -->

        <section class="profile-page-header">

            <div>

                <span>
                    AKUN PETUGAS
                </span>

                <h1>
                    Profil Saya
                </h1>

                <p>
                    Kelola informasi akun dan keamanan
                    profil petugas Anda.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="profile-back-btn"
            >

                <i data-lucide="arrow-left"></i>

                Dashboard

            </a>

        </section>



        <!-- =====================================================
             ALERT
        ===================================================== -->

        <?php if ($success !== ''): ?>

            <div class="profile-alert success">

                <div class="alert-icon">

                    <i data-lucide="circle-check"></i>

                </div>


                <div>

                    <strong>
                        Berhasil
                    </strong>

                    <p>
                        <?= htmlspecialchars($success) ?>
                    </p>

                </div>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="profile-alert error">

                <div class="alert-icon">

                    <i data-lucide="circle-alert"></i>

                </div>


                <div>

                    <strong>
                        Perubahan gagal
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             MAIN GRID
        ===================================================== -->

        <section class="profile-main-grid">


            <!-- =================================================
                 LEFT
            ================================================= -->

            <div class="profile-left">


                <!-- =============================================
                     PROFILE SUMMARY
                ============================================== -->

                <section class="profile-summary-card">


                    <div class="profile-cover">

                        <div class="cover-pattern"></div>

                    </div>


                    <div class="profile-summary-content">

                        <div class="profile-large-avatar">

                            <i data-lucide="user-round"></i>

                        </div>


                        <div class="profile-summary-info">

                            <span>
                                PETUGAS
                            </span>

                            <h2>
                                <?= htmlspecialchars($nama) ?>
                            </h2>

                            <p>
                                <?= htmlspecialchars($email) ?>
                            </p>

                        </div>


                        <div class="profile-role-badge">

                            <i data-lucide="shield-check"></i>

                            Petugas

                        </div>

                    </div>


                    <div class="profile-summary-details">


                        <div>

                            <i data-lucide="building-2"></i>

                            <span>

                                <small>
                                    Fakultas
                                </small>

                                <strong>
                                    <?= $fakultas !== ''
                                        ? htmlspecialchars($fakultas)
                                        : 'Belum diisi' ?>
                                </strong>

                            </span>

                        </div>


                        <div>

                            <i data-lucide="phone"></i>

                            <span>

                                <small>
                                    Nomor WhatsApp
                                </small>

                                <strong>
                                    <?= $no_wa !== ''
                                        ? htmlspecialchars($no_wa)
                                        : 'Belum diisi' ?>
                                </strong>

                            </span>

                        </div>


                        <div>

                            <i data-lucide="calendar-days"></i>

                            <span>

                                <small>
                                    Bergabung
                                </small>

                                <strong>
                                    <?= formatTanggal(
                                        $petugas['created_at']
                                    ) ?>
                                </strong>

                            </span>

                        </div>


                    </div>


                </section>



                <!-- =============================================
                     EDIT PROFIL
                ============================================== -->

                <section class="profile-form-card">


                    <div class="profile-card-heading">

                        <div>

                            <span>
                                INFORMASI AKUN
                            </span>

                            <h2>
                                Data Profil
                            </h2>

                        </div>


                        <div class="profile-heading-icon">

                            <i data-lucide="user-pen"></i>

                        </div>

                    </div>


                    <form
                        method="POST"
                        id="profileForm"
                    >
                        <?= csrfField() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >


                        <div class="form-grid">


                            <div class="form-field">

                                <label for="nama">
                                    Nama Lengkap
                                    <span>*</span>
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="user"></i>

                                    <input
                                        type="text"
                                        id="nama"
                                        name="nama"
                                        value="<?= htmlspecialchars($nama) ?>"
                                        placeholder="Nama lengkap"
                                        required
                                    >

                                </div>

                            </div>



                            <div class="form-field">

                                <label for="email">
                                    Email
                                    <span>*</span>
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="mail"></i>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?= htmlspecialchars($email) ?>"
                                        placeholder="nama@example.invalid"
                                        required
                                    >

                                </div>

                            </div>



                            <div class="form-field">

                                <label for="no_wa">
                                    Nomor WhatsApp
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="phone"></i>

                                    <input
                                        type="text"
                                        id="no_wa"
                                        name="no_wa"
                                        value="<?= htmlspecialchars($no_wa) ?>"
                                        placeholder="08xxxxxxxxxx"
                                    >

                                </div>

                            </div>



                            <div class="form-field">

                                <label for="fakultas">
                                    Fakultas
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="building-2"></i>

                                    <input
                                        type="text"
                                        id="fakultas"
                                        name="fakultas"
                                        value="<?= htmlspecialchars($fakultas) ?>"
                                        placeholder="Nama fakultas"
                                    >

                                </div>

                            </div>


                        </div>


                        <div class="form-actions">

                            <span>
                                Perubahan akan langsung
                                diterapkan pada akun Anda.
                            </span>


                            <button
                                type="submit"
                                class="save-profile-btn"
                            >

                                <i data-lucide="save"></i>

                                Simpan Profil

                            </button>

                        </div>


                    </form>


                </section>



                <!-- =============================================
                     PASSWORD
                ============================================== -->

                <section class="profile-form-card password-card">


                    <div class="profile-card-heading">

                        <div>

                            <span>
                                KEAMANAN AKUN
                            </span>

                            <h2>
                                Ubah Password
                            </h2>

                        </div>


                        <div class="profile-heading-icon security">

                            <i data-lucide="lock-keyhole"></i>

                        </div>

                    </div>


                    <form
                        method="POST"
                        id="passwordForm"
                    >
                        <?= csrfField() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="update_password"
                        >


                        <div class="password-fields">


                            <div class="form-field">

                                <label for="password_lama">
                                    Password Saat Ini
                                    <span>*</span>
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="lock"></i>

                                    <input
                                        type="password"
                                        id="password_lama"
                                        name="password_lama"
                                        placeholder="Masukkan password saat ini"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        data-target="password_lama"
                                        aria-label="Tampilkan password"
                                    >

                                        <i data-lucide="eye"></i>

                                    </button>

                                </div>

                            </div>



                            <div class="form-field">

                                <label for="password_baru">
                                    Password Baru
                                    <span>*</span>
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="key-round"></i>

                                    <input
                                        type="password"
                                        id="password_baru"
                                        name="password_baru"
                                        placeholder="Minimal 6 karakter"
                                        minlength="6"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        data-target="password_baru"
                                        aria-label="Tampilkan password"
                                    >

                                        <i data-lucide="eye"></i>

                                    </button>

                                </div>

                            </div>



                            <div class="form-field">

                                <label for="konfirmasi_password">
                                    Konfirmasi Password
                                    <span>*</span>
                                </label>


                                <div class="input-wrap">

                                    <i data-lucide="shield-check"></i>

                                    <input
                                        type="password"
                                        id="konfirmasi_password"
                                        name="konfirmasi_password"
                                        placeholder="Ulangi password baru"
                                        minlength="6"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        data-target="konfirmasi_password"
                                        aria-label="Tampilkan password"
                                    >

                                        <i data-lucide="eye"></i>

                                    </button>

                                </div>

                            </div>


                        </div>


                        <div class="password-note">

                            <i data-lucide="info"></i>

                            <span>
                                Gunakan password minimal 6 karakter
                                dan jangan membagikannya kepada orang lain.
                            </span>

                        </div>


                        <div class="form-actions">

                            <span>
                                Password akan disimpan secara
                                terenkripsi.
                            </span>


                            <button
                                type="submit"
                                class="save-profile-btn"
                            >

                                <i data-lucide="key-round"></i>

                                Ubah Password

                            </button>

                        </div>


                    </form>


                </section>


            </div>



            <!-- =================================================
                 RIGHT SIDEBAR
            ================================================= -->

            <aside class="profile-sidebar">


                <!-- =============================================
                     STATISTIK
                ============================================== -->

                <section class="mini-stats-card">


                    <div class="sidebar-heading">

                        <span>
                            AKTIVITAS
                        </span>

                        <h2>
                            Statistik Tugas
                        </h2>

                    </div>


                    <div class="mini-stat-list">


                        <div class="mini-stat">

                            <div class="mini-stat-icon blue">

                                <i data-lucide="clipboard-list"></i>

                            </div>


                            <div>

                                <span>
                                    Total Tugas
                                </span>

                                <strong>
                                    <?= $totalTugas ?>
                                </strong>

                            </div>

                        </div>



                        <div class="mini-stat">

                            <div class="mini-stat-icon amber">

                                <i data-lucide="clock-3"></i>

                            </div>


                            <div>

                                <span>
                                    Menunggu
                                </span>

                                <strong>
                                    <?= $totalMenunggu ?>
                                </strong>

                            </div>

                        </div>



                        <div class="mini-stat">

                            <div class="mini-stat-icon violet">

                                <i data-lucide="wrench"></i>

                            </div>


                            <div>

                                <span>
                                    Diproses
                                </span>

                                <strong>
                                    <?= $totalDiproses ?>
                                </strong>

                            </div>

                        </div>



                        <div class="mini-stat">

                            <div class="mini-stat-icon green">

                                <i data-lucide="circle-check"></i>

                            </div>


                            <div>

                                <span>
                                    Selesai
                                </span>

                                <strong>
                                    <?= $totalSelesai ?>
                                </strong>

                            </div>

                        </div>


                    </div>


                </section>



                <!-- =============================================
                     PROGRESS
                ============================================== -->

                <section class="profile-progress-card">


                    <div class="sidebar-heading">

                        <span>
                            PROGRES
                        </span>

                        <h2>
                            Penyelesaian
                        </h2>

                    </div>


                    <div class="progress-circle-wrap">


                        <div
                            class="progress-circle"
                            style="--progress: <?= $progress ?>%;"
                        >

                            <div>

                                <strong>
                                    <?= $progress ?>%
                                </strong>

                                <span>
                                    selesai
                                </span>

                            </div>

                        </div>


                    </div>


                    <p class="progress-description">

                        <?php if ($totalTugas === 0): ?>

                            Belum ada tugas yang
                            ditugaskan kepada Anda.

                        <?php elseif (
                            $totalSelesai === $totalTugas
                        ): ?>

                            Semua tugas yang ditugaskan
                            telah selesai ditangani.

                        <?php else: ?>

                            Anda telah menyelesaikan
                            <?= $totalSelesai ?>
                            dari
                            <?= $totalTugas ?>
                            tugas.

                        <?php endif; ?>

                    </p>


                    <a
                        href="tugas.php"
                        class="sidebar-action"
                    >

                        Lihat Tugas

                        <i data-lucide="arrow-right"></i>

                    </a>


                </section>



                <!-- =============================================
                     SECURITY INFO
                ============================================== -->

                <section class="security-card">


                    <div class="security-card-icon">

                        <i data-lucide="shield-check"></i>

                    </div>


                    <div>

                        <strong>
                            Keamanan Akun
                        </strong>

                        <p>
                            Jangan bagikan password atau
                            informasi login kepada orang lain.
                        </p>

                    </div>

                </section>


            </aside>


        </section>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - PETUGAS PROFILE
========================================================= */

.staff-profile-page {

    min-height: calc(100vh - 76px);

    padding: 38px 24px 70px;

    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(37, 99, 235, .065),
            transparent 28%
        ),
        radial-gradient(
            circle at 95% 8%,
            rgba(59, 130, 246, .04),
            transparent 25%
        ),
        #f8fafc;

}

.staff-profile-shell {

    width: min(1120px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.profile-page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}

.profile-page-header > div {

    min-width: 0;

}

.profile-page-header > div > span {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .1em;

}

.profile-page-header h1 {

    margin: 0;

    color: #0f172a;

    font-size: 27px;

    font-weight: 800;

    line-height: 1.15;

    letter-spacing: -.5px;

}

.profile-page-header p {

    margin: 6px 0 0;

    color: #64748b;

    font-size: 10px;

    line-height: 1.5;

}

.profile-back-btn {

    min-height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 0 12px;

    color: #475569;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 9px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    transition: .2s ease;

}

.profile-back-btn:hover {

    color: #2563eb;

    border-color: #bfdbfe;

    background: #eff6ff;

    transform: translateY(-1px);

}

.profile-back-btn svg {

    width: 14px;
    height: 14px;

}


/* =========================================================
   ALERT
========================================================= */

.profile-alert {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    margin-bottom: 18px;

    padding: 13px 15px;

    border-radius: 12px;

}

.profile-alert.success {

    color: #166534;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}

.profile-alert.error {

    color: #991b1b;

    background: #fef2f2;

    border: 1px solid #fecaca;

}

.alert-icon {

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 8px;

}

.profile-alert.success .alert-icon {

    background: #dcfce7;

}

.profile-alert.error .alert-icon {

    background: #fee2e2;

}

.alert-icon svg {

    width: 16px;
    height: 16px;

}

.profile-alert strong {

    display: block;

    font-size: 10px;

}

.profile-alert p {

    margin: 3px 0 0;

    font-size: 8px;

    line-height: 1.5;

    opacity: .8;

}


/* =========================================================
   MAIN GRID
========================================================= */

.profile-main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        300px;

    align-items: start;

    gap: 20px;

}

.profile-left {

    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 17px;

}

.profile-sidebar {

    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 15px;

    position: sticky;

    top: 95px;

}


/* =========================================================
   PROFILE SUMMARY
========================================================= */

.profile-summary-card {

    overflow: hidden;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 16px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

}

.profile-cover {

    position: relative;

    height: 105px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #3b82f6
        );

}

.cover-pattern {

    position: absolute;

    inset: 0;

    opacity: .25;

    background-image:
        radial-gradient(
            circle at 15% 20%,
            rgba(255,255,255,.35) 0,
            rgba(255,255,255,.35) 2px,
            transparent 3px
        );

    background-size: 24px 24px;

}

.profile-summary-content {

    position: relative;

    display: flex;

    align-items: flex-end;

    gap: 13px;

    padding: 0 20px;

    margin-top: -31px;

}

.profile-large-avatar {

    width: 68px;
    height: 68px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #fff;

    border: 4px solid #fff;

    border-radius: 18px;

    box-shadow:
        0 5px 18px rgba(15,23,42,.12);

}

.profile-large-avatar::before {

    content: "";

    position: absolute;

    width: 58px;
    height: 58px;

    background: #eff6ff;

    border-radius: 14px;

    z-index: 0;

}

.profile-large-avatar svg {

    position: relative;

    z-index: 1;

    width: 27px;
    height: 27px;

}

.profile-summary-info {

    min-width: 0;

    padding-bottom: 4px;

}

.profile-summary-info > span {

    display: block;

    margin-bottom: 3px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .08em;

}

.profile-summary-info h2 {

    overflow: hidden;

    margin: 0;

    color: #0f172a;

    font-size: 15px;

    font-weight: 800;

    line-height: 1.2;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.profile-summary-info p {

    overflow: hidden;

    margin: 3px 0 0;

    color: #94a3b8;

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.profile-role-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    min-height: 27px;

    margin-left: auto;

    margin-bottom: 4px;

    padding: 0 8px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 7px;

    font-size: 7px;

    font-weight: 800;

}

.profile-role-badge svg {

    width: 12px;
    height: 12px;

}

.profile-summary-details {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 0;

    margin-top: 19px;

    border-top: 1px solid #eef2f7;

}

.profile-summary-details > div {

    display: flex;

    align-items: center;

    gap: 8px;

    min-width: 0;

    padding: 13px 16px;

    border-right: 1px solid #eef2f7;

}

.profile-summary-details > div:last-child {

    border-right: none;

}

.profile-summary-details > div > svg {

    width: 15px;
    height: 15px;

    flex-shrink: 0;

    color: #94a3b8;

}

.profile-summary-details span {

    min-width: 0;

}

.profile-summary-details small {

    display: block;

    margin-bottom: 3px;

    color: #94a3b8;

    font-size: 6px;

}

.profile-summary-details strong {

    display: block;

    overflow: hidden;

    color: #475569;

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   FORM CARD
========================================================= */

.profile-form-card {

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

}

.profile-card-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 17px 19px;

    border-bottom: 1px solid #eef2f7;

}

.profile-card-heading > div:first-child {

    min-width: 0;

}

.profile-card-heading > div:first-child > span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .09em;

}

.profile-card-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

}

.profile-heading-icon {

    width: 33px;
    height: 33px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 9px;

}

.profile-heading-icon.security {

    color: #7c3aed;

    background: #f5f3ff;

    border-color: #ddd6fe;

}

.profile-heading-icon svg {

    width: 16px;
    height: 16px;

}

.profile-form-card form {

    padding: 18px 19px;

}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 15px;

}

.form-field {

    min-width: 0;

}

.form-field > label {

    display: block;

    margin-bottom: 7px;

    color: #334155;

    font-size: 8px;

    font-weight: 800;

}

.form-field > label span {

    color: #ef4444;

}

.input-wrap {

    position: relative;

}

.input-wrap > svg {

    position: absolute;

    top: 50%;
    left: 11px;

    width: 14px;
    height: 14px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}

.input-wrap input {

    width: 100%;

    height: 40px;

    box-sizing: border-box;

    padding: 0 11px 0 34px;

    color: #334155;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 9px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;

}

.input-wrap input::placeholder {

    color: #cbd5e1;

}

.input-wrap input:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.07);

}

.form-actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-top: 18px;

    padding-top: 15px;

    border-top: 1px solid #eef2f7;

}

.form-actions > span {

    color: #94a3b8;

    font-size: 7px;

    line-height: 1.5;

}

.save-profile-btn {

    min-height: 38px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 6px;

    flex-shrink: 0;

    padding: 0 13px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 8px;

    cursor: pointer;

    font-family: inherit;

    font-size: 8px;

    font-weight: 800;

    box-shadow:
        0 7px 17px rgba(37,99,235,.15);

    transition: .2s ease;

}

.save-profile-btn:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-1px);

}

.save-profile-btn svg {

    width: 14px;
    height: 14px;

}


/* =========================================================
   PASSWORD
========================================================= */

.password-fields {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 13px;

}

.password-toggle {

    position: absolute;

    top: 50%;
    right: 8px;

    width: 27px;
    height: 27px;

    display: flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    color: #94a3b8;

    background: transparent;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    transform: translateY(-50%);

}

.password-toggle:hover {

    color: #2563eb;

    background: #eff6ff;

}

.password-toggle svg {

    width: 14px;
    height: 14px;

}

.password-fields
.input-wrap input {

    padding-right: 40px;

}

.password-note {

    display: flex;

    align-items: flex-start;

    gap: 7px;

    margin-top: 15px;

    padding: 9px 10px;

    color: #64748b;

    background: #f8fafc;

    border: 1px solid #eef2f7;

    border-radius: 8px;

}

.password-note svg {

    width: 13px;
    height: 13px;

    flex-shrink: 0;

    color: #2563eb;

}

.password-note span {

    font-size: 7px;

    line-height: 1.5;

}


/* =========================================================
   SIDEBAR HEADING
========================================================= */

.sidebar-heading {

    padding: 17px 18px;

    border-bottom: 1px solid #eef2f7;

}

.sidebar-heading > span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .09em;

}

.sidebar-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

}


/* =========================================================
   MINI STATS
========================================================= */

.mini-stats-card {

    overflow: hidden;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15,23,42,.035);

}

.mini-stat-list {

    padding: 5px 0;

}

.mini-stat {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 10px 17px;

    border-bottom: 1px solid #f1f5f9;

}

.mini-stat:last-child {

    border-bottom: none;

}

.mini-stat-icon {

    width: 34px;
    height: 34px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 9px;

}

.mini-stat-icon svg {

    width: 16px;
    height: 16px;

}

.mini-stat-icon.blue {

    color: #2563eb;

    background: #eff6ff;

}

.mini-stat-icon.amber {

    color: #b45309;

    background: #fffbeb;

}

.mini-stat-icon.violet {

    color: #7c3aed;

    background: #f5f3ff;

}

.mini-stat-icon.green {

    color: #15803d;

    background: #f0fdf4;

}

.mini-stat > div:last-child {

    min-width: 0;

}

.mini-stat span {

    display: block;

    color: #94a3b8;

    font-size: 7px;

}

.mini-stat strong {

    display: block;

    margin-top: 2px;

    color: #334155;

    font-size: 14px;

    font-weight: 800;

}


/* =========================================================
   PROGRESS
========================================================= */

.profile-progress-card {

    padding-bottom: 17px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15,23,42,.035);

}

.progress-circle-wrap {

    display: flex;

    align-items: center;
    justify-content: center;

    padding: 18px 0 10px;

}

.progress-circle {

    width: 105px;
    height: 105px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background:
        conic-gradient(
            #2563eb var(--progress),
            #e2e8f0 var(--progress)
        );

}

.progress-circle::before {

    content: "";

    position: absolute;

    width: 83px;
    height: 83px;

    background: #fff;

    border-radius: 50%;

}

.progress-circle > div {

    position: relative;

    z-index: 1;

    text-align: center;

}

.progress-circle strong {

    display: block;

    color: #0f172a;

    font-size: 19px;

    font-weight: 800;

    line-height: 1;

}

.progress-circle span {

    display: block;

    margin-top: 4px;

    color: #94a3b8;

    font-size: 7px;

}

.progress-description {

    max-width: 230px;

    margin: 0 auto 13px;

    padding: 0 15px;

    color: #64748b;

    font-size: 8px;

    line-height: 1.6;

    text-align: center;

}

.sidebar-action {

    min-height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    margin: 0 17px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 8px;

    font-weight: 800;

    transition: .2s ease;

}

.sidebar-action:hover {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

}

.sidebar-action svg {

    width: 13px;
    height: 13px;

}


/* =========================================================
   SECURITY
========================================================= */

.security-card {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    padding: 14px;

    color: #5b21b6;

    background: #faf5ff;

    border: 1px solid #e9d5ff;

    border-radius: 13px;

}

.security-card-icon {

    width: 31px;
    height: 31px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #7c3aed;

    background: #f3e8ff;

    border-radius: 8px;

}

.security-card-icon svg {

    width: 15px;
    height: 15px;

}

.security-card strong {

    display: block;

    margin-bottom: 3px;

    font-size: 9px;

    font-weight: 800;

}

.security-card p {

    margin: 0;

    color: #7e57a5;

    font-size: 7px;

    line-height: 1.5;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 950px) {

    .profile-main-grid {

        grid-template-columns:
            minmax(0, 1fr);

    }

    .profile-sidebar {

        position: static;

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        align-items: start;

    }

    .mini-stats-card {

        grid-column: 1 / -1;

    }

}


/* =========================================================
   TABLET SMALL
========================================================= */

@media (max-width: 760px) {

    .password-fields {

        grid-template-columns:
            1fr;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .staff-profile-page {

        padding: 28px 14px 50px;

    }

    .profile-page-header {

        align-items: flex-start;

        flex-direction: column;

    }

    .profile-back-btn {

        width: 100%;

        box-sizing: border-box;

    }

    .profile-summary-content {

        align-items: flex-start;

        flex-wrap: wrap;

        padding: 0 15px;

    }

    .profile-role-badge {

        margin-left: 0;

        margin-bottom: 0;

    }

    .profile-summary-details {

        grid-template-columns:
            1fr;

    }

    .profile-summary-details > div {

        border-right: none;

        border-bottom: 1px solid #eef2f7;

    }

    .profile-summary-details > div:last-child {

        border-bottom: none;

    }

    .form-grid {

        grid-template-columns:
            1fr;

    }

    .profile-sidebar {

        display: flex;

        flex-direction: column;

    }

    .form-actions {

        align-items: stretch;

        flex-direction: column;

    }

    .form-actions > span {

        order: 2;

    }

    .save-profile-btn {

        width: 100%;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 420px) {

    .staff-profile-page {

        padding: 24px 12px 45px;

    }

    .profile-cover {

        height: 90px;

    }

    .profile-large-avatar {

        width: 62px;
        height: 62px;

    }

    .profile-summary-info h2 {

        font-size: 14px;

    }

    .profile-card-heading {

        padding: 15px;

    }

    .profile-form-card form {

        padding: 15px;

    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation: none !important;

        transition: none !important;

    }

}

/* =========================================================
   MOBILE FINAL FIX - PROFIL PETUGAS
   Tidak mengubah desktop
   ========================================================= */

@media (max-width: 620px) {

    /* PAGE */
    .staff-profile-page {
        width: 100%;
        max-width: 100%;
        padding: 24px 12px 45px !important;
        overflow-x: hidden;
    }

    .staff-profile-shell {
        width: 100%;
        max-width: 100%;
    }

    /* HEADER */
    .profile-page-header {
        width: 100%;
        gap: 14px;
        align-items: flex-start;
    }

    .profile-page-header > div {
        width: 100%;
        min-width: 0;
    }

    .profile-page-header h1 {
        font-size: 25px;
    }

    .profile-page-header p {
        font-size: 10px;
        line-height: 1.6;
    }

    .profile-back-btn {
        width: 100%;
        min-height: 40px;
    }


    /* =====================================================
       MAIN GRID
       ===================================================== */

    .profile-main-grid {
        display: flex !important;
        flex-direction: column !important;
        width: 100%;
        gap: 16px;
    }

    .profile-left {
        width: 100%;
        min-width: 0;
        gap: 16px;
    }

    .profile-sidebar {
        width: 100%;
        min-width: 0;
        display: flex !important;
        flex-direction: column !important;
        gap: 15px;
        position: static !important;
    }


    /* =====================================================
       PROFILE SUMMARY
       ===================================================== */

    .profile-summary-card {
        width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    .profile-cover {
        width: 100%;
        height: 95px;
    }

    .profile-summary-content {
        width: 100%;
        min-width: 0;
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 10px;
        padding: 0 14px;
        margin-top: -30px;
    }

    .profile-large-avatar {
        position: relative !important;
        width: 62px;
        height: 62px;
        min-width: 62px;
        flex-shrink: 0;
        margin: 0;
    }

    .profile-large-avatar::before {
        position: absolute !important;
        content: "";
        width: 52px;
        height: 52px;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        z-index: 0;
    }

    .profile-summary-info {
        min-width: 0;
        flex: 1;
        max-width: calc(100% - 75px);
        padding-bottom: 2px;
    }

    .profile-summary-info h2 {
        max-width: 100%;
        font-size: 14px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .profile-summary-info p {
        max-width: 100%;
        font-size: 8px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .profile-role-badge {
        width: auto;
        margin-left: 0 !important;
        margin-bottom: 0 !important;
        flex-shrink: 0;
    }


    /* =====================================================
       DETAIL PROFILE
       ===================================================== */

    .profile-summary-details {
        display: flex !important;
        flex-direction: column !important;
        width: 100%;
        margin-top: 17px;
    }

    .profile-summary-details > div {
        width: 100%;
        min-width: 0;
        border-right: none !important;
        border-bottom: 1px solid #eef2f7;
        padding: 12px 14px;
    }

    .profile-summary-details > div:last-child {
        border-bottom: none;
    }

    .profile-summary-details span {
        min-width: 0;
        overflow: hidden;
    }

    .profile-summary-details strong {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }


    /* =====================================================
       FORM CARD
       ===================================================== */

    .profile-form-card {
        width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    .profile-card-heading {
        width: 100%;
        padding: 15px;
    }

    .profile-card-heading h2 {
        font-size: 14px;
    }

    .profile-form-card form {
        width: 100%;
        padding: 15px;
    }


    /* =====================================================
       FORM
       ===================================================== */

    .form-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        width: 100%;
        gap: 14px;
    }

    .form-field {
        width: 100%;
        min-width: 0;
    }

    .input-wrap {
        width: 100%;
        min-width: 0;
    }

    .input-wrap input {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        height: 43px;
    }


    /* =====================================================
       PASSWORD
       ===================================================== */

    .password-fields {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        width: 100%;
        gap: 14px;
    }

    .password-fields .form-field {
        width: 100%;
    }

    .password-fields .input-wrap {
        width: 100%;
    }

    .password-fields .input-wrap input {
        width: 100%;
        padding-right: 42px;
    }


    /* =====================================================
       FORM ACTION
       ===================================================== */

    .form-actions {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100%;
        gap: 11px;
    }

    .form-actions > span {
        order: 2;
        width: 100%;
        font-size: 7px;
        line-height: 1.5;
    }

    .save-profile-btn {
        order: 1;
        width: 100% !important;
        min-height: 41px;
    }


    /* =====================================================
       SIDEBAR STATISTICS
       ===================================================== */

    .mini-stats-card,
    .profile-progress-card,
    .security-card {
        width: 100%;
        min-width: 0;
    }

    .sidebar-heading {
        padding: 15px;
    }

    .mini-stat {
        width: 100%;
        min-width: 0;
        padding: 12px 15px;
    }

    .mini-stat > div:last-child {
        min-width: 0;
    }


    /* =====================================================
       PROGRESS
       ===================================================== */

    .progress-circle-wrap {
        padding-top: 20px;
    }

    .progress-circle {
        width: 125px;
        height: 125px;
    }

    .progress-circle::before {
        width: 99px;
        height: 99px;
    }

    .progress-description {
        padding: 0 20px;
        font-size: 8px;
    }

    .sidebar-action {
        width: calc(100% - 30px);
        margin-left: 15px;
        margin-right: 15px;
    }


    /* =====================================================
       SECURITY
       ===================================================== */

    .security-card {
        padding: 13px;
    }


    /* =====================================================
       ALERT
       ===================================================== */

    .profile-alert {
        width: 100%;
        min-width: 0;
    }


    /* =====================================================
       ANTI OVERFLOW
       ===================================================== */

    .staff-profile-page,
    .staff-profile-shell,
    .profile-main-grid,
    .profile-left,
    .profile-sidebar,
    .profile-summary-card,
    .profile-form-card,
    .mini-stats-card,
    .profile-progress-card,
    .security-card {
        max-width: 100%;
    }

    .profile-main-grid *,
    .profile-left *,
    .profile-sidebar * {
        max-width: 100%;
    }
}


/* =========================================================
   SMALL PHONE
   ========================================================= */

@media (max-width: 420px) {

    .staff-profile-page {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    .profile-page-header h1 {
        font-size: 23px;
    }

    .profile-page-header p {
        font-size: 9px;
    }

    .profile-cover {
        height: 88px;
    }

    .profile-summary-content {
        padding-left: 12px;
        padding-right: 12px;
    }

    .profile-large-avatar {
        width: 58px;
        height: 58px;
        min-width: 58px;
    }

    .profile-large-avatar::before {
        width: 48px;
        height: 48px;
    }

    .profile-summary-info {
        max-width: calc(100% - 70px);
    }

    .profile-summary-info h2 {
        font-size: 13px;
    }

    .profile-summary-info p {
        font-size: 7px;
    }

    .profile-role-badge {
        font-size: 7px;
    }

    .profile-card-heading {
        padding: 14px;
    }

    .profile-form-card form {
        padding: 14px;
    }

    .sidebar-heading {
        padding: 14px;
    }

    .mini-stat {
        padding-left: 14px;
        padding-right: 14px;
    }
}


/* =========================================================
   VERY SMALL PHONE
   ========================================================= */

@media (max-width: 360px) {

    .staff-profile-page {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }

    .profile-page-header h1 {
        font-size: 21px;
    }

    .profile-summary-content {
        padding-left: 10px;
        padding-right: 10px;
    }

    .profile-large-avatar {
        width: 55px;
        height: 55px;
        min-width: 55px;
    }

    .profile-summary-info {
        max-width: calc(100% - 67px);
    }

    .profile-summary-info h2 {
        font-size: 12px;
    }

    .profile-card-heading h2 {
        font-size: 13px;
    }
}

</style>



<script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"></script>


<script nonce="<?= e(cspNonce()) ?>">

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =====================================================
           LUCIDE
        ===================================================== */

        if (window.lucide) {

            lucide.createIcons();

        }



        /* =====================================================
           LENIS
        ===================================================== */

        if (window.Lenis) {

            const lenis =
                new Lenis({

                    duration: 1.05,

                    smoothWheel: true,

                    syncTouch: false,

                    wheelMultiplier: .9,

                    touchMultiplier: 1.2

                });


            function raf(time) {

                lenis.raf(
                    time * 1000
                );

                requestAnimationFrame(
                    raf
                );

            }


            requestAnimationFrame(
                raf
            );

        }



        /* =====================================================
           GSAP
        ===================================================== */

        if (window.gsap) {


            gsap.from(
                ".profile-page-header",
                {
                    y: 20,
                    opacity: 0,
                    duration: .55,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".profile-alert",
                {
                    y: -10,
                    opacity: 0,
                    duration: .45,
                    delay: .05,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".profile-left > *",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .08,
                    delay: .1,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".profile-sidebar > *",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .08,
                    delay: .2,
                    ease: "power3.out"
                }
            );

        }



        /* =====================================================
           NAVBAR SCROLL
        ===================================================== */

        const navbar =
            document.querySelector(
                ".navbar"
            );


        if (navbar) {


            function updateNavbar() {

                if (
                    window.scrollY > 25
                ) {

                    navbar.classList.add(
                        "scrolled"
                    );

                } else {

                    navbar.classList.remove(
                        "scrolled"
                    );

                }

            }


            updateNavbar();


            window.addEventListener(
                "scroll",
                updateNavbar,
                {
                    passive: true
                }
            );

        }



        /* =====================================================
           PASSWORD TOGGLE
        ===================================================== */

        document
            .querySelectorAll(
                ".password-toggle"
            )
            .forEach(
                function (button) {


                    button.addEventListener(
                        "click",
                        function () {


                            const targetId =
                                this.dataset.target;


                            const input =
                                document.getElementById(
                                    targetId
                                );


                            if (!input) {
                                return;
                            }


                            const isPassword =
                                input.type ===
                                "password";


                            input.type =
                                isPassword
                                    ? "text"
                                    : "password";


                            this.innerHTML =
                                isPassword
                                    ? '<i data-lucide="eye-off"></i>'
                                    : '<i data-lucide="eye"></i>';


                            this.setAttribute(
                                "aria-label",
                                isPassword
                                    ? "Sembunyikan password"
                                    : "Tampilkan password"
                            );


                            if (window.lucide) {

                                lucide.createIcons();

                            }

                        }
                    );

                }
            );



        /* =====================================================
           PASSWORD CONFIRMATION
        ===================================================== */

        const passwordForm =
            document.getElementById(
                "passwordForm"
            );


        const passwordBaru =
            document.getElementById(
                "password_baru"
            );


        const konfirmasi =
            document.getElementById(
                "konfirmasi_password"
            );


        if (
            passwordForm &&
            passwordBaru &&
            konfirmasi
        ) {


            passwordForm.addEventListener(
                "submit",
                function (event) {


                    if (
                        passwordBaru.value !==
                        konfirmasi.value
                    ) {

                        event.preventDefault();


                        konfirmasi.focus();


                        konfirmasi.style.borderColor =
                            "#f87171";


                        konfirmasi.style.boxShadow =
                            "0 0 0 3px rgba(239,68,68,.08)";


                        setTimeout(
                            function () {

                                konfirmasi.style.borderColor =
                                    "";

                                konfirmasi.style.boxShadow =
                                    "";

                            },
                            2200
                        );

                    }

                }
            );

        }



        /* =====================================================
           FORM BUTTON LOADING
        ===================================================== */

        document
            .querySelectorAll(
                ".profile-form-card form"
            )
            .forEach(
                function (form) {


                    form.addEventListener(
                        "submit",
                        function () {


                            /*
                             * Jangan menampilkan loading
                             * jika validasi password gagal.
                             */

                            if (
                                form.id ===
                                "passwordForm" &&
                                passwordBaru &&
                                konfirmasi &&
                                passwordBaru.value !==
                                konfirmasi.value
                            ) {

                                return;

                            }


                            const button =
                                form.querySelector(
                                    ".save-profile-btn"
                                );


                            if (!button) {
                                return;
                            }


                            button.disabled =
                                true;


                            button.style.opacity =
                                ".7";


                            button.innerHTML = `

                                <i
                                    data-lucide="loader-circle"
                                    class="profile-loading-icon"
                                ></i>

                                Menyimpan...

                            `;


                            if (window.lucide) {

                                lucide.createIcons();

                            }

                        }
                    );

                }
            );


        /* =====================================================
           LOADING ANIMATION
        ===================================================== */

        const style =
            document.createElement(
                "style"
            );


        style.textContent = `

            .profile-loading-icon {
                animation:
                    profileSpin .8s linear infinite;
            }

            @keyframes profileSpin {
                to {
                    transform: rotate(360deg);
                }
            }

        `;


        document.head.appendChild(
            style
        );

    }
);

</script>


</body>

</html>

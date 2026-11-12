<?php
require_once "../config/database.php";
requireRole('petugas');


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

$id_user = $_SESSION['user_id'] ?? $_SESSION['id_user'];


/* =========================================================
   DATA PETUGAS
========================================================= */

$stmtUser = mysqli_prepare(
    $conn,
    "
    SELECT
        id_user,
        nama,
        email,
        no_wa,
        fakultas,
        role
    FROM users
    WHERE id_user = ?
      AND role = 'petugas'
    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmtUser,
    "i",
    $id_user
);

mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);

$petugas = mysqli_fetch_assoc($resultUser);


if (!$petugas) {
    header("Location: ../login.php");
    exit;
}


/* =========================================================
   STATISTIK TUGAS
========================================================= */

$stmtStats = mysqli_prepare(
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

mysqli_stmt_execute($stmtStats);

$resultStats =
    mysqli_stmt_get_result($stmtStats);

$stats =
    mysqli_fetch_assoc($resultStats);


$totalTugas =
    (int)($stats['total'] ?? 0);

$totalMenunggu =
    (int)($stats['menunggu'] ?? 0);

$totalDiproses =
    (int)($stats['diproses'] ?? 0);

$totalSelesai =
    (int)($stats['selesai'] ?? 0);


/* =========================================================
   TUGAS TERBARU
========================================================= */

$stmtRecent = mysqli_prepare(
    $conn,
    "
    SELECT
        p.id_pengaduan,
        p.no_laporan,
        p.lokasi,
        p.deskripsi,
        p.foto,
        p.status,
        p.tanggal_lapor,

        k.nama_kategori,

        u.nama AS nama_pelapor

    FROM pengaduan p

    INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori

    INNER JOIN users u
        ON p.id_user = u.id_user

    WHERE p.id_petugas = ?

    ORDER BY
        CASE p.status
            WHEN 'Diproses' THEN 1
            WHEN 'Menunggu' THEN 2
            WHEN 'Selesai' THEN 3
            ELSE 4
        END,
        p.tanggal_lapor DESC

    LIMIT 5
    "
);

mysqli_stmt_bind_param(
    $stmtRecent,
    "i",
    $id_user
);

mysqli_stmt_execute($stmtRecent);

$resultRecent =
    mysqli_stmt_get_result($stmtRecent);

$tugasTerbaru = [];

while (
    $row =
    mysqli_fetch_assoc($resultRecent)
) {

    $tugasTerbaru[] = $row;

}


/* =========================================================
   TUGAS TERBARU SELESAI
========================================================= */

$stmtLastCompleted = mysqli_prepare(
    $conn,
    "
    SELECT
        p.no_laporan,
        p.tanggal_lapor,
        k.nama_kategori,
        p.lokasi
    FROM pengaduan p

    INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori

    WHERE p.id_petugas = ?
      AND p.status = 'Selesai'

    ORDER BY p.tanggal_lapor DESC

    LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmtLastCompleted,
    "i",
    $id_user
);

mysqli_stmt_execute(
    $stmtLastCompleted
);

$resultLastCompleted =
    mysqli_stmt_get_result(
        $stmtLastCompleted
    );

$lastCompleted =
    mysqli_fetch_assoc(
        $resultLastCompleted
    );


/* =========================================================
   HELPER
========================================================= */

function statusClass($status)
{
    switch ($status) {

        case 'Menunggu':
            return 'waiting';

        case 'Diproses':
            return 'processing';

        case 'Selesai':
            return 'completed';

        default:
            return '';
    }
}


function statusIcon($status)
{
    switch ($status) {

        case 'Menunggu':
            return 'clock-3';

        case 'Diproses':
            return 'loader-circle';

        case 'Selesai':
            return 'circle-check';

        default:
            return 'circle';
    }
}


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

    $timestamp = strtotime($tanggal);

    return date('d', $timestamp)
        . ' '
        . $bulan[(int)date('m', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}


function formatWaktu($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('H:i', strtotime($tanggal)) . ' WIB';
}


/* =========================================================
   SAPAAN
========================================================= */

$jam = (int)date('H');

if ($jam < 11) {
    $sapaan = 'Selamat pagi';
} elseif ($jam < 15) {
    $sapaan = 'Selamat siang';
} elseif ($jam < 18) {
    $sapaan = 'Selamat sore';
} else {
    $sapaan = 'Selamat malam';
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

    <title>Dashboard Petugas | ADUIN</title>

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

$activePage = 'dashboard';

include '../includes/navbar.php';

?>


<main class="staff-dashboard">

    <div class="staff-shell">


        <!-- =====================================================
             WELCOME
        ===================================================== -->

        <section class="staff-welcome">

            <div class="welcome-content">

                <span class="welcome-eyebrow">
                    DASHBOARD PETUGAS
                </span>

                <h1>
                    <?= htmlspecialchars($sapaan) ?>,
                    <?= htmlspecialchars($petugas['nama']) ?> 👋
                </h1>

                <p>
                    Kelola dan pantau tugas penanganan
                    pengaduan fasilitas kampus Anda.
                </p>

            </div>


            <div class="welcome-action">

                <a
                    href="tugas.php"
                    class="view-task-btn"
                >

                    <i data-lucide="clipboard-list"></i>

                    <span>
                        Lihat Tugas
                    </span>

                    <i
                        data-lucide="arrow-right"
                        class="arrow-icon"
                    ></i>

                </a>

            </div>

        </section>



        <!-- =====================================================
             STATISTIK
        ===================================================== -->

        <section class="staff-stats">


            <!-- TOTAL -->

            <div class="staff-stat-card">

                <div class="staff-stat-icon blue">

                    <i data-lucide="clipboard-list"></i>

                </div>


                <div class="staff-stat-content">

                    <span>
                        Total Tugas
                    </span>

                    <strong>
                        <?= $totalTugas ?>
                    </strong>

                    <small>
                        Semua pengaduan yang ditugaskan
                    </small>

                </div>

            </div>



            <!-- MENUNGGU -->

            <div class="staff-stat-card">

                <div class="staff-stat-icon amber">

                    <i data-lucide="clock-3"></i>

                </div>


                <div class="staff-stat-content">

                    <span>
                        Menunggu
                    </span>

                    <strong>
                        <?= $totalMenunggu ?>
                    </strong>

                    <small>
                        Menunggu penanganan
                    </small>

                </div>

            </div>



            <!-- DIPROSES -->

            <div class="staff-stat-card">

                <div class="staff-stat-icon violet">

                    <i data-lucide="wrench"></i>

                </div>


                <div class="staff-stat-content">

                    <span>
                        Diproses
                    </span>

                    <strong>
                        <?= $totalDiproses ?>
                    </strong>

                    <small>
                        Sedang ditangani
                    </small>

                </div>

            </div>



            <!-- SELESAI -->

            <div class="staff-stat-card">

                <div class="staff-stat-icon green">

                    <i data-lucide="circle-check"></i>

                </div>


                <div class="staff-stat-content">

                    <span>
                        Selesai
                    </span>

                    <strong>
                        <?= $totalSelesai ?>
                    </strong>

                    <small>
                        Penanganan selesai
                    </small>

                </div>

            </div>


        </section>



        <!-- =====================================================
             MAIN GRID
        ===================================================== -->

        <section class="staff-main-grid">


            <!-- =================================================
                 TUGAS TERBARU
            ================================================= -->

            <div class="staff-task-card">

                <div class="section-heading">

                    <div>

                        <span>
                            PENGADUAN
                        </span>

                        <h2>
                            Tugas Terbaru
                        </h2>

                    </div>


                    <a
                        href="tugas.php"
                        class="see-all-btn"
                    >

                        Lihat Semua

                        <i data-lucide="arrow-right"></i>

                    </a>

                </div>



                <?php if (count($tugasTerbaru) > 0): ?>


                    <div class="task-list">


                        <?php foreach (
                            $tugasTerbaru
                            as $index => $task
                        ): ?>


                            <article
                                class="task-item"
                                data-task="<?= $index ?>"
                            >


                                <!-- ICON -->

                                <div class="task-item-icon">

                                    <?php if (
                                        !empty($task['foto'])
                                    ): ?>

                                        <img
                                            src="<?= uploadUrl($task['foto'], 'pengaduan') ?>"
                                            alt="Foto pengaduan"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <i data-lucide="file-text"></i>

                                    <?php endif; ?>

                                </div>



                                <!-- CONTENT -->

                                <div class="task-item-content">

                                    <div class="task-item-top">

                                        <span class="task-number">
                                            <?= htmlspecialchars(
                                                $task['no_laporan']
                                            ) ?>
                                        </span>


                                        <span
                                            class="
                                                task-status
                                                <?= statusClass(
                                                    $task['status']
                                                ) ?>
                                            "
                                        >

                                            <i
                                                data-lucide="<?= statusIcon(
                                                    $task['status']
                                                ) ?>"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $task['status']
                                            ) ?>

                                        </span>

                                    </div>


                                    <h3>
                                        <?= htmlspecialchars(
                                            $task['nama_kategori']
                                        ) ?>
                                    </h3>


                                    <div class="task-location">

                                        <i data-lucide="map-pin"></i>

                                        <span>
                                            <?= htmlspecialchars(
                                                $task['lokasi']
                                            ) ?>
                                        </span>

                                    </div>


                                    <div class="task-meta">

                                        <span>

                                            <i data-lucide="user"></i>

                                            <?= htmlspecialchars(
                                                $task['nama_pelapor']
                                            ) ?>

                                        </span>


                                        <span>

                                            <i data-lucide="calendar-days"></i>

                                            <?= formatTanggal(
                                                $task['tanggal_lapor']
                                            ) ?>

                                        </span>

                                    </div>

                                </div>



                                <!-- ACTION -->

                                <a
                                    href="detail-tugas.php?id=<?= (int)$task['id_pengaduan'] ?>"
                                    class="task-detail-btn"
                                    title="Lihat detail tugas"
                                >

                                    <i data-lucide="chevron-right"></i>

                                </a>


                            </article>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="task-empty">

                        <div class="task-empty-icon">

                            <i data-lucide="clipboard-check"></i>

                        </div>


                        <h3>
                            Belum Ada Tugas
                        </h3>


                        <p>
                            Saat ini belum ada pengaduan yang
                            ditugaskan kepada Anda.
                        </p>


                        <a
                            href="tugas.php"
                            class="empty-task-btn"
                        >

                            <i data-lucide="refresh-cw"></i>

                            Cek Tugas

                        </a>

                    </div>


                <?php endif; ?>

            </div>



            <!-- =================================================
                 SIDEBAR
            ================================================= -->

            <aside class="staff-sidebar">


                <!-- PROFIL SINGKAT -->

                <div class="staff-profile-card">

                    <div class="profile-card-top">

                        <div class="profile-avatar">

                            <i data-lucide="user-round"></i>

                        </div>


                        <div>

                            <span>
                                Petugas
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $petugas['nama']
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="profile-divider"></div>


                    <div class="profile-detail">

                        <i data-lucide="mail"></i>

                        <span>
                            <?= htmlspecialchars(
                                $petugas['email']
                            ) ?>
                        </span>

                    </div>


                    <?php if (
                        !empty($petugas['fakultas'])
                    ): ?>

                        <div class="profile-detail">

                            <i data-lucide="building-2"></i>

                            <span>
                                <?= htmlspecialchars(
                                    $petugas['fakultas']
                                ) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <a
                        href="profil.php"
                        class="profile-link"
                    >

                        Kelola Profil

                        <i data-lucide="arrow-up-right"></i>

                    </a>

                </div>



                <!-- PROGRESS -->

                <div class="progress-card">

                    <div class="section-heading small">

                        <div>

                            <span>
                                PROGRES
                            </span>

                            <h2>
                                Penanganan
                            </h2>

                        </div>

                    </div>


                    <?php

                    $progressPercentage =
                        $totalTugas > 0
                            ? round(
                                ($totalSelesai / $totalTugas) * 100
                            )
                            : 0;

                    ?>


                    <div class="progress-summary">

                        <div>

                            <strong>
                                <?= $progressPercentage ?>%
                            </strong>

                            <span>
                                selesai
                            </span>

                        </div>


                        <div class="progress-count">

                            <?= $totalSelesai ?>
                            /
                            <?= $totalTugas ?>

                        </div>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-fill"
                            style="width: <?= $progressPercentage ?>%;"
                        ></div>

                    </div>


                    <div class="progress-caption">

                        <?php if ($totalTugas === 0): ?>

                            Belum ada tugas yang ditugaskan.

                        <?php elseif ($totalSelesai === $totalTugas): ?>

                            Semua tugas telah selesai.

                        <?php else: ?>

                            <?= $totalTugas - $totalSelesai ?>
                            tugas masih perlu ditangani.

                        <?php endif; ?>

                    </div>

                </div>



                <!-- TERAKHIR SELESAI -->

                <div class="completed-card">

                    <div class="completed-icon">

                        <i data-lucide="badge-check"></i>

                    </div>


                    <div class="completed-content">

                        <span>
                            PENYELESAIAN TERBARU
                        </span>


                        <?php if ($lastCompleted): ?>

                            <strong>
                                <?= htmlspecialchars(
                                    $lastCompleted['no_laporan']
                                ) ?>
                            </strong>


                            <p>
                                <?= htmlspecialchars(
                                    $lastCompleted['nama_kategori']
                                ) ?>

                                ·

                                <?= htmlspecialchars(
                                    $lastCompleted['lokasi']
                                ) ?>
                            </p>


                            <small>
                                <?= formatTanggal(
                                    $lastCompleted['tanggal_lapor']
                                ) ?>
                            </small>

                        <?php else: ?>

                            <strong>
                                Belum ada
                            </strong>

                            <p>
                                Belum ada pengaduan yang
                                diselesaikan.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>


            </aside>


        </section>



        <!-- =====================================================
             QUICK ACTION
        ===================================================== -->

        <section class="quick-section">

            <div class="section-heading">

                <div>

                    <span>
                        AKSES CEPAT
                    </span>

                    <h2>
                        Menu Petugas
                    </h2>

                </div>

            </div>


            <div class="quick-grid">


                <a
                    href="tugas.php"
                    class="quick-card"
                >

                    <div class="quick-icon blue">

                        <i data-lucide="clipboard-list"></i>

                    </div>


                    <div>

                        <strong>
                            Tugas Pengaduan
                        </strong>

                        <span>
                            Lihat seluruh tugas yang diberikan
                        </span>

                    </div>


                    <i
                        data-lucide="arrow-up-right"
                        class="quick-arrow"
                    ></i>

                </a>



                <a
                    href="riwayat.php"
                    class="quick-card"
                >

                    <div class="quick-icon green">

                        <i data-lucide="history"></i>

                    </div>


                    <div>

                        <strong>
                            Riwayat Penanganan
                        </strong>

                        <span>
                            Lihat tugas yang telah ditangani
                        </span>

                    </div>


                    <i
                        data-lucide="arrow-up-right"
                        class="quick-arrow"
                    ></i>

                </a>



                <a
                    href="profil.php"
                    class="quick-card"
                >

                    <div class="quick-icon violet">

                        <i data-lucide="user-cog"></i>

                    </div>


                    <div>

                        <strong>
                            Profil Saya
                        </strong>

                        <span>
                            Kelola informasi akun petugas
                        </span>

                    </div>


                    <i
                        data-lucide="arrow-up-right"
                        class="quick-arrow"
                    ></i>

                </a>


            </div>

        </section>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - PETUGAS DASHBOARD
========================================================= */

.staff-dashboard {

    min-height: calc(100vh - 76px);

    padding: 42px 24px 70px;

    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(37, 99, 235, .07),
            transparent 28%
        ),
        radial-gradient(
            circle at 95% 10%,
            rgba(59, 130, 246, .045),
            transparent 25%
        ),
        #f8fafc;

}

.staff-shell {

    width: min(1100px, 100%);

    margin: 0 auto;

}


/* =========================================================
   WELCOME
========================================================= */

.staff-welcome {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 25px;

    margin-bottom: 25px;

}

.welcome-content {

    min-width: 0;

}

.welcome-eyebrow {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: .1em;

}

.welcome-content h1 {

    margin: 0;

    color: #0f172a;

    font-size: 30px;

    line-height: 1.15;

    font-weight: 800;

    letter-spacing: -.7px;

}

.welcome-content p {

    max-width: 620px;

    margin: 8px 0 0;

    color: #64748b;

    font-size: 12px;

    line-height: 1.6;

}

.view-task-btn {

    min-height: 42px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    flex-shrink: 0;

    padding: 0 14px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 10px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    box-shadow:
        0 8px 20px rgba(37, 99, 235, .18);

    transition: .25s ease;

}

.view-task-btn:hover {

    color: #fff;

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-2px);

    box-shadow:
        0 12px 25px rgba(37, 99, 235, .24);

}

.view-task-btn > svg:first-child {

    width: 16px;

}

.view-task-btn .arrow-icon {

    width: 14px;

}


/* =========================================================
   STATS
========================================================= */

.staff-stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 13px;

    margin-bottom: 20px;

}

.staff-stat-card {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 0;

    padding: 16px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 13px;

    box-shadow:
        0 6px 22px rgba(15, 23, 42, .035);

    transition:
        transform .25s ease,
        box-shadow .25s ease;

}

.staff-stat-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 28px rgba(15, 23, 42, .06);

}

.staff-stat-icon {

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 11px;

}

.staff-stat-icon svg {

    width: 19px;
    height: 19px;

}

.staff-stat-icon.blue {

    color: #2563eb;

    background: #eff6ff;

}

.staff-stat-icon.amber {

    color: #b45309;

    background: #fffbeb;

}

.staff-stat-icon.violet {

    color: #7c3aed;

    background: #f5f3ff;

}

.staff-stat-icon.green {

    color: #15803d;

    background: #f0fdf4;

}

.staff-stat-content {

    min-width: 0;

}

.staff-stat-content > span {

    display: block;

    margin-bottom: 3px;

    color: #64748b;

    font-size: 9px;

}

.staff-stat-content strong {

    display: block;

    color: #0f172a;

    font-size: 21px;

    line-height: 1;

    font-weight: 800;

}

.staff-stat-content small {

    display: block;

    overflow: hidden;

    margin-top: 4px;

    color: #a0aec0;

    font-size: 7px;

    line-height: 1.2;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   MAIN GRID
========================================================= */

.staff-main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        315px;

    gap: 20px;

    margin-bottom: 20px;

}


/* =========================================================
   TASK CARD
========================================================= */

.staff-task-card {

    min-width: 0;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 16px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

    overflow: hidden;

}

.section-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 19px 20px;

    border-bottom: 1px solid #eef2f7;

}

.section-heading > div {

    min-width: 0;

}

.section-heading > div > span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .08em;

}

.section-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 15px;

    line-height: 1.2;

    font-weight: 800;

}

.section-heading.small {

    padding: 0 0 15px;

    border-bottom: none;

}

.see-all-btn {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    flex-shrink: 0;

    color: #2563eb;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

}

.see-all-btn:hover {

    color: #1d4ed8;

}

.see-all-btn svg {

    width: 13px;
    height: 13px;

}


/* =========================================================
   TASK LIST
========================================================= */

.task-list {

    display: flex;

    flex-direction: column;

}

.task-item {

    display: grid;

    grid-template-columns: 54px minmax(0, 1fr) 32px;

    align-items: center;

    gap: 12px;

    min-height: 106px;

    padding: 13px 20px;

    border-bottom: 1px solid #eef2f7;

    transition:
        background .2s ease;

}

.task-item:last-child {

    border-bottom: none;

}

.task-item:hover {

    background: #fbfdff;

}

.task-item-icon {

    width: 54px;
    height: 54px;

    display: flex;

    align-items: center;
    justify-content: center;

    overflow: hidden;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 11px;

}

.task-item-icon img {

    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;

}

.task-item-icon svg {

    width: 22px;
    height: 22px;

}

.task-item-content {

    min-width: 0;

}

.task-item-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 8px;

    margin-bottom: 5px;

}

.task-number {

    display: block;

    overflow: hidden;

    color: #94a3b8;

    font-size: 8px;

    font-weight: 700;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.task-status {

    min-height: 23px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 4px;

    padding: 0 7px;

    flex-shrink: 0;

    border-radius: 6px;

    font-size: 7px;

    font-weight: 800;

    line-height: 1;

}

.task-status svg {

    width: 11px;
    height: 11px;

}

.task-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}

.task-status.processing {

    color: #6d28d9;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}

.task-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}

.task-item-content h3 {

    overflow: hidden;

    margin: 0;

    color: #334155;

    font-size: 11px;

    font-weight: 800;

    line-height: 1.25;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.task-location {

    display: flex;

    align-items: center;

    gap: 4px;

    margin-top: 5px;

    color: #64748b;

}

.task-location svg {

    width: 11px;
    height: 11px;

    flex-shrink: 0;

    color: #2563eb;

}

.task-location span {

    overflow: hidden;

    font-size: 8px;

    line-height: 1.3;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.task-meta {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-top: 6px;

}

.task-meta span {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    overflow: hidden;

    color: #94a3b8;

    font-size: 7px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.task-meta svg {

    width: 10px;
    height: 10px;

    flex-shrink: 0;

}

.task-detail-btn {

    width: 32px;
    height: 32px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #64748b;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 8px;

    text-decoration: none;

    transition: .2s ease;

}

.task-detail-btn:hover {

    color: #2563eb;

    background: #eff6ff;

    border-color: #bfdbfe;

    transform: translateX(2px);

}

.task-detail-btn svg {

    width: 15px;
    height: 15px;

}


/* =========================================================
   EMPTY TASK
========================================================= */

.task-empty {

    min-height: 300px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;

}

.task-empty-icon {

    width: 58px;
    height: 58px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 12px;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 16px;

}

.task-empty-icon svg {

    width: 27px;
    height: 27px;

}

.task-empty h3 {

    margin: 0;

    color: #334155;

    font-size: 14px;

    font-weight: 800;

}

.task-empty p {

    max-width: 300px;

    margin: 6px 0 15px;

    color: #94a3b8;

    font-size: 9px;

    line-height: 1.6;

}

.empty-task-btn {

    min-height: 34px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 6px;

    padding: 0 12px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

}

.empty-task-btn svg {

    width: 13px;
    height: 13px;

}


/* =========================================================
   SIDEBAR
========================================================= */

.staff-sidebar {

    display: flex;

    flex-direction: column;

    gap: 14px;

}


/* =========================================================
   PROFILE CARD
========================================================= */

.staff-profile-card {

    padding: 18px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

}

.profile-card-top {

    display: flex;

    align-items: center;

    gap: 10px;

}

.profile-avatar {

    width: 43px;
    height: 43px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 12px;

}

.profile-avatar svg {

    width: 21px;
    height: 21px;

}

.profile-card-top span {

    display: block;

    margin-bottom: 3px;

    color: #94a3b8;

    font-size: 8px;

}

.profile-card-top strong {

    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 11px;

    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.profile-divider {

    height: 1px;

    margin: 15px 0;

    background: #eef2f7;

}

.profile-detail {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 9px;

    color: #64748b;

}

.profile-detail svg {

    width: 13px;
    height: 13px;

    flex-shrink: 0;

    color: #94a3b8;

}

.profile-detail span {

    overflow: hidden;

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.profile-link {

    min-height: 33px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-top: 12px;

    padding: 0 10px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

}

.profile-link:hover {

    background: #dbeafe;

}

.profile-link svg {

    width: 13px;
    height: 13px;

}


/* =========================================================
   PROGRESS CARD
========================================================= */

.progress-card {

    padding: 18px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

}

.progress-summary {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 10px;

}

.progress-summary strong {

    color: #0f172a;

    font-size: 26px;

    line-height: 1;

    font-weight: 800;

}

.progress-summary span {

    margin-left: 4px;

    color: #94a3b8;

    font-size: 8px;

}

.progress-count {

    color: #64748b;

    font-size: 9px;

    font-weight: 700;

}

.progress-track {

    width: 100%;
    height: 7px;

    overflow: hidden;

    margin-top: 12px;

    background: #e2e8f0;

    border-radius: 20px;

}

.progress-fill {

    height: 100%;

    min-width: 0;

    background: #2563eb;

    border-radius: inherit;

}

.progress-caption {

    margin-top: 7px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.5;

}


/* =========================================================
   COMPLETED CARD
========================================================= */

.completed-card {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    padding: 16px;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

    border-radius: 15px;

}

.completed-icon {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #15803d;

    background: #dcfce7;

    border-radius: 9px;

}

.completed-icon svg {

    width: 18px;
    height: 18px;

}

.completed-content {

    min-width: 0;

}

.completed-content > span {

    display: block;

    margin-bottom: 4px;

    color: #65a374;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .06em;

}

.completed-content strong {

    display: block;

    overflow: hidden;

    color: #166534;

    font-size: 10px;

    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.completed-content p {

    overflow: hidden;

    margin: 4px 0 0;

    color: #4d7c5b;

    font-size: 8px;

    line-height: 1.45;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.completed-content small {

    display: block;

    margin-top: 4px;

    color: #65a374;

    font-size: 7px;

}


/* =========================================================
   QUICK MENU
========================================================= */

.quick-section {

    margin-top: 20px;

}

.quick-section > .section-heading {

    padding: 0 0 13px;

    border-bottom: none;

}

.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 13px;

}

.quick-card {

    position: relative;

    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;

    padding: 15px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 13px;

    text-decoration: none;

    box-shadow:
        0 6px 22px rgba(15, 23, 42, .035);

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        border-color .25s ease;

}

.quick-card:hover {

    transform: translateY(-2px);

    border-color: #dbeafe;

    box-shadow:
        0 12px 28px rgba(15, 23, 42, .06);

}

.quick-icon {

    width: 38px;
    height: 38px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 10px;

}

.quick-icon svg {

    width: 18px;
    height: 18px;

}

.quick-icon.blue {

    color: #2563eb;

    background: #eff6ff;

}

.quick-icon.green {

    color: #15803d;

    background: #f0fdf4;

}

.quick-icon.violet {

    color: #7c3aed;

    background: #f5f3ff;

}

.quick-card > div:nth-child(2) {

    min-width: 0;

}

.quick-card strong {

    display: block;

    color: #334155;

    font-size: 10px;

    font-weight: 800;

}

.quick-card span {

    display: block;

    overflow: hidden;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.4;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.quick-arrow {

    width: 14px;
    height: 14px;

    margin-left: auto;

    flex-shrink: 0;

    color: #94a3b8;

    transition: .2s ease;

}

.quick-card:hover .quick-arrow {

    color: #2563eb;

    transform: translate(2px, -2px);

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 950px) {

    .staff-main-grid {

        grid-template-columns:
            minmax(0, 1fr);

    }

    .staff-sidebar {

        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        align-items: stretch;

    }

}


/* =========================================================
   TABLET SMALL
========================================================= */

@media (max-width: 800px) {

    .staff-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .staff-sidebar {

        grid-template-columns:
            1fr 1fr;

    }

    .completed-card {

        grid-column: 1 / -1;

    }

    .quick-grid {

        grid-template-columns:
            1fr;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .staff-dashboard {

        padding: 28px 14px 50px;

    }

    .staff-welcome {

        align-items: flex-start;

        flex-direction: column;

        gap: 16px;

    }

    .welcome-content h1 {

        font-size: 27px;

    }

    .view-task-btn {

        width: 100%;

        box-sizing: border-box;

    }

    .staff-sidebar {

        display: flex;

        flex-direction: column;

    }

    .task-item {

        grid-template-columns:
            48px minmax(0, 1fr) 30px;

        gap: 10px;

        padding: 12px 14px;

    }

    .task-item-icon {

        width: 48px;
        height: 48px;

    }

    .task-item-top {

        align-items: flex-start;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .staff-dashboard {

        padding: 24px 12px 45px;

    }

    .staff-stats {

        gap: 9px;

    }

    .staff-stat-card {

        padding: 12px;

    }

    .staff-stat-icon {

        width: 34px;
        height: 34px;

    }

    .staff-stat-icon svg {

        width: 16px;
        height: 16px;

    }

    .staff-stat-content strong {

        font-size: 18px;

    }

    .staff-stat-content > span {

        font-size: 8px;

    }

    .staff-stat-content small {

        display: none;

    }

    .section-heading {

        padding: 16px 14px;

    }

    .task-item {

        grid-template-columns:
            1fr 30px;

        gap: 10px;

    }

    .task-item-icon {

        display: none;

    }

    .task-item-content {

        grid-column: 1;

    }

    .task-detail-btn {

        grid-column: 2;

        grid-row: 1;

    }

    .task-item-top {

        padding-right: 3px;

    }

    .task-meta {

        flex-wrap: wrap;

        gap: 7px;

    }

    .staff-profile-card,
    .progress-card {

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

                lenis.raf(time * 1000);

                requestAnimationFrame(
                    raf
                );

            }


            requestAnimationFrame(raf);

        }



        /* =====================================================
           GSAP
        ===================================================== */

        if (window.gsap) {

            gsap.from(
                ".staff-welcome",
                {
                    y: 20,
                    opacity: 0,
                    duration: .6,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".staff-stat-card",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .07,
                    delay: .08,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".staff-task-card",
                {
                    y: 18,
                    opacity: 0,
                    duration: .55,
                    delay: .18,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".staff-sidebar > *",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .08,
                    delay: .25,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".quick-card",
                {
                    y: 15,
                    opacity: 0,
                    duration: .45,
                    stagger: .07,
                    delay: .35,
                    ease: "power3.out"
                }
            );

        }



        /* =====================================================
           NAVBAR SCROLL
        ===================================================== */

        const navbar =
            document.querySelector(".navbar");


        if (navbar) {

            function updateNavbar() {

                if (window.scrollY > 25) {

                    navbar.classList.add("scrolled");

                } else {

                    navbar.classList.remove("scrolled");

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
           TASK DETAIL HOVER
        ===================================================== */

        document
            .querySelectorAll(".task-detail-btn")
            .forEach(function (button) {

                button.addEventListener(
                    "mouseenter",
                    function () {

                        const icon =
                            this.querySelector("svg");

                        if (
                            icon &&
                            window.gsap
                        ) {

                            gsap.to(
                                icon,
                                {
                                    x: 2,
                                    duration: .2,
                                    ease: "power2.out"
                                }
                            );

                        }

                    }
                );


                button.addEventListener(
                    "mouseleave",
                    function () {

                        const icon =
                            this.querySelector("svg");

                        if (
                            icon &&
                            window.gsap
                        ) {

                            gsap.to(
                                icon,
                                {
                                    x: 0,
                                    duration: .2,
                                    ease: "power2.out"
                                }
                            );

                        }

                    }
                );

            });



        /* =====================================================
           QUICK CARD HOVER
        ===================================================== */

        document
            .querySelectorAll(".quick-card")
            .forEach(function (card) {

                card.addEventListener(
                    "mouseenter",
                    function () {

                        const arrow =
                            this.querySelector(".quick-arrow");

                        if (
                            arrow &&
                            window.gsap
                        ) {

                            gsap.to(
                                arrow,
                                {
                                    x: 2,
                                    y: -2,
                                    duration: .2,
                                    ease: "power2.out"
                                }
                            );

                        }

                    }
                );


                card.addEventListener(
                    "mouseleave",
                    function () {

                        const arrow =
                            this.querySelector(".quick-arrow");

                        if (
                            arrow &&
                            window.gsap
                        ) {

                            gsap.to(
                                arrow,
                                {
                                    x: 0,
                                    y: 0,
                                    duration: .2,
                                    ease: "power2.out"
                                }
                            );

                        }

                    }
                );

            });

    }
);

</script>


</body>

</html>

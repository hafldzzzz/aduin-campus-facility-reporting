<?php
require_once dirname(__DIR__) . '/config/database.php';
$user = requireRole('pengguna');
$id_user = (int)$user['id_user'];
$stats = dbOne($conn, "SELECT COUNT(*) AS total,SUM(status='Menunggu') AS menunggu,SUM(status='Diproses') AS diproses,SUM(status='Selesai') AS selesai FROM pengaduan WHERE id_user = ?", 'i', [$id_user]);
$totalPengaduan = (int)($stats['total'] ?? 0);
$totalMenunggu = (int)($stats['menunggu'] ?? 0);
$totalDiproses = (int)($stats['diproses'] ?? 0);
$totalSelesai = (int)($stats['selesai'] ?? 0);
$recentResult = dbQuery($conn, 'SELECT p.id_pengaduan,p.no_laporan,p.lokasi,p.status,p.tanggal_lapor,k.nama_kategori FROM pengaduan p INNER JOIN kategori k ON p.id_kategori=k.id_kategori WHERE p.id_user = ? ORDER BY p.tanggal_lapor DESC LIMIT 5', 'i', [$id_user]);
$hour = (int)date('H');
$greeting = $hour >= 5 && $hour < 11 ? 'Selamat pagi' : ($hour >= 11 && $hour < 15 ? 'Selamat siang' : ($hour >= 15 && $hour < 18 ? 'Selamat sore' : 'Selamat malam'));
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
        Dashboard | ADUIN
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

    <!-- Lucide -->
    <script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lucide@latest"></script>

    <!-- GSAP -->
    <script nonce="<?= e(cspNonce()) ?>" src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js"></script>

    <script nonce="<?= e(cspNonce()) ?>" src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/ScrollTrigger.min.js"></script>

    <!-- Lenis -->
    <script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"></script>

</head>


<body>


<?php

$basePath = '../';

$activePage = 'dashboard';

include '../includes/navbar.php';

?>


<main class="user-dashboard-page">


    <!-- =========================
         WELCOME
    ========================== -->

    <section class="dashboard-welcome">

        <div class="dashboard-container">

            <div class="welcome-content">

                <div>

                    <span class="welcome-eyebrow">
                        DASHBOARD PENGGUNA
                    </span>

                    <h1>

                        <?= $greeting; ?>,
                        <span>
                            <?= htmlspecialchars($user['nama']); ?>
                        </span>

                    </h1>

                    <p>
                        Pantau pengaduan fasilitas kampusmu dengan mudah melalui ADUIN.
                    </p>

                </div>


                <a
                    href="buat-pengaduan.php"
                    class="create-report-btn"
                >

                    <i data-lucide="plus"></i>

                    <span>
                        Buat Pengaduan
                    </span>

                </a>

            </div>

        </div>

    </section>



    <!-- =========================
         CONTENT
    ========================== -->

    <section class="dashboard-content">

        <div class="dashboard-container">


            <!-- =========================
                 STATISTIK
            ========================== -->

            <div class="dashboard-stats">


                <!-- TOTAL -->

                <div class="dashboard-stat-card">

                    <div class="dashboard-stat-icon blue">

                        <i data-lucide="files"></i>

                    </div>

                    <div class="dashboard-stat-info">

                        <span>
                            Total Pengaduan
                        </span>

                        <strong>
                            <?= $totalPengaduan; ?>
                        </strong>

                    </div>

                </div>


                <!-- MENUNGGU -->

                <div class="dashboard-stat-card">

                    <div class="dashboard-stat-icon orange">

                        <i data-lucide="clock-3"></i>

                    </div>

                    <div class="dashboard-stat-info">

                        <span>
                            Menunggu
                        </span>

                        <strong>
                            <?= $totalMenunggu; ?>
                        </strong>

                    </div>

                </div>


                <!-- DIPROSES -->

                <div class="dashboard-stat-card">

                    <div class="dashboard-stat-icon purple">

                        <i data-lucide="loader-circle"></i>

                    </div>

                    <div class="dashboard-stat-info">

                        <span>
                            Diproses
                        </span>

                        <strong>
                            <?= $totalDiproses; ?>
                        </strong>

                    </div>

                </div>


                <!-- SELESAI -->

                <div class="dashboard-stat-card">

                    <div class="dashboard-stat-icon green">

                        <i data-lucide="circle-check"></i>

                    </div>

                    <div class="dashboard-stat-info">

                        <span>
                            Selesai
                        </span>

                        <strong>
                            <?= $totalSelesai; ?>
                        </strong>

                    </div>

                </div>


            </div>



            <!-- =========================
                 GRID
            ========================== -->

            <div class="dashboard-grid">


                <!-- =========================
                     PENGADUAN TERBARU
                ========================== -->

                <div class="dashboard-card recent-card">


                    <div class="dashboard-card-header">

                        <div>

                            <h2>
                                Pengaduan Terbaru
                            </h2>

                            <p>
                                Lima laporan terakhir yang kamu buat.
                            </p>

                        </div>


                        <a
                            href="riwayat.php"
                            class="view-all-btn"
                        >

                            Lihat Semua

                            <i data-lucide="arrow-right"></i>

                        </a>

                    </div>



                    <div class="recent-list">


                    <?php if (
                        mysqli_num_rows($recentResult) > 0
                    ): ?>


                        <?php while (
                            $row =
                                mysqli_fetch_assoc(
                                    $recentResult
                                )
                        ): ?>


                            <?php

                            $statusClass =
                                'waiting';

                            if (
                                $row['status']
                                === 'Diproses'
                            ) {
                                $statusClass =
                                    'processing';
                            } elseif (
                                $row['status']
                                === 'Selesai'
                            ) {
                                $statusClass =
                                    'completed';
                            }

                            ?>


                            <div class="recent-item">


                                <div class="recent-icon">

                                    <?php

                                    $icon =
                                        'file-text';

                                    $kategori =
                                        strtolower(
                                            $row[
                                                'nama_kategori'
                                            ]
                                        );

                                    if (
                                        strpos(
                                            $kategori,
                                            'listrik'
                                        ) !== false
                                    ) {
                                        $icon =
                                            'zap';
                                    } elseif (
                                        strpos(
                                            $kategori,
                                            'ac'
                                        ) !== false
                                    ) {
                                        $icon =
                                            'snowflake';
                                    } elseif (
                                        strpos(
                                            $kategori,
                                            'toilet'
                                        ) !== false
                                    ) {
                                        $icon =
                                            'bath';
                                    } elseif (
                                        strpos(
                                            $kategori,
                                            'jaringan'
                                        ) !== false
                                    ) {
                                        $icon =
                                            'wifi';
                                    } elseif (
                                        strpos(
                                            $kategori,
                                            'kelas'
                                        ) !== false
                                    ) {
                                        $icon =
                                            'school';
                                    }

                                    ?>

                                    <i
                                        data-lucide="<?= $icon; ?>"
                                    ></i>

                                </div>


                                <div class="recent-info">

                                    <div class="recent-top">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row['no_laporan']
                                            ); ?>
                                        </strong>

                                        <span
                                            class="user-status <?= $statusClass; ?>"
                                        >

                                            <span
                                                class="status-dot"
                                            ></span>

                                            <?= htmlspecialchars(
                                                $row['status']
                                            ); ?>

                                        </span>

                                    </div>


                                    <div class="recent-middle">

                                        <span>
                                            <?= htmlspecialchars(
                                                $row['nama_kategori']
                                            ); ?>
                                        </span>

                                        <span class="separator">
                                            •
                                        </span>

                                        <span>

                                            <?= htmlspecialchars(
                                                $row['lokasi']
                                            ); ?>

                                        </span>

                                    </div>


                                    <small>

                                        <?= date(
                                            'd M Y, H:i',
                                            strtotime(
                                                $row[
                                                    'tanggal_lapor'
                                                ]
                                            )
                                        ); ?>

                                    </small>

                                </div>


                            </div>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <div class="dashboard-empty">

                            <div class="dashboard-empty-icon">

                                <i data-lucide="inbox"></i>

                            </div>

                            <strong>
                                Belum Ada Pengaduan
                            </strong>

                            <span>
                                Kamu belum membuat laporan pengaduan.
                            </span>

                            <a
                                href="buat-pengaduan.php"
                            >
                                Buat Pengaduan
                            </a>

                        </div>


                    <?php endif; ?>


                    </div>

                </div>



                <!-- =========================
                     QUICK ACTION
                ========================== -->

                <div class="dashboard-side">


                    <div class="dashboard-card quick-card">


                        <div class="dashboard-card-header">

                            <div>

                                <h2>
                                    Aksi Cepat
                                </h2>

                                <p>
                                    Akses fitur yang sering digunakan.
                                </p>

                            </div>

                        </div>


                        <div class="quick-actions">


                            <a
                                href="buat-pengaduan.php"
                                class="quick-action primary"
                            >

                                <div class="quick-action-icon">

                                    <i data-lucide="file-plus-2"></i>

                                </div>

                                <div>

                                    <strong>
                                        Buat Pengaduan
                                    </strong>

                                    <span>
                                        Laporkan fasilitas bermasalah
                                    </span>

                                </div>

                                <i
                                    data-lucide="chevron-right"
                                    class="quick-arrow"
                                ></i>

                            </a>


                            <a
                                href="riwayat.php"
                                class="quick-action"
                            >

                                <div class="quick-action-icon">

                                    <i data-lucide="history"></i>

                                </div>

                                <div>

                                    <strong>
                                        Riwayat Pengaduan
                                    </strong>

                                    <span>
                                        Lihat semua laporan kamu
                                    </span>

                                </div>

                                <i
                                    data-lucide="chevron-right"
                                    class="quick-arrow"
                                ></i>

                            </a>


                        </div>


                    </div>



                    <!-- =========================
                         INFO
                    ========================== -->

                    <div class="dashboard-info-card">

                        <div class="info-card-icon">

                            <i data-lucide="info"></i>

                        </div>


                        <div>

                            <strong>
                                Butuh bantuan?
                            </strong>

                            <p>
                                Laporkan fasilitas kampus yang rusak atau bermasalah melalui form pengaduan.
                            </p>

                        </div>

                    </div>


                </div>

            </div>

        </div>

    </section>

</main>



<style>

/* =====================================================
   USER DASHBOARD
===================================================== */

.user-dashboard-page {
    min-height: calc(100vh - 76px);
    background: #f8fafc;
}

.dashboard-container {
    width: min(92%, 1160px);
    margin: 0 auto;
}


/* =====================================================
   WELCOME
===================================================== */

.dashboard-welcome {
    padding: 40px 0 32px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.welcome-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

.welcome-eyebrow {
    display: block;
    margin-bottom: 7px;
    color: #2563eb;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
}

.welcome-content h1 {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.7px;
}

.welcome-content h1 span {
    color: #2563eb;
}

.welcome-content p {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 12px;
}

.create-report-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    padding: 0 16px;
    color: #ffffff;
    background: #2563eb;
    border-radius: 10px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    box-shadow: 0 8px 20px rgba(37, 99, 235, .17);
    transition: .25s ease;
}

.create-report-btn svg {
    width: 17px;
    height: 17px;
}

.create-report-btn:hover {
    color: #ffffff;
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(37, 99, 235, .23);
}


/* =====================================================
   CONTENT
===================================================== */

.dashboard-content {
    padding: 28px 0 55px;
}


/* =====================================================
   STATS
===================================================== */

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.dashboard-stat-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
}

.dashboard-stat-icon {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    flex-shrink: 0;
}

.dashboard-stat-icon svg {
    width: 20px;
    height: 20px;
}

.dashboard-stat-icon.blue {
    color: #2563eb;
    background: #eff6ff;
}

.dashboard-stat-icon.orange {
    color: #ea580c;
    background: #fff7ed;
}

.dashboard-stat-icon.purple {
    color: #7c3aed;
    background: #f5f3ff;
}

.dashboard-stat-icon.green {
    color: #16a34a;
    background: #f0fdf4;
}

.dashboard-stat-info {
    display: flex;
    flex-direction: column;
}

.dashboard-stat-info span {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.dashboard-stat-info strong {
    margin-top: 3px;
    color: #0f172a;
    font-size: 23px;
    font-weight: 800;
}


/* =====================================================
   GRID
===================================================== */

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 20px;
    align-items: start;
}

.dashboard-side {
    display: flex;
    flex-direction: column;
    gap: 18px;
}


/* =====================================================
   CARD
===================================================== */

.dashboard-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 17px;
    box-shadow: 0 8px 25px rgba(15, 23, 42, .04);
}

.dashboard-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 21px;
    border-bottom: 1px solid #e2e8f0;
}

.dashboard-card-header h2 {
    margin: 0;
    color: #0f172a;
    font-size: 15px;
    font-weight: 800;
}

.dashboard-card-header p {
    margin: 4px 0 0;
    color: #94a3b8;
    font-size: 10px;
}

.view-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #2563eb;
    text-decoration: none;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.view-all-btn svg {
    width: 14px;
    height: 14px;
    transition: transform .2s ease;
}

.view-all-btn:hover svg {
    transform: translateX(3px);
}


/* =====================================================
   RECENT LIST
===================================================== */

.recent-list {
    display: flex;
    flex-direction: column;
}

.recent-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 21px;
    border-bottom: 1px solid #eef2f7;
    transition: background .2s ease;
}

.recent-item:last-child {
    border-bottom: none;
}

.recent-item:hover {
    background: #f8fbff;
}

.recent-icon {
    width: 37px;
    height: 37px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 10px;
    flex-shrink: 0;
}

.recent-icon svg {
    width: 17px;
    height: 17px;
}

.recent-info {
    flex: 1;
    min-width: 0;
}

.recent-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.recent-top strong {
    color: #1e293b;
    font-size: 11px;
    font-weight: 700;
}

.user-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 7px;
    border-radius: 6px;
    font-size: 8px;
    font-weight: 700;
    white-space: nowrap;
}

.user-status.waiting {
    color: #b45309;
    background: #fffbeb;
}

.user-status.processing {
    color: #6d28d9;
    background: #f5f3ff;
}

.user-status.completed {
    color: #15803d;
    background: #f0fdf4;
}

.user-status .status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.user-status.waiting .status-dot {
    background: #f59e0b;
}

.user-status.processing .status-dot {
    background: #8b5cf6;
}

.user-status.completed .status-dot {
    background: #22c55e;
}

.recent-middle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    color: #64748b;
    font-size: 9px;
}

.recent-middle span:not(.separator) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recent-middle span:last-child {
    max-width: 220px;
}

.separator {
    color: #cbd5e1;
}

.recent-info small {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 8px;
}


/* =====================================================
   QUICK ACTION
===================================================== */

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 15px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    color: inherit;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    text-decoration: none;
    transition: .22s ease;
}

.quick-action:hover {
    border-color: #bfdbfe;
    background: #f8fbff;
    transform: translateY(-1px);
}

.quick-action.primary {
    color: #ffffff;
    background: #2563eb;
    border-color: #2563eb;
}

.quick-action.primary:hover {
    color: #ffffff;
    background: #1d4ed8;
    border-color: #1d4ed8;
}

.quick-action-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 9px;
    flex-shrink: 0;
}

.quick-action-icon svg {
    width: 17px;
    height: 17px;
}

.quick-action.primary .quick-action-icon {
    color: #ffffff;
    background: rgba(255,255,255,.15);
}

.quick-action > div:nth-child(2) {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.quick-action strong {
    font-size: 10px;
    font-weight: 700;
}

.quick-action span {
    margin-top: 3px;
    color: #94a3b8;
    font-size: 8px;
    line-height: 1.3;
}

.quick-action.primary span {
    color: rgba(255,255,255,.75);
}

.quick-arrow {
    width: 15px;
    height: 15px;
    margin-left: auto;
    color: #94a3b8;
    flex-shrink: 0;
}

.quick-action.primary .quick-arrow {
    color: rgba(255,255,255,.8);
}


/* =====================================================
   INFO CARD
===================================================== */

.dashboard-info-card {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 17px;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 15px;
}

.info-card-icon {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #ffffff;
    border-radius: 9px;
    flex-shrink: 0;
}

.info-card-icon svg {
    width: 17px;
    height: 17px;
}

.dashboard-info-card strong {
    color: #1e40af;
    font-size: 11px;
}

.dashboard-info-card p {
    margin: 4px 0 0;
    color: #3b82f6;
    font-size: 9px;
    line-height: 1.55;
}


/* =====================================================
   EMPTY
===================================================== */

.dashboard-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 45px 20px;
    text-align: center;
}

.dashboard-empty-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 11px;
    color: #94a3b8;
    background: #f1f5f9;
    border-radius: 13px;
}

.dashboard-empty-icon svg {
    width: 22px;
    height: 22px;
}

.dashboard-empty strong {
    color: #475569;
    font-size: 12px;
}

.dashboard-empty > span {
    margin-top: 4px;
    color: #94a3b8;
    font-size: 9px;
}

.dashboard-empty a {
    margin-top: 13px;
    padding: 8px 12px;
    color: #ffffff;
    background: #2563eb;
    border-radius: 8px;
    text-decoration: none;
    font-size: 9px;
    font-weight: 700;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .dashboard-container {
        width: calc(100% - 32px);
    }

    .dashboard-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 600px) {

    .dashboard-welcome {
        padding: 30px 0 24px;
    }

    .welcome-content {
        align-items: flex-start;
        flex-direction: column;
    }

    .welcome-content h1 {
        font-size: 23px;
    }

    .welcome-content p {
        font-size: 11px;
    }

    .create-report-btn {
        width: 100%;
    }

    .dashboard-content {
        padding-top: 20px;
    }

    .dashboard-card-header {
        padding: 17px;
    }

    .recent-item {
        padding: 14px 17px;
    }

}


@media (max-width: 430px) {

    .dashboard-container {
        width: calc(100% - 24px);
    }

    .welcome-content h1 {
        font-size: 21px;
    }

    .welcome-content p {
        font-size: 10px;
    }

    .dashboard-stats {
        gap: 10px;
    }

    .dashboard-stat-card {
        gap: 9px;
        padding: 12px;
    }

    .dashboard-stat-icon {
        width: 34px;
        height: 34px;
    }

    .dashboard-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .dashboard-stat-info span {
        font-size: 9px;
    }

    .dashboard-stat-info strong {
        font-size: 17px;
    }

    .recent-top {
        align-items: flex-start;
        flex-direction: column;
        gap: 5px;
    }

    .recent-middle {
        align-items: flex-start;
        flex-direction: column;
        gap: 2px;
    }

    .separator {
        display: none;
    }

    .recent-middle span:last-child {
        max-width: 100%;
    }

}


/* =====================================================
   ANIMATION
===================================================== */

.dashboard-welcome,
.dashboard-stats,
.dashboard-grid {
    opacity: 0;
}

.user-dashboard-page.loaded .dashboard-welcome,
.user-dashboard-page.loaded .dashboard-stats,
.user-dashboard-page.loaded .dashboard-grid {
    opacity: 1;
}

</style>



<script nonce="<?= e(cspNonce()) ?>">

/* =====================================================
   LUCIDE
===================================================== */

lucide.createIcons();


/* =====================================================
   LENIS
===================================================== */

const lenis = new Lenis({
    duration: 1.05,
    smoothWheel: true,
    syncTouch: false,
    wheelMultiplier: .9,
    touchMultiplier: 1.2
});


/* =====================================================
   SCROLLTRIGGER
===================================================== */

gsap.registerPlugin(ScrollTrigger);

lenis.on(
    "scroll",
    ScrollTrigger.update
);

gsap.ticker.add(function(time) {
    lenis.raf(time * 1000);
});

gsap.ticker.lagSmoothing(0);


/* =====================================================
   NAVBAR SCROLL
===================================================== */

window.addEventListener(
    "scroll",
    function() {

        const navbar =
            document.getElementById("navbar");

        if (!navbar) return;

        if (window.scrollY > 25) {

            navbar.classList.add(
                "scrolled"
            );

        } else {

            navbar.classList.remove(
                "scrolled"
            );

        }

    }
);


/* =====================================================
   PAGE ANIMATION
===================================================== */

window.addEventListener(
    "load",
    function() {

        const page =
            document.querySelector(
                ".user-dashboard-page"
            );

        if (page) {

            page.classList.add(
                "loaded"
            );

        }


        gsap.from(
            ".welcome-content",
            {
                opacity: 0,
                y: 18,
                duration: .7,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".dashboard-stat-card",
            {
                opacity: 0,
                y: 20,
                duration: .65,
                stagger: .08,
                delay: .15,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".dashboard-grid",
            {
                opacity: 0,
                y: 24,
                duration: .7,
                delay: .25,
                ease: "power3.out"
            }
        );

    }
);


/* =====================================================
   MOBILE MENU
===================================================== */

const mobileMenuBtn =
    document.getElementById(
        "mobileMenuBtn"
    );

const mobileMenu =
    document.getElementById(
        "mobileMenu"
    );


if (
    mobileMenuBtn &&
    mobileMenu
) {

    mobileMenuBtn.addEventListener(
        "click",
        function() {

            const isOpen =
                mobileMenu.classList.toggle(
                    "open"
                );


            mobileMenuBtn.setAttribute(
                "aria-expanded",
                isOpen
                    ? "true"
                    : "false"
            );


            mobileMenuBtn.innerHTML =
                isOpen
                    ? '<i data-lucide="x"></i>'
                    : '<i data-lucide="menu"></i>';


            lucide.createIcons();

        }
    );

}


/* =====================================================
   CLOSE MOBILE MENU
===================================================== */

document
    .querySelectorAll(
        ".mobile-menu-link"
    )
    .forEach(
        function(link) {

            link.addEventListener(
                "click",
                function() {

                    if (!mobileMenu) {
                        return;
                    }


                    mobileMenu.classList.remove(
                        "open"
                    );


                    if (mobileMenuBtn) {

                        mobileMenuBtn.setAttribute(
                            "aria-expanded",
                            "false"
                        );


                        mobileMenuBtn.innerHTML =
                            '<i data-lucide="menu"></i>';


                        lucide.createIcons();

                    }

                }
            );

        }
    );

</script>


</body>

</html>
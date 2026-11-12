<?php
require_once "../config/database.php";
requireRole('admin');

/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

$id_user = $_SESSION['user_id'] ?? $_SESSION['id_user'];


/* =========================
   DATA PETUGAS
========================= */

$query = "
    SELECT
        u.id_user,
        u.nama,
        u.email,
        u.no_wa,
        u.fakultas,
        u.created_at,

        COUNT(p.id_pengaduan) AS total_tugas,

        SUM(
            CASE
                WHEN p.status = 'Menunggu'
                THEN 1
                ELSE 0
            END
        ) AS tugas_menunggu,

        SUM(
            CASE
                WHEN p.status = 'Diproses'
                THEN 1
                ELSE 0
            END
        ) AS tugas_diproses,

        SUM(
            CASE
                WHEN p.status = 'Selesai'
                THEN 1
                ELSE 0
            END
        ) AS tugas_selesai

    FROM users u

    LEFT JOIN pengaduan p
        ON u.id_user = p.id_petugas

    WHERE u.role = 'petugas'

    GROUP BY
        u.id_user,
        u.nama,
        u.email,
        u.no_wa,
        u.fakultas,
        u.created_at

    ORDER BY u.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    throw new RuntimeException('Data petugas tidak dapat dimuat.');
}


/* =========================
   STATISTIK PETUGAS
========================= */

$qTotal = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role='petugas'"
);

$totalPetugas =
    mysqli_fetch_assoc($qTotal)['total'] ?? 0;


$qTugas = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE id_petugas IS NOT NULL"
);

$totalTugas =
    mysqli_fetch_assoc($qTugas)['total'] ?? 0;


$qDiproses = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE id_petugas IS NOT NULL
     AND status='Diproses'"
);

$totalDiproses =
    mysqli_fetch_assoc($qDiproses)['total'] ?? 0;


$qSelesai = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE id_petugas IS NOT NULL
     AND status='Selesai'"
);

$totalSelesai =
    mysqli_fetch_assoc($qSelesai)['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Petugas | ADUIN</title>

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

$activePage = 'petugas';

include '../includes/navbar.php';

?>


<main class="admin-petugas-page">


    <!-- =========================
         HEADER
    ========================== -->

    <section class="petugas-header">

        <div class="petugas-container">

            <div class="petugas-heading">

                <div class="heading-icon">

                    <i data-lucide="wrench"></i>

                </div>

                <div>

                    <span class="page-eyebrow">
                        ADMINISTRATOR
                    </span>

                    <h1>
                        Petugas
                    </h1>

                    <p>
                        Kelola petugas dan pantau tugas penanganan pengaduan.
                    </p>

                </div>

            </div>

        </div>

    </section>



    <!-- =========================
         CONTENT
    ========================== -->

    <section class="petugas-content">

        <div class="petugas-container">


            <!-- =========================
                 STATISTIK
            ========================== -->

            <div class="petugas-stats">


                <div class="petugas-stat-card">

                    <div class="petugas-stat-icon blue">

                        <i data-lucide="users"></i>

                    </div>

                    <div class="petugas-stat-info">

                        <span>
                            Total Petugas
                        </span>

                        <strong>
                            <?= $totalPetugas; ?>
                        </strong>

                    </div>

                </div>


                <div class="petugas-stat-card">

                    <div class="petugas-stat-icon purple">

                        <i data-lucide="clipboard-list"></i>

                    </div>

                    <div class="petugas-stat-info">

                        <span>
                            Total Tugas
                        </span>

                        <strong>
                            <?= $totalTugas; ?>
                        </strong>

                    </div>

                </div>


                <div class="petugas-stat-card">

                    <div class="petugas-stat-icon orange">

                        <i data-lucide="loader-circle"></i>

                    </div>

                    <div class="petugas-stat-info">

                        <span>
                            Sedang Diproses
                        </span>

                        <strong>
                            <?= $totalDiproses; ?>
                        </strong>

                    </div>

                </div>


                <div class="petugas-stat-card">

                    <div class="petugas-stat-icon green">

                        <i data-lucide="circle-check"></i>

                    </div>

                    <div class="petugas-stat-info">

                        <span>
                            Tugas Selesai
                        </span>

                        <strong>
                            <?= $totalSelesai; ?>
                        </strong>

                    </div>

                </div>


            </div>



            <!-- =========================
                 CARD
            ========================== -->

            <div class="petugas-card">


                <!-- CARD HEADER -->

                <div class="petugas-card-header">

                    <div>

                        <h2>
                            Daftar Petugas
                        </h2>

                        <p>
                            Petugas yang terdaftar dan tugas yang ditangani.
                        </p>

                    </div>

                    <div class="petugas-total-badge">

                        <?= $totalPetugas; ?> petugas

                    </div>

                </div>



                <!-- =========================
                     FILTER
                ========================== -->

                <div class="petugas-filter">


                    <div class="petugas-search">

                        <i data-lucide="search"></i>

                        <input
                            type="text"
                            id="searchPetugas"
                            placeholder="Cari nama, email, fakultas..."
                        >

                    </div>


                    <div class="task-filter">

                        <i data-lucide="filter"></i>

                        <select id="taskFilter">

                            <option value="">
                                Semua Petugas
                            </option>

                            <option value="aktif">
                                Ada Tugas
                            </option>

                            <option value="kosong">
                                Belum Ada Tugas
                            </option>

                            <option value="diproses">
                                Sedang Diproses
                            </option>

                            <option value="selesai">
                                Ada Tugas Selesai
                            </option>

                        </select>

                    </div>

                </div>



                <!-- =========================
                     TABLE
                ========================== -->

                <div class="petugas-table-wrapper">

                    <table class="petugas-table">

                        <thead>

                            <tr>

                                <th>
                                    Petugas
                                </th>

                                <th>
                                    Kontak
                                </th>

                                <th>
                                    Fakultas
                                </th>

                                <th>
                                    Total Tugas
                                </th>

                                <th>
                                    Diproses
                                </th>

                                <th>
                                    Selesai
                                </th>

                                <th>
                                    Terdaftar
                                </th>

                            </tr>

                        </thead>


                        <tbody id="petugasTableBody">


                        <?php if (mysqli_num_rows($result) > 0): ?>


                            <?php while (
                                $row =
                                    mysqli_fetch_assoc($result)
                            ): ?>


                                <?php

                                $initial =
                                    strtoupper(
                                        substr(
                                            trim(
                                                $row['nama']
                                            ),
                                            0,
                                            1
                                        )
                                    );

                                $totalTugasPetugas =
                                    (int) (
                                        $row['total_tugas']
                                        ?? 0
                                    );

                                $tugasDiproses =
                                    (int) (
                                        $row['tugas_diproses']
                                        ?? 0
                                    );

                                $tugasSelesai =
                                    (int) (
                                        $row['tugas_selesai']
                                        ?? 0
                                    );

                                $taskType =
                                    $totalTugasPetugas > 0
                                        ? 'aktif'
                                        : 'kosong';

                                $searchText =
                                    strtolower(
                                        $row['nama'] .
                                        ' ' .
                                        $row['email'] .
                                        ' ' .
                                        ($row['fakultas'] ?? '')
                                    );

                                ?>


                                <tr

                                    data-search="<?= htmlspecialchars(
                                        $searchText
                                    ); ?>"

                                    data-task="<?= $taskType; ?>"

                                    data-processing="<?= $tugasDiproses; ?>"

                                    data-completed="<?= $tugasSelesai; ?>"

                                >


                                    <!-- PETUGAS -->

                                    <td>

                                        <div class="petugas-profile">

                                            <div class="petugas-avatar">

                                                <?= htmlspecialchars(
                                                    $initial
                                                ); ?>

                                            </div>


                                            <div class="petugas-profile-info">

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $row['nama']
                                                    ); ?>

                                                </strong>

                                                <small>

                                                    ID #<?= $row['id_user']; ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>



                                    <!-- KONTAK -->

                                    <td>

                                        <div class="petugas-contact">

                                            <div>

                                                <i data-lucide="mail"></i>

                                                <span>

                                                    <?= htmlspecialchars(
                                                        $row['email']
                                                    ); ?>

                                                </span>

                                            </div>


                                            <?php if (
                                                !empty(
                                                    $row['no_wa']
                                                )
                                            ): ?>

                                                <div>

                                                    <i data-lucide="phone"></i>

                                                    <span>

                                                        <?= htmlspecialchars(
                                                            $row['no_wa']
                                                        ); ?>

                                                    </span>

                                                </div>

                                            <?php else: ?>

                                                <div class="no-contact">

                                                    <i data-lucide="phone-off"></i>

                                                    <span>
                                                        No. WA belum diisi
                                                    </span>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </td>



                                    <!-- FAKULTAS -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $row['fakultas']
                                            )
                                        ): ?>

                                            <span class="petugas-faculty">

                                                <i data-lucide="building-2"></i>

                                                <?= htmlspecialchars(
                                                    $row['fakultas']
                                                ); ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="not-filled">
                                                Belum diisi
                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <!-- TOTAL TUGAS -->

                                    <td>

                                        <div class="task-count">

                                            <div class="task-count-number">

                                                <?= $totalTugasPetugas; ?>

                                            </div>

                                            <span>
                                                laporan
                                            </span>

                                        </div>

                                    </td>



                                    <!-- DIPROSES -->

                                    <td>

                                        <?php if (
                                            $tugasDiproses > 0
                                        ): ?>

                                            <span class="mini-status processing">

                                                <span class="mini-dot"></span>

                                                <?= $tugasDiproses; ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="zero-value">
                                                0
                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <!-- SELESAI -->

                                    <td>

                                        <?php if (
                                            $tugasSelesai > 0
                                        ): ?>

                                            <span class="mini-status completed">

                                                <span class="mini-dot"></span>

                                                <?= $tugasSelesai; ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="zero-value">
                                                0
                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <!-- TANGGAL -->

                                    <td>

                                        <div class="registered-date">

                                            <i data-lucide="calendar-days"></i>

                                            <span>

                                                <?= date(
                                                    'd M Y',
                                                    strtotime(
                                                        $row['created_at']
                                                    )
                                                ); ?>

                                            </span>

                                        </div>

                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="7">

                                    <div class="petugas-empty">

                                        <div class="empty-petugas-icon">

                                            <i data-lucide="wrench"></i>

                                        </div>

                                        <h3>
                                            Belum Ada Petugas
                                        </h3>

                                        <p>
                                            Belum ada akun dengan role petugas.
                                        </p>

                                    </div>

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>


            </div>

        </div>

    </section>

</main>



<style>

/* =====================================================
   PETUGAS PAGE
===================================================== */

.admin-petugas-page {
    min-height: calc(100vh - 76px);
    background: #f8fafc;
}

.petugas-container {
    width: min(92%, 1160px);
    margin: 0 auto;
}


/* =====================================================
   HEADER
===================================================== */

.petugas-header {
    padding: 42px 0 28px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.petugas-heading {
    display: flex;
    align-items: center;
    gap: 16px;
}

.heading-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 15px;
    flex-shrink: 0;
}

.heading-icon svg {
    width: 25px;
    height: 25px;
}

.page-eyebrow {
    display: block;
    margin-bottom: 5px;
    color: #2563eb;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
}

.petugas-heading h1 {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.7px;
}

.petugas-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
}


/* =====================================================
   CONTENT
===================================================== */

.petugas-content {
    padding: 28px 0 55px;
}


/* =====================================================
   STATISTICS
===================================================== */

.petugas-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.petugas-stat-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
}

.petugas-stat-icon {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    flex-shrink: 0;
}

.petugas-stat-icon svg {
    width: 20px;
    height: 20px;
}

.petugas-stat-icon.blue {
    color: #2563eb;
    background: #eff6ff;
}

.petugas-stat-icon.purple {
    color: #7c3aed;
    background: #f5f3ff;
}

.petugas-stat-icon.orange {
    color: #ea580c;
    background: #fff7ed;
}

.petugas-stat-icon.green {
    color: #16a34a;
    background: #f0fdf4;
}

.petugas-stat-info {
    display: flex;
    flex-direction: column;
}

.petugas-stat-info span {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.petugas-stat-info strong {
    margin-top: 3px;
    color: #0f172a;
    font-size: 23px;
    font-weight: 800;
}


/* =====================================================
   CARD
===================================================== */

.petugas-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 17px;
    box-shadow: 0 8px 25px rgba(15, 23, 42, .04);
}

.petugas-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 21px 22px;
    border-bottom: 1px solid #e2e8f0;
}

.petugas-card-header h2 {
    margin: 0;
    color: #0f172a;
    font-size: 16px;
    font-weight: 800;
}

.petugas-card-header p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 11px;
}

.petugas-total-badge {
    padding: 8px 12px;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}


/* =====================================================
   FILTER
===================================================== */

.petugas-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 22px;
    background: #fbfdff;
    border-bottom: 1px solid #e2e8f0;
}

.petugas-search,
.task-filter {
    height: 42px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 12px;
    background: #ffffff;
    border: 1px solid #dbe3ee;
    border-radius: 10px;
}

.petugas-search {
    flex: 1;
}

.petugas-search svg,
.task-filter svg {
    width: 17px;
    height: 17px;
    color: #94a3b8;
    flex-shrink: 0;
}

.petugas-search input,
.task-filter select {
    width: 100%;
    height: 100%;
    border: none;
    outline: none;
    color: #334155;
    background: transparent;
    font-family: inherit;
    font-size: 12px;
}

.task-filter {
    width: 190px;
}

.task-filter select {
    cursor: pointer;
}


/* =====================================================
   TABLE
===================================================== */

.petugas-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.petugas-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.petugas-table th {
    padding: 13px 18px;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 10px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}

.petugas-table td {
    padding: 15px 18px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}

.petugas-table tbody tr {
    transition: background .2s ease;
}

.petugas-table tbody tr:hover {
    background: #f8fbff;
}


/* =====================================================
   PROFILE
===================================================== */

.petugas-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.petugas-avatar {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ea580c;
    background: #fff7ed;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.petugas-profile-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.petugas-profile-info strong {
    max-width: 170px;
    overflow: hidden;
    color: #1e293b;
    font-size: 11px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.petugas-profile-info small {
    margin-top: 3px;
    color: #94a3b8;
    font-size: 9px;
}


/* =====================================================
   CONTACT
===================================================== */

.petugas-contact {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.petugas-contact div {
    display: flex;
    align-items: center;
    gap: 6px;
    max-width: 190px;
}

.petugas-contact svg {
    width: 13px;
    height: 13px;
    color: #94a3b8;
    flex-shrink: 0;
}

.petugas-contact span {
    overflow: hidden;
    color: #64748b;
    font-size: 9px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.petugas-contact .no-contact span {
    color: #cbd5e1;
}


/* =====================================================
   FACULTY
===================================================== */

.petugas-faculty {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    color: #475569;
    background: #f1f5f9;
    border-radius: 7px;
    font-size: 9px;
    font-weight: 600;
    white-space: nowrap;
}

.petugas-faculty svg {
    width: 13px;
    height: 13px;
}

.not-filled {
    color: #94a3b8;
    font-size: 9px;
}


/* =====================================================
   TASK COUNT
===================================================== */

.task-count {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.task-count-number {
    color: #1e293b;
    font-size: 14px;
    font-weight: 800;
}

.task-count span {
    color: #94a3b8;
    font-size: 9px;
}


/* =====================================================
   MINI STATUS
===================================================== */

.mini-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 700;
}

.mini-status.processing {
    color: #6d28d9;
    background: #f5f3ff;
}

.mini-status.completed {
    color: #15803d;
    background: #f0fdf4;
}

.mini-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.mini-status.processing .mini-dot {
    background: #8b5cf6;
}

.mini-status.completed .mini-dot {
    background: #22c55e;
}

.zero-value {
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 600;
}


/* =====================================================
   DATE
===================================================== */

.registered-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
    font-size: 9px;
    white-space: nowrap;
}

.registered-date svg {
    width: 14px;
    height: 14px;
    color: #94a3b8;
}


/* =====================================================
   EMPTY
===================================================== */

.petugas-empty {
    padding: 55px 20px;
    text-align: center;
}

.empty-petugas-icon {
    width: 55px;
    height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 13px;
    color: #94a3b8;
    background: #f1f5f9;
    border-radius: 15px;
}

.empty-petugas-icon svg {
    width: 25px;
    height: 25px;
}

.petugas-empty h3 {
    margin: 0;
    color: #334155;
    font-size: 15px;
}

.petugas-empty p {
    margin: 5px 0 0;
    color: #94a3b8;
    font-size: 11px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .petugas-container {
        width: calc(100% - 32px);
    }

    .petugas-stats {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .petugas-header {
        padding: 30px 0 22px;
    }

    .petugas-heading h1 {
        font-size: 23px;
    }

    .petugas-heading p {
        font-size: 11px;
    }

    .heading-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
    }

    .petugas-card-header {
        padding: 17px;
    }

    .petugas-filter {
        flex-direction: column;
        align-items: stretch;
        padding: 13px 17px;
    }

    .task-filter {
        width: auto;
    }

}


@media (max-width: 430px) {

    .petugas-container {
        width: calc(100% - 24px);
    }

    .petugas-heading {
        gap: 11px;
    }

    .petugas-heading h1 {
        font-size: 21px;
    }

    .petugas-heading p {
        font-size: 10px;
    }

    .petugas-stats {
        gap: 10px;
    }

    .petugas-stat-card {
        gap: 9px;
        padding: 12px;
    }

    .petugas-stat-icon {
        width: 34px;
        height: 34px;
    }

    .petugas-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .petugas-stat-info span {
        font-size: 9px;
    }

    .petugas-stat-info strong {
        font-size: 17px;
    }

    .petugas-total-badge {
        display: none;
    }

}


/* =====================================================
   ANIMATION
===================================================== */

.petugas-header,
.petugas-stats,
.petugas-card {
    opacity: 0;
}

.admin-petugas-page.loaded .petugas-header,
.admin-petugas-page.loaded .petugas-stats,
.admin-petugas-page.loaded .petugas-card {
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
   FILTER
===================================================== */

const searchPetugas =
    document.getElementById(
        "searchPetugas"
    );

const taskFilter =
    document.getElementById(
        "taskFilter"
    );

const petugasRows =
    document.querySelectorAll(
        "#petugasTableBody tr[data-search]"
    );


function filterPetugas() {

    const searchValue =
        searchPetugas.value
            .toLowerCase()
            .trim();

    const filterValue =
        taskFilter.value;


    petugasRows.forEach(
        function(row) {

            const text =
                row.dataset.search || "";

            const task =
                row.dataset.task || "";

            const processing =
                parseInt(
                    row.dataset.processing || 0
                );

            const completed =
                parseInt(
                    row.dataset.completed || 0
                );


            const matchSearch =
                text.includes(
                    searchValue
                );


            let matchFilter = true;


            if (filterValue === "aktif") {

                matchFilter =
                    task === "aktif";

            }

            else if (
                filterValue === "kosong"
            ) {

                matchFilter =
                    task === "kosong";

            }

            else if (
                filterValue === "diproses"
            ) {

                matchFilter =
                    processing > 0;

            }

            else if (
                filterValue === "selesai"
            ) {

                matchFilter =
                    completed > 0;

            }


            row.style.display =
                matchSearch &&
                matchFilter
                    ? ""
                    : "none";

        }
    );

}


if (searchPetugas) {

    searchPetugas.addEventListener(
        "input",
        filterPetugas
    );

}


if (taskFilter) {

    taskFilter.addEventListener(
        "change",
        filterPetugas
    );

}


/* =====================================================
   PAGE ANIMATION
===================================================== */

window.addEventListener(
    "load",
    function() {

        const page =
            document.querySelector(
                ".admin-petugas-page"
            );

        if (page) {

            page.classList.add(
                "loaded"
            );

        }


        gsap.from(
            ".petugas-heading",
            {
                opacity: 0,
                y: 18,
                duration: .7,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".petugas-stat-card",
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
            ".petugas-card",
            {
                opacity: 0,
                y: 25,
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

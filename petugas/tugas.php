<?php

require_once "../config/database.php";
requireRole('petugas');

/* =========================================================
   CEK LOGIN
========================================================= */

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id_user'])
) {
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
   FILTER
========================================================= */

$statusFilter = trim(getString('status'));
$search = substr(trim(getString('search')), 0, 200);

$allowedStatus = [
    'Menunggu',
    'Diproses',
    'Selesai'
];

if (
    $statusFilter !== '' &&
    !in_array($statusFilter, $allowedStatus, true)
) {
    $statusFilter = '';
}


/* =========================================================
   STATISTIK
========================================================= */

$stmtStats = mysqli_prepare(
    $conn,
    "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Menunggu'), 0) AS menunggu,
        COALESCE(SUM(status = 'Diproses'), 0) AS diproses,
        COALESCE(SUM(status = 'Selesai'), 0) AS selesai
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

$resultStats = mysqli_stmt_get_result($stmtStats);

$stats = mysqli_fetch_assoc($resultStats);

$totalTugas = (int)($stats['total'] ?? 0);
$totalMenunggu = (int)($stats['menunggu'] ?? 0);
$totalDiproses = (int)($stats['diproses'] ?? 0);
$totalSelesai = (int)($stats['selesai'] ?? 0);


/* =========================================================
   QUERY TUGAS
========================================================= */

$sql = "
    SELECT
        p.id_pengaduan,
        p.no_laporan,
        p.lokasi,
        p.deskripsi,
        p.foto,
        p.status,
        p.tanggal_lapor,

        k.nama_kategori,

        u.nama AS nama_pelapor,
        u.email AS email_pelapor,
        u.no_wa AS wa_pelapor

    FROM pengaduan p

    INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori

    INNER JOIN users u
        ON p.id_user = u.id_user

    WHERE p.id_petugas = ?
";

$types = "i";
$params = [$id_user];

if ($statusFilter !== '') {
    $sql .= " AND p.status = ?";
    $types .= "s";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $sql .= "
        AND (
            p.no_laporan LIKE ?
            OR p.lokasi LIKE ?
            OR p.deskripsi LIKE ?
            OR k.nama_kategori LIKE ?
            OR u.nama LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $types .= "sssss";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql .= "
    ORDER BY
        CASE p.status
            WHEN 'Menunggu' THEN 1
            WHEN 'Diproses' THEN 2
            WHEN 'Selesai' THEN 3
            ELSE 4
        END,
        p.tanggal_lapor DESC
";


$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


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
            return 'waiting';
    }
}


function statusIcon($status)
{
    switch ($status) {

        case 'Menunggu':
            return 'clock-3';

        case 'Diproses':
            return 'wrench';

        case 'Selesai':
            return 'circle-check';

        default:
            return 'clock-3';
    }
}


function formatTanggalTugas($tanggal)
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


function formatJamTugas($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('H:i', strtotime($tanggal));
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
        Tugas Pengaduan | ADUIN
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

$activePage = 'tugas';

include '../includes/navbar.php';

?>


<main class="staff-task-page">

    <div class="staff-task-shell">


        <!-- =====================================================
             HEADER
        ===================================================== -->

        <section class="task-page-header">

            <div>

                <span class="page-eyebrow">
                    MANAJEMEN TUGAS
                </span>

                <h1>
                    Tugas Pengaduan
                </h1>

                <p>
                    Kelola dan tangani pengaduan fasilitas yang
                    ditugaskan kepada Anda.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="task-back-btn"
            >

                <i data-lucide="arrow-left"></i>

                Dashboard

            </a>

        </section>



        <!-- =====================================================
             STATISTIK
        ===================================================== -->

        <section class="task-stats">

            <div class="task-stat-card blue">

                <div class="task-stat-icon">

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


            <div class="task-stat-card amber">

                <div class="task-stat-icon">

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


            <div class="task-stat-card violet">

                <div class="task-stat-icon">

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


            <div class="task-stat-card green">

                <div class="task-stat-icon">

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

        </section>



        <!-- =====================================================
             CONTENT CARD
        ===================================================== -->

        <section class="task-content-card">


            <!-- =================================================
                 TOOLBAR
            ================================================= -->

            <div class="task-toolbar">

                <div class="task-toolbar-title">

                    <span>
                        DAFTAR TUGAS
                    </span>

                    <h2>
                        Pengaduan Ditugaskan
                    </h2>

                </div>


                <form
                    method="GET"
                    class="task-filter-form"
                    id="filterForm"
                >

                    <div class="task-search">

                        <i data-lucide="search"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Cari laporan..."
                            autocomplete="off"
                        >

                    </div>


                    <div class="task-status-filter">

                        <i data-lucide="list-filter"></i>

                        <select
                            name="status"
                            data-submit-filter
                        >

                            <option
                                value=""
                                <?= $statusFilter === ''
                                    ? 'selected'
                                    : '' ?>
                            >
                                Semua Status
                            </option>

                            <option
                                value="Menunggu"
                                <?= $statusFilter === 'Menunggu'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Menunggu
                            </option>

                            <option
                                value="Diproses"
                                <?= $statusFilter === 'Diproses'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Diproses
                            </option>

                            <option
                                value="Selesai"
                                <?= $statusFilter === 'Selesai'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Selesai
                            </option>

                        </select>

                    </div>


                    <button
                        type="submit"
                        class="task-search-btn"
                    >

                        <i data-lucide="search"></i>

                        Cari

                    </button>

                </form>

            </div>



            <!-- =================================================
                 TABLE
            ================================================= -->

            <div class="task-table-wrap">

                <table class="task-table">

                    <thead>

                        <tr>

                            <th>
                                LAPORAN
                            </th>

                            <th>
                                PELAPOR
                            </th>

                            <th>
                                KATEGORI
                            </th>

                            <th>
                                LOKASI
                            </th>

                            <th>
                                TANGGAL
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                AKSI
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($row = mysqli_fetch_assoc($result)): ?>

                            <?php
                                $status = $row['status'];
                                $statusClassName = statusClass($status);
                                $statusIconName = statusIcon($status);
                            ?>

                            <tr>

                                <!-- LAPORAN -->

                                <td>

                                    <div class="report-cell">

                                        <div class="report-icon">

                                            <i data-lucide="file-text"></i>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $row['no_laporan']
                                                ) ?>
                                            </strong>

                                            <span>
                                                ID #<?= (int)$row['id_pengaduan'] ?>
                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- PELAPOR -->

                                <td>

                                    <div class="reporter-cell">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row['nama_pelapor']
                                            ) ?>
                                        </strong>

                                        <?php if (!empty($row['email_pelapor'])): ?>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $row['email_pelapor']
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- KATEGORI -->

                                <td>

                                    <span class="category-badge">

                                        <i data-lucide="tag"></i>

                                        <?= htmlspecialchars(
                                            $row['nama_kategori']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- LOKASI -->

                                <td>

                                    <div class="location-cell">

                                        <i data-lucide="map-pin"></i>

                                        <span>
                                            <?= htmlspecialchars(
                                                $row['lokasi']
                                            ) ?>
                                        </span>

                                    </div>

                                </td>


                                <!-- TANGGAL -->

                                <td>

                                    <div class="date-cell">

                                        <strong>
                                            <?= formatTanggalTugas(
                                                $row['tanggal_lapor']
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= formatJamTugas(
                                                $row['tanggal_lapor']
                                            ) ?>
                                            WIB
                                        </span>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="task-status <?= $statusClassName ?>"
                                    >

                                        <i
                                            data-lucide="<?= $statusIconName ?>"
                                        ></i>

                                        <?= htmlspecialchars($status) ?>

                                    </span>

                                </td>


                                <!-- AKSI -->

                                <td>

                                    <a
                                        href="detail-tugas.php?id=<?= (int)$row['id_pengaduan'] ?>"
                                        class="detail-task-btn"
                                    >

                                        <span>
                                            Detail
                                        </span>

                                        <i data-lucide="arrow-right"></i>

                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty-table"
                            >

                                <div class="empty-task">

                                    <div class="empty-task-icon">

                                        <i data-lucide="clipboard-x"></i>

                                    </div>

                                    <h3>
                                        Tidak Ada Tugas
                                    </h3>

                                    <p>

                                        <?php if (
                                            $search !== '' ||
                                            $statusFilter !== ''
                                        ): ?>

                                            Tidak ditemukan tugas yang
                                            sesuai dengan filter pencarian.

                                        <?php else: ?>

                                            Belum ada pengaduan yang
                                            ditugaskan kepada Anda.

                                        <?php endif; ?>

                                    </p>


                                    <?php if (
                                        $search !== '' ||
                                        $statusFilter !== ''
                                    ): ?>

                                        <a
                                            href="tugas.php"
                                            class="reset-filter-btn"
                                        >

                                            <i data-lucide="rotate-ccw"></i>

                                            Reset Filter

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>



            <!-- =================================================
                 MOBILE CARDS
            ================================================= -->

            <div class="task-mobile-list">

                <?php

                /*
                 * Query ulang khusus untuk mobile.
                 * Menggunakan query yang sama agar data mobile
                 * tetap konsisten dengan tabel desktop.
                 */

                mysqli_stmt_execute($stmt);

                $mobileResult = mysqli_stmt_get_result($stmt);

                ?>

                <?php if (mysqli_num_rows($mobileResult) > 0): ?>

                    <?php while ($row = mysqli_fetch_assoc($mobileResult)): ?>

                        <?php
                            $status = $row['status'];
                            $statusClassName = statusClass($status);
                            $statusIconName = statusIcon($status);
                        ?>

                        <article class="task-mobile-card">


                            <div class="mobile-task-top">

                                <div class="mobile-report-number">

                                    <div class="report-icon">

                                        <i data-lucide="file-text"></i>

                                    </div>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row['no_laporan']
                                            ) ?>
                                        </strong>

                                        <span>
                                            ID #<?= (int)$row['id_pengaduan'] ?>
                                        </span>

                                    </div>

                                </div>


                                <span
                                    class="task-status <?= $statusClassName ?>"
                                >

                                    <i
                                        data-lucide="<?= $statusIconName ?>"
                                    ></i>

                                    <?= htmlspecialchars($status) ?>

                                </span>

                            </div>


                            <div class="mobile-task-divider"></div>


                            <div class="mobile-task-info">


                                <div class="mobile-info-item">

                                    <span>
                                        Pelapor
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $row['nama_pelapor']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="mobile-info-item">

                                    <span>
                                        Kategori
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $row['nama_kategori']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="mobile-info-item full">

                                    <span>
                                        Lokasi
                                    </span>

                                    <div class="mobile-location">

                                        <i data-lucide="map-pin"></i>

                                        <?= htmlspecialchars(
                                            $row['lokasi']
                                        ) ?>

                                    </div>

                                </div>


                                <div class="mobile-info-item">

                                    <span>
                                        Tanggal
                                    </span>

                                    <strong>
                                        <?= formatTanggalTugas(
                                            $row['tanggal_lapor']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="mobile-info-item">

                                    <span>
                                        Waktu
                                    </span>

                                    <strong>
                                        <?= formatJamTugas(
                                            $row['tanggal_lapor']
                                        ) ?>
                                        WIB
                                    </strong>

                                </div>

                            </div>


                            <a
                                href="detail-tugas.php?id=<?= (int)$row['id_pengaduan'] ?>"
                                class="mobile-detail-btn"
                            >

                                Lihat Detail Pengaduan

                                <i data-lucide="arrow-right"></i>

                            </a>

                        </article>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="mobile-empty-task">

                        <div class="empty-task-icon">

                            <i data-lucide="clipboard-x"></i>

                        </div>

                        <h3>
                            Tidak Ada Tugas
                        </h3>

                        <p>
                            Belum ada tugas yang sesuai dengan filter.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


        </section>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - PETUGAS TUGAS
========================================================= */

.staff-task-page {

    min-height: calc(100vh - 76px);

    padding:
        38px
        24px
        70px;

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


.staff-task-shell {

    width: min(1160px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.task-page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}


.task-page-header > div {

    min-width: 0;

}


.page-eyebrow {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .1em;

}


.task-page-header h1 {

    margin: 0;

    color: #0f172a;

    font-size: 27px;

    font-weight: 800;

    line-height: 1.15;

    letter-spacing: -.5px;

}


.task-page-header p {

    margin: 6px 0 0;

    color: #64748b;

    font-size: 10px;

    line-height: 1.5;

}


.task-back-btn {

    min-height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 0 13px;

    color: #475569;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 9px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    transition: .2s ease;

    flex-shrink: 0;

}


.task-back-btn:hover {

    color: #2563eb;

    background: #eff6ff;

    border-color: #bfdbfe;

    transform: translateY(-1px);

}


.task-back-btn svg {

    width: 14px;

    height: 14px;

}


/* =========================================================
   STATISTICS
========================================================= */

.task-stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 13px;

    margin-bottom: 18px;

}


.task-stat-card {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 0;

    padding: 15px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 13px;

    box-shadow:
        0 6px 22px rgba(15,23,42,.035);

}


.task-stat-icon {

    width: 38px;

    height: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 10px;

}


.task-stat-icon svg {

    width: 18px;

    height: 18px;

}


.task-stat-card > div:last-child {

    min-width: 0;

}


.task-stat-card span {

    display: block;

    color: #94a3b8;

    font-size: 7px;

    line-height: 1.3;

}


.task-stat-card strong {

    display: block;

    margin-top: 3px;

    color: #0f172a;

    font-size: 19px;

    font-weight: 800;

    line-height: 1;

}


.task-stat-card.blue
.task-stat-icon {

    color: #2563eb;

    background: #eff6ff;

}


.task-stat-card.amber
.task-stat-icon {

    color: #b45309;

    background: #fffbeb;

}


.task-stat-card.violet
.task-stat-icon {

    color: #7c3aed;

    background: #f5f3ff;

}


.task-stat-card.green
.task-stat-icon {

    color: #15803d;

    background: #f0fdf4;

}


/* =========================================================
   CONTENT CARD
========================================================= */

.task-content-card {

    overflow: hidden;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15,23,42,.035);

}


/* =========================================================
   TOOLBAR
========================================================= */

.task-toolbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 18px;

    padding: 18px 19px;

    border-bottom: 1px solid #eef2f7;

}


.task-toolbar-title {

    min-width: 0;

}


.task-toolbar-title span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .09em;

}


.task-toolbar-title h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

}


/* =========================================================
   FILTER
========================================================= */

.task-filter-form {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-shrink: 0;

}


.task-search {

    position: relative;

    width: 190px;

}


.task-search > svg {

    position: absolute;

    left: 10px;

    top: 50%;

    width: 14px;

    height: 14px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}


.task-search input {

    width: 100%;

    height: 38px;

    box-sizing: border-box;

    padding:
        0
        10px
        0
        32px;

    color: #334155;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 8px;

    transition: .2s ease;

}


.task-search input:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.07);

}


.task-status-filter {

    position: relative;

}


.task-status-filter > svg {

    position: absolute;

    left: 10px;

    top: 50%;

    width: 13px;

    height: 13px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}


.task-status-filter select {

    width: 135px;

    height: 38px;

    padding:
        0
        25px
        0
        29px;

    color: #475569;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 8px;

    cursor: pointer;

}


.task-search-btn {

    height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding: 0 12px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 9px;

    cursor: pointer;

    font-family: inherit;

    font-size: 8px;

    font-weight: 800;

    transition: .2s ease;

}


.task-search-btn:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-1px);

}


.task-search-btn svg {

    width: 13px;

    height: 13px;

}


/* =========================================================
   TABLE
========================================================= */

.task-table-wrap {

    width: 100%;

    overflow-x: auto;

}


.task-table {

    width: 100%;

    min-width: 930px;

    border-collapse: collapse;

}


.task-table thead th {

    padding: 11px 14px;

    color: #94a3b8;

    background: #f8fafc;

    border-bottom: 1px solid #eef2f7;

    font-size: 7px;

    font-weight: 800;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .05em;

    white-space: nowrap;

}


.task-table tbody td {

    padding: 13px 14px;

    border-bottom: 1px solid #f1f5f9;

    vertical-align: middle;

}


.task-table tbody tr {

    transition: background .2s ease;

}


.task-table tbody tr:hover {

    background: #fafcff;

}


.task-table tbody tr:last-child td {

    border-bottom: none;

}


/* =========================================================
   REPORT
========================================================= */

.report-cell {

    display: flex;

    align-items: center;

    gap: 9px;

    min-width: 145px;

}


.report-icon {

    width: 33px;

    height: 33px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 8px;

}


.report-icon svg {

    width: 15px;

    height: 15px;

}


.report-cell > div:last-child {

    min-width: 0;

}


.report-cell strong {

    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 8px;

    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;

}


.report-cell span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 7px;

}


/* =========================================================
   REPORTER
========================================================= */

.reporter-cell {

    min-width: 115px;

}


.reporter-cell strong {

    display: block;

    color: #334155;

    font-size: 8px;

    font-weight: 700;

}


.reporter-cell span {

    display: block;

    margin-top: 3px;

    max-width: 130px;

    overflow: hidden;

    color: #94a3b8;

    font-size: 7px;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   CATEGORY
========================================================= */

.category-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    min-height: 25px;

    padding: 0 8px;

    color: #475569;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 7px;

    font-size: 7px;

    font-weight: 700;

    white-space: nowrap;

}


.category-badge svg {

    width: 11px;

    height: 11px;

    color: #64748b;

}


/* =========================================================
   LOCATION
========================================================= */

.location-cell {

    display: flex;

    align-items: flex-start;

    gap: 5px;

    max-width: 150px;

    color: #64748b;

    font-size: 7px;

    line-height: 1.4;

}


.location-cell svg {

    width: 12px;

    height: 12px;

    flex-shrink: 0;

    color: #94a3b8;

    margin-top: 1px;

}


/* =========================================================
   DATE
========================================================= */

.date-cell {

    min-width: 90px;

}


.date-cell strong {

    display: block;

    color: #475569;

    font-size: 7px;

    font-weight: 700;

}


.date-cell span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 7px;

}


/* =========================================================
   STATUS
========================================================= */

.task-status {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    min-height: 25px;

    padding: 0 8px;

    border-radius: 7px;

    font-size: 7px;

    font-weight: 800;

    white-space: nowrap;

}


.task-status svg {

    width: 12px;

    height: 12px;

}


.task-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}


.task-status.processing {

    color: #7c3aed;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}


.task-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}


/* =========================================================
   DETAIL BUTTON
========================================================= */

.detail-task-btn {

    min-height: 32px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding: 0 10px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 7px;

    font-weight: 800;

    transition: .2s ease;

    white-space: nowrap;

}


.detail-task-btn:hover {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

    transform: translateY(-1px);

}


.detail-task-btn svg {

    width: 12px;

    height: 12px;

    transition: transform .2s ease;

}


.detail-task-btn:hover svg {

    transform: translateX(2px);

}


/* =========================================================
   EMPTY
========================================================= */

.empty-table {

    padding: 0 !important;

}


.empty-task {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 300px;

    padding: 30px;

    text-align: center;

}


.empty-task-icon {

    width: 55px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 13px;

    color: #94a3b8;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 14px;

}


.empty-task-icon svg {

    width: 24px;

    height: 24px;

}


.empty-task h3 {

    margin: 0;

    color: #334155;

    font-size: 13px;

    font-weight: 800;

}


.empty-task p {

    max-width: 330px;

    margin: 6px 0 15px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.6;

}


.reset-filter-btn {

    min-height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding: 0 11px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 8px;

    font-weight: 800;

}


.reset-filter-btn svg {

    width: 13px;

    height: 13px;

}


/* =========================================================
   MOBILE LIST
========================================================= */

.task-mobile-list {

    display: none;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1000px) {

    .task-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .task-toolbar {

        align-items: flex-start;

        flex-direction: column;

    }

    .task-filter-form {

        width: 100%;

    }

    .task-search {

        flex: 1;

        width: auto;

    }

}


/* =========================================================
   TABLET SMALL
========================================================= */

@media (max-width: 760px) {

    .staff-task-page {

        padding:
            28px
            15px
            50px;

    }

    .task-page-header {

        align-items: flex-start;

        flex-direction: column;

    }

    .task-back-btn {

        width: 100%;

        box-sizing: border-box;

    }

    .task-toolbar {

        padding: 16px 15px;

    }

    .task-filter-form {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr)
            auto;

        width: 100%;

    }

    .task-search {

        width: 100%;

    }

    .task-status-filter {

        grid-column: 1 / -1;

        width: 100%;

    }

    .task-status-filter select {

        width: 100%;

    }

    .task-search-btn {

        min-width: 75px;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .staff-task-page {

        width: 100%;

        max-width: 100%;

        box-sizing: border-box;

        padding:
            25px
            12px
            45px;

        overflow-x: hidden;

    }

    .staff-task-shell {

        width: 100%;

        max-width: 100%;

    }


    /* HEADER */

    .task-page-header {

        width: 100%;

        margin-bottom: 18px;

    }

    .task-page-header h1 {

        font-size: 24px;

    }

    .task-page-header p {

        font-size: 9px;

        line-height: 1.6;

    }


    /* STATS */

    .task-stats {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 9px;

        width: 100%;

        margin-bottom: 15px;

    }

    .task-stat-card {

        padding: 12px;

        gap: 8px;

        border-radius: 11px;

    }

    .task-stat-icon {

        width: 32px;

        height: 32px;

        border-radius: 8px;

    }

    .task-stat-icon svg {

        width: 15px;

        height: 15px;

    }

    .task-stat-card span {

        font-size: 6px;

    }

    .task-stat-card strong {

        font-size: 16px;

    }


    /* CONTENT */

    .task-content-card {

        width: 100%;

        border-radius: 13px;

    }


    /* TOOLBAR */

    .task-toolbar {

        padding: 15px;

        gap: 13px;

    }

    .task-toolbar-title h2 {

        font-size: 13px;

    }

    .task-toolbar-title span {

        font-size: 6px;

    }

    .task-filter-form {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr)
            auto;

        gap: 7px;

        width: 100%;

    }

    .task-search {

        width: 100%;

    }

    .task-search input {

        height: 40px;

        font-size: 8px;

    }

    .task-status-filter {

        grid-column: 1 / -1;

        width: 100%;

    }

    .task-status-filter select {

        width: 100%;

        height: 40px;

    }

    .task-search-btn {

        height: 40px;

        padding: 0 12px;

    }


    /* HIDE DESKTOP TABLE */

    .task-table-wrap {

        display: none;

    }


    /* SHOW MOBILE */

    .task-mobile-list {

        display: flex;

        flex-direction: column;

        gap: 10px;

        padding: 10px;

        background: #f8fafc;

    }


    /* MOBILE CARD */

    .task-mobile-card {

        width: 100%;

        box-sizing: border-box;

        padding: 14px;

        background: #fff;

        border: 1px solid #e2e8f0;

        border-radius: 12px;

        box-shadow:
            0 4px 15px rgba(15,23,42,.035);

    }


    .mobile-task-top {

        display: flex;

        align-items: flex-start;

        justify-content: space-between;

        gap: 9px;

    }


    .mobile-report-number {

        display: flex;

        align-items: center;

        gap: 8px;

        min-width: 0;

        flex: 1;

    }


    .mobile-report-number .report-icon {

        width: 34px;

        height: 34px;

    }


    .mobile-report-number > div:last-child {

        min-width: 0;

    }


    .mobile-report-number strong {

        display: block;

        overflow: hidden;

        color: #334155;

        font-size: 8px;

        font-weight: 800;

        text-overflow: ellipsis;

        white-space: nowrap;

    }


    .mobile-report-number span {

        display: block;

        margin-top: 3px;

        color: #94a3b8;

        font-size: 6px;

    }


    .mobile-task-top .task-status {

        flex-shrink: 0;

        font-size: 6px;

        min-height: 23px;

        padding: 0 7px;

    }


    .mobile-task-top .task-status svg {

        width: 10px;

        height: 10px;

    }


    .mobile-task-divider {

        width: 100%;

        height: 1px;

        margin: 13px 0;

        background: #eef2f7;

    }


    .mobile-task-info {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 12px 10px;

    }


    .mobile-info-item {

        min-width: 0;

    }


    .mobile-info-item.full {

        grid-column: 1 / -1;

    }


    .mobile-info-item > span {

        display: block;

        margin-bottom: 4px;

        color: #94a3b8;

        font-size: 6px;

        font-weight: 600;

    }


    .mobile-info-item > strong {

        display: block;

        overflow: hidden;

        color: #475569;

        font-size: 8px;

        font-weight: 700;

        text-overflow: ellipsis;

        white-space: nowrap;

    }


    .mobile-location {

        display: flex;

        align-items: flex-start;

        gap: 5px;

        color: #475569;

        font-size: 8px;

        line-height: 1.45;

    }


    .mobile-location svg {

        width: 11px;

        height: 11px;

        flex-shrink: 0;

        margin-top: 1px;

        color: #94a3b8;

    }


    .mobile-detail-btn {

        width: 100%;

        min-height: 38px;

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 6px;

        margin-top: 14px;

        color: #2563eb;

        background: #eff6ff;

        border: 1px solid #dbeafe;

        border-radius: 8px;

        text-decoration: none;

        font-size: 8px;

        font-weight: 800;

        transition: .2s ease;

        box-sizing: border-box;

    }


    .mobile-detail-btn:hover {

        color: #fff;

        background: #2563eb;

        border-color: #2563eb;

    }


    .mobile-detail-btn svg {

        width: 13px;

        height: 13px;

    }


    .mobile-empty-task {

        display: flex;

        flex-direction: column;

        align-items: center;

        justify-content: center;

        padding: 45px 20px;

        text-align: center;

        background: #fff;

        border: 1px solid #e2e8f0;

        border-radius: 12px;

    }


    .mobile-empty-task h3 {

        margin: 0;

        color: #334155;

        font-size: 13px;

        font-weight: 800;

    }


    .mobile-empty-task p {

        margin: 6px 0 0;

        color: #94a3b8;

        font-size: 8px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 420px) {

    .staff-task-page {

        padding-left: 10px;

        padding-right: 10px;

    }

    .task-page-header h1 {

        font-size: 22px;

    }

    .task-stats {

        gap: 7px;

    }

    .task-stat-card {

        padding: 10px;

    }

    .task-stat-icon {

        width: 29px;

        height: 29px;

    }

    .task-stat-card strong {

        font-size: 15px;

    }

    .task-toolbar {

        padding: 14px;

    }

    .task-mobile-list {

        padding: 8px;

    }

    .task-mobile-card {

        padding: 12px;

    }

    .mobile-task-top .task-status {

        font-size: 5.5px;

    }

}


/* =========================================================
   VERY SMALL MOBILE
========================================================= */

@media (max-width: 360px) {

    .staff-task-page {

        padding-left: 8px;

        padding-right: 8px;

    }

    .task-stats {

        grid-template-columns: 1fr;

    }

    .task-stat-card {

        min-height: 52px;

    }

    .mobile-task-top {

        flex-direction: column;

        align-items: stretch;

    }

    .mobile-task-top .task-status {

        align-self: flex-start;

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

        let lenis = null;

        if (typeof Lenis !== "undefined") {

            lenis = new Lenis({

                duration: 1.05,

                smoothWheel: true,

                syncTouch: false,

                wheelMultiplier: .9,

                touchMultiplier: 1.2

            });


            function raf(time) {

                lenis.raf(time);

                requestAnimationFrame(raf);

            }

            requestAnimationFrame(raf);

        }


        /* =====================================================
           GSAP PAGE ENTRANCE
        ===================================================== */

        if (
            typeof gsap !== "undefined"
        ) {

            gsap.set(
                ".task-page-header, .task-stat-card, .task-content-card",
                {
                    opacity: 0,
                    y: 18
                }
            );


            gsap.to(
                ".task-page-header",
                {
                    opacity: 1,
                    y: 0,
                    duration: .65,
                    ease: "power3.out"
                }
            );


            gsap.to(
                ".task-stat-card",
                {
                    opacity: 1,
                    y: 0,
                    duration: .55,
                    stagger: .07,
                    delay: .12,
                    ease: "power3.out"
                }
            );


            gsap.to(
                ".task-content-card",
                {
                    opacity: 1,
                    y: 0,
                    duration: .65,
                    delay: .28,
                    ease: "power3.out"
                }
            );

        }


        /* =====================================================
           SEARCH SUBMIT LOADING
        ===================================================== */

        const filterForm =
            document.getElementById("filterForm");

        if (filterForm) {

            filterForm.addEventListener(
                "submit",
                function () {

                    const button =
                        filterForm.querySelector(
                            ".task-search-btn"
                        );

                    if (!button) {
                        return;
                    }

                    button.disabled = true;

                    button.innerHTML = `
                        <i data-lucide="loader-circle"></i>
                        Memuat
                    `;

                    if (window.lucide) {

                        lucide.createIcons();

                    }

                }
            );

        }


        /* =====================================================
           NAVBAR SCROLL
        ===================================================== */

        const navbar =
            document.querySelector(".navbar");

        if (navbar) {

            const updateNavbar =
                function () {

                    if (window.scrollY > 25) {

                        navbar.classList.add("scrolled");

                    } else {

                        navbar.classList.remove("scrolled");

                    }

                };

            updateNavbar();

            window.addEventListener(
                "scroll",
                updateNavbar,
                {
                    passive: true
                }
            );

        }


    }
);

</script>


</body>

</html>

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
   CEK PETUGAS
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

$totalRiwayat = (int)($stats['total'] ?? 0);
$totalMenunggu = (int)($stats['menunggu'] ?? 0);
$totalDiproses = (int)($stats['diproses'] ?? 0);
$totalSelesai = (int)($stats['selesai'] ?? 0);


/* =========================================================
   QUERY RIWAYAT
========================================================= */

/*
    Mengambil hanya penanganan TERAKHIR untuk setiap pengaduan.

    Struktur:
    pengaduan
        ↓
    kategori
        ↓
    users sebagai pelapor
        ↓
    penanganan terakhir
*/

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

        pn.id_penanganan,
        pn.catatan,
        pn.foto_perbaikan,
        pn.tanggal_penanganan

    FROM pengaduan p

    INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori

    INNER JOIN users u
        ON p.id_user = u.id_user

    LEFT JOIN penanganan pn
        ON pn.id_penanganan = (
            SELECT MAX(pn2.id_penanganan)
            FROM penanganan pn2
            WHERE
                pn2.id_pengaduan = p.id_pengaduan
                AND pn2.id_petugas = ?
        )

    WHERE p.id_petugas = ?
";


$types = "ii";

$params = [
    $id_user,
    $id_user
];


if ($statusFilter !== '') {

    $sql .= "
        AND p.status = ?
    ";

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
            WHEN 'Diproses' THEN 1
            WHEN 'Menunggu' THEN 2
            WHEN 'Selesai' THEN 3
            ELSE 4
        END,
        COALESCE(
            pn.tanggal_penanganan,
            p.tanggal_lapor
        ) DESC
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
   HELPER STATUS
========================================================= */

function riwayatStatusClass($status)
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


function riwayatStatusIcon($status)
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


/* =========================================================
   FORMAT TANGGAL
========================================================= */

function formatTanggalRiwayat($tanggal)
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

    if (!$timestamp) {
        return '-';
    }

    return date('d', $timestamp)
        . ' '
        . $bulan[(int)date('m', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}


function formatJamRiwayat($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if (!$timestamp) {
        return '-';
    }

    return date('H:i', $timestamp);
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
        Riwayat Penanganan | ADUIN
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

    <script nonce="<?= e(cspNonce()) ?>"
        src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js"
    ></script>

</head>


<body>


<?php

$basePath = '../';

$activePage = 'riwayat';

include '../includes/navbar.php';

?>


<main class="staff-history-page">

    <div class="staff-history-shell">


        <!-- =================================================
             HEADER
        ================================================= -->

        <section class="history-page-header">

            <div class="history-heading">

                <span class="history-eyebrow">
                    AKTIVITAS PETUGAS
                </span>

                <h1>
                    Riwayat Penanganan
                </h1>

                <p>
                    Lihat seluruh pengaduan yang pernah ditugaskan
                    dan ditangani oleh Anda.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="history-back-btn"
            >

                <i data-lucide="arrow-left"></i>

                Dashboard

            </a>

        </section>



        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="history-stats">


            <!-- TOTAL -->

            <div class="history-stat blue">

                <div class="history-stat-icon">

                    <i data-lucide="clipboard-list"></i>

                </div>

                <div>

                    <span>
                        Total Riwayat
                    </span>

                    <strong>
                        <?= $totalRiwayat ?>
                    </strong>

                </div>

            </div>


            <!-- MENUNGGU -->

            <div class="history-stat amber">

                <div class="history-stat-icon">

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


            <!-- DIPROSES -->

            <div class="history-stat violet">

                <div class="history-stat-icon">

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


            <!-- SELESAI -->

            <div class="history-stat green">

                <div class="history-stat-icon">

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



        <!-- =================================================
             MAIN CARD
        ================================================= -->

        <section class="history-card">


            <!-- =================================================
                 TOOLBAR
            ================================================= -->

            <div class="history-toolbar">


                <div class="history-toolbar-heading">

                    <span>
                        RIWAYAT
                    </span>

                    <h2>
                        Daftar Penanganan
                    </h2>

                </div>


                <form
                    method="GET"
                    class="history-filter"
                    id="historyFilter"
                >


                    <div class="history-search">

                        <i data-lucide="search"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Cari riwayat..."
                            autocomplete="off"
                        >

                    </div>


                    <div class="history-select">

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
                        class="history-search-btn"
                    >

                        <i data-lucide="search"></i>

                        Cari

                    </button>


                </form>

            </div>



            <!-- =================================================
                 DESKTOP TABLE
            ================================================= -->

            <div class="history-table-wrap">

                <table class="history-table">

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
                                PENANGANAN
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                TANGGAL
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

                            $statusClass =
                                riwayatStatusClass($status);

                            $statusIcon =
                                riwayatStatusIcon($status);

                            ?>


                            <tr>


                                <!-- LAPORAN -->

                                <td>

                                    <div class="history-report">

                                        <div class="history-report-icon">

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

                                    <div class="history-reporter">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row['nama_pelapor']
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            !empty($row['email_pelapor'])
                                        ): ?>

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

                                    <span class="history-category">

                                        <i data-lucide="tag"></i>

                                        <?= htmlspecialchars(
                                            $row['nama_kategori']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- LOKASI -->

                                <td>

                                    <div class="history-location">

                                        <i data-lucide="map-pin"></i>

                                        <span>
                                            <?= htmlspecialchars(
                                                $row['lokasi']
                                            ) ?>
                                        </span>

                                    </div>

                                </td>


                                <!-- PENANGANAN -->

                                <td>

                                    <?php if (
                                        !empty($row['catatan'])
                                    ): ?>

                                        <div class="history-note">

                                            <i data-lucide="message-square-text"></i>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $row['catatan']
                                                ) ?>
                                            </span>

                                        </div>

                                    <?php elseif (
                                        !empty($row['foto_perbaikan'])
                                    ): ?>

                                        <div class="history-note">

                                            <i data-lucide="image"></i>

                                            <span>
                                                Foto perbaikan tersedia
                                            </span>

                                        </div>

                                    <?php else: ?>

                                        <span class="history-no-note">
                                            Belum ada catatan
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="history-status <?= $statusClass ?>"
                                    >

                                        <i
                                            data-lucide="<?= $statusIcon ?>"
                                        ></i>

                                        <?= htmlspecialchars($status) ?>

                                    </span>

                                </td>


                                <!-- TANGGAL -->

                                <td>

                                    <div class="history-date">

                                        <strong>
                                            <?= formatTanggalRiwayat(
                                                $row['tanggal_penanganan']
                                                ?: $row['tanggal_lapor']
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= formatJamRiwayat(
                                                $row['tanggal_penanganan']
                                                ?: $row['tanggal_lapor']
                                            ) ?>
                                            WIB
                                        </span>

                                    </div>

                                </td>


                                <!-- DETAIL -->

                                <td>

                                    <a
                                        href="detail-tugas.php?id=<?= (int)$row['id_pengaduan'] ?>"
                                        class="history-detail-btn"
                                    >

                                        Detail

                                        <i data-lucide="arrow-right"></i>

                                    </a>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                class="history-empty-cell"
                            >

                                <div class="history-empty">

                                    <div class="history-empty-icon">

                                        <i data-lucide="history"></i>

                                    </div>

                                    <h3>
                                        Belum Ada Riwayat
                                    </h3>

                                    <p>

                                        <?php if (
                                            $search !== '' ||
                                            $statusFilter !== ''
                                        ): ?>

                                            Tidak ditemukan riwayat
                                            yang sesuai dengan filter.

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
                                            href="riwayat.php"
                                            class="history-reset-btn"
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
                 MOBILE
            ================================================= -->

            <div class="history-mobile-list">


                <?php

                /*
                 * Query kembali agar mobile memiliki data
                 * yang sama dengan tabel desktop.
                 */

                mysqli_stmt_execute($stmt);

                $mobileResult =
                    mysqli_stmt_get_result($stmt);

                ?>


                <?php if (
                    mysqli_num_rows($mobileResult) > 0
                ): ?>


                    <?php while (
                        $row = mysqli_fetch_assoc($mobileResult)
                    ): ?>


                        <?php

                        $status = $row['status'];

                        $statusClass =
                            riwayatStatusClass($status);

                        $statusIcon =
                            riwayatStatusIcon($status);

                        ?>


                        <article class="history-mobile-card">


                            <!-- TOP -->

                            <div class="mobile-history-top">


                                <div class="mobile-history-report">

                                    <div class="history-report-icon">

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
                                    class="history-status <?= $statusClass ?>"
                                >

                                    <i
                                        data-lucide="<?= $statusIcon ?>"
                                    ></i>

                                    <?= htmlspecialchars($status) ?>

                                </span>


                            </div>


                            <div class="mobile-history-divider"></div>


                            <!-- INFO -->

                            <div class="mobile-history-info">


                                <div class="mobile-history-item">

                                    <span>
                                        Pelapor
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $row['nama_pelapor']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="mobile-history-item">

                                    <span>
                                        Kategori
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $row['nama_kategori']
                                        ) ?>
                                    </strong>

                                </div>


                                <div
                                    class="mobile-history-item full"
                                >

                                    <span>
                                        Lokasi
                                    </span>

                                    <div class="mobile-history-location">

                                        <i data-lucide="map-pin"></i>

                                        <?= htmlspecialchars(
                                            $row['lokasi']
                                        ) ?>

                                    </div>

                                </div>


                                <div
                                    class="mobile-history-item full"
                                >

                                    <span>
                                        Penanganan
                                    </span>


                                    <?php if (
                                        !empty($row['catatan'])
                                    ): ?>

                                        <div class="mobile-history-note">

                                            <i data-lucide="message-square-text"></i>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $row['catatan']
                                                ) ?>
                                            </span>

                                        </div>

                                    <?php elseif (
                                        !empty($row['foto_perbaikan'])
                                    ): ?>

                                        <div class="mobile-history-note">

                                            <i data-lucide="image"></i>

                                            <span>
                                                Foto perbaikan tersedia
                                            </span>

                                        </div>

                                    <?php else: ?>

                                        <div class="mobile-no-note">

                                            Belum ada catatan penanganan.

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div class="mobile-history-item">

                                    <span>
                                        Tanggal
                                    </span>

                                    <strong>
                                        <?= formatTanggalRiwayat(
                                            $row['tanggal_penanganan']
                                            ?: $row['tanggal_lapor']
                                        ) ?>
                                    </strong>

                                </div>


                                <div class="mobile-history-item">

                                    <span>
                                        Waktu
                                    </span>

                                    <strong>
                                        <?= formatJamRiwayat(
                                            $row['tanggal_penanganan']
                                            ?: $row['tanggal_lapor']
                                        ) ?>
                                        WIB
                                    </strong>

                                </div>


                            </div>


                            <!-- DETAIL -->

                            <a
                                href="detail-tugas.php?id=<?= (int)$row['id_pengaduan'] ?>"
                                class="mobile-history-detail"
                            >

                                Lihat Detail Pengaduan

                                <i data-lucide="arrow-right"></i>

                            </a>


                        </article>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="mobile-history-empty">

                        <div class="history-empty-icon">

                            <i data-lucide="history"></i>

                        </div>

                        <h3>
                            Belum Ada Riwayat
                        </h3>

                        <p>
                            Tidak ada data yang sesuai dengan filter.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </section>


    </div>

</main>



<style>

/* =========================================================
   PETUGAS - RIWAYAT
========================================================= */

.staff-history-page {

    min-height: calc(100vh - 76px);

    padding:
        38px
        24px
        70px;

    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(37, 99, 235, .06),
            transparent 28%
        ),
        radial-gradient(
            circle at 95% 10%,
            rgba(59, 130, 246, .04),
            transparent 25%
        ),
        #f8fafc;

}


.staff-history-shell {

    width: min(1160px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.history-page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}


.history-heading {

    min-width: 0;

}


.history-eyebrow {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .1em;

}


.history-page-header h1 {

    margin: 0;

    color: #0f172a;

    font-size: 27px;

    font-weight: 800;

    line-height: 1.15;

    letter-spacing: -.5px;

}


.history-page-header p {

    margin: 6px 0 0;

    color: #64748b;

    font-size: 10px;

    line-height: 1.5;

}


.history-back-btn {

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

    white-space: nowrap;

    transition: .2s ease;

}


.history-back-btn:hover {

    color: #2563eb;

    background: #eff6ff;

    border-color: #bfdbfe;

    transform: translateY(-1px);

}


.history-back-btn svg {

    width: 14px;

    height: 14px;

}


/* =========================================================
   STATISTICS
========================================================= */

.history-stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 13px;

    margin-bottom: 18px;

}


.history-stat {

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


.history-stat-icon {

    width: 38px;

    height: 38px;

    min-width: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

}


.history-stat-icon svg {

    width: 18px;

    height: 18px;

}


.history-stat > div:last-child {

    min-width: 0;

}


.history-stat span {

    display: block;

    color: #94a3b8;

    font-size: 7px;

    line-height: 1.3;

}


.history-stat strong {

    display: block;

    margin-top: 3px;

    color: #0f172a;

    font-size: 19px;

    font-weight: 800;

    line-height: 1;

}


.history-stat.blue .history-stat-icon {

    color: #2563eb;

    background: #eff6ff;

}


.history-stat.amber .history-stat-icon {

    color: #b45309;

    background: #fffbeb;

}


.history-stat.violet .history-stat-icon {

    color: #7c3aed;

    background: #f5f3ff;

}


.history-stat.green .history-stat-icon {

    color: #15803d;

    background: #f0fdf4;

}


/* =========================================================
   MAIN CARD
========================================================= */

.history-card {

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

.history-toolbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 18px;

    padding: 18px 19px;

    border-bottom: 1px solid #eef2f7;

}


.history-toolbar-heading {

    min-width: 0;

}


.history-toolbar-heading span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .09em;

}


.history-toolbar-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

}


/* =========================================================
   FILTER
========================================================= */

.history-filter {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-shrink: 0;

}


.history-search {

    position: relative;

    width: 190px;

}


.history-search > svg {

    position: absolute;

    left: 10px;

    top: 50%;

    width: 14px;

    height: 14px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}


.history-search input {

    width: 100%;

    height: 38px;

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

    box-sizing: border-box;

    transition: .2s ease;

}


.history-search input:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.07);

}


.history-select {

    position: relative;

}


.history-select > svg {

    position: absolute;

    left: 10px;

    top: 50%;

    width: 13px;

    height: 13px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}


.history-select select {

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


.history-search-btn {

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


.history-search-btn:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-1px);

}


.history-search-btn svg {

    width: 13px;

    height: 13px;

}


/* =========================================================
   TABLE
========================================================= */

.history-table-wrap {

    width: 100%;

    overflow-x: auto;

}


.history-table {

    width: 100%;

    min-width: 1120px;

    border-collapse: collapse;

}


.history-table thead th {

    padding: 11px 13px;

    color: #94a3b8;

    background: #f8fafc;

    border-bottom: 1px solid #eef2f7;

    font-size: 6.5px;

    font-weight: 800;

    text-align: left;

    text-transform: uppercase;

    letter-spacing: .05em;

    white-space: nowrap;

}


.history-table tbody td {

    padding: 13px;

    border-bottom: 1px solid #f1f5f9;

    vertical-align: middle;

}


.history-table tbody tr {

    transition: background .2s ease;

}


.history-table tbody tr:hover {

    background: #fafcff;

}


.history-table tbody tr:last-child td {

    border-bottom: none;

}


/* =========================================================
   REPORT
========================================================= */

.history-report {

    display: flex;

    align-items: center;

    gap: 8px;

    min-width: 145px;

}


.history-report-icon {

    width: 33px;

    height: 33px;

    min-width: 33px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 8px;

}


.history-report-icon svg {

    width: 15px;

    height: 15px;

}


.history-report > div:last-child {

    min-width: 0;

}


.history-report strong {

    display: block;

    max-width: 120px;

    overflow: hidden;

    color: #334155;

    font-size: 8px;

    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;

}


.history-report span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 7px;

}


/* =========================================================
   REPORTER
========================================================= */

.history-reporter {

    min-width: 105px;

}


.history-reporter strong {

    display: block;

    color: #334155;

    font-size: 8px;

    font-weight: 700;

}


.history-reporter span {

    display: block;

    max-width: 120px;

    margin-top: 3px;

    overflow: hidden;

    color: #94a3b8;

    font-size: 6.5px;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   CATEGORY
========================================================= */

.history-category {

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


.history-category svg {

    width: 11px;

    height: 11px;

    color: #64748b;

}


/* =========================================================
   LOCATION
========================================================= */

.history-location {

    display: flex;

    align-items: flex-start;

    gap: 5px;

    max-width: 135px;

    color: #64748b;

    font-size: 7px;

    line-height: 1.4;

}


.history-location svg {

    width: 12px;

    height: 12px;

    min-width: 12px;

    color: #94a3b8;

    margin-top: 1px;

}


/* =========================================================
   NOTE
========================================================= */

.history-note {

    display: flex;

    align-items: flex-start;

    gap: 5px;

    max-width: 145px;

    color: #64748b;

    font-size: 7px;

    line-height: 1.45;

}


.history-note svg {

    width: 12px;

    height: 12px;

    min-width: 12px;

    margin-top: 1px;

    color: #94a3b8;

}


.history-note span {

    display: -webkit-box;

    overflow: hidden;

    -webkit-box-orient: vertical;

    -webkit-line-clamp: 2;

}


.history-no-note {

    color: #cbd5e1;

    font-size: 7px;

    font-style: italic;

    white-space: nowrap;

}


/* =========================================================
   STATUS
========================================================= */

.history-status {

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


.history-status svg {

    width: 12px;

    height: 12px;

}


.history-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}


.history-status.processing {

    color: #7c3aed;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}


.history-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}


/* =========================================================
   DATE
========================================================= */

.history-date {

    min-width: 85px;

}


.history-date strong {

    display: block;

    color: #475569;

    font-size: 7px;

    font-weight: 700;

}


.history-date span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 6.5px;

}


/* =========================================================
   DETAIL
========================================================= */

.history-detail-btn {

    min-height: 31px;

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

    white-space: nowrap;

    transition: .2s ease;

}


.history-detail-btn:hover {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

    transform: translateY(-1px);

}


.history-detail-btn svg {

    width: 12px;

    height: 12px;

}


/* =========================================================
   EMPTY
========================================================= */

.history-empty-cell {

    padding: 0 !important;

}


.history-empty {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 300px;

    padding: 30px;

    text-align: center;

}


.history-empty-icon {

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


.history-empty-icon svg {

    width: 24px;

    height: 24px;

}


.history-empty h3 {

    margin: 0;

    color: #334155;

    font-size: 13px;

    font-weight: 800;

}


.history-empty p {

    max-width: 330px;

    margin: 6px 0 15px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.6;

}


.history-reset-btn {

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


.history-reset-btn svg {

    width: 13px;

    height: 13px;

}


/* =========================================================
   MOBILE LIST - DEFAULT HIDDEN
========================================================= */

.history-mobile-list {

    display: none;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1000px) {

    .history-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

    .history-toolbar {

        align-items: flex-start;

        flex-direction: column;

    }

    .history-filter {

        width: 100%;

    }

    .history-search {

        flex: 1;

        width: auto;

    }

}


/* =========================================================
   SMALL TABLET
========================================================= */

@media (max-width: 760px) {

    .staff-history-page {

        padding:
            28px
            15px
            50px;

    }

    .history-page-header {

        align-items: flex-start;

        flex-direction: column;

    }

    .history-back-btn {

        width: 100%;

    }

    .history-filter {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr)
            auto;

        gap: 7px;

    }

    .history-search {

        width: 100%;

    }

    .history-select {

        grid-column: 1 / -1;

        width: 100%;

    }

    .history-select select {

        width: 100%;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 620px) {

    .staff-history-page {

        width: 100%;

        max-width: 100%;

        box-sizing: border-box;

        padding:
            25px
            12px
            45px;

        overflow-x: hidden;

    }

    .staff-history-shell {

        width: 100%;

        max-width: 100%;

    }


    /* HEADER */

    .history-page-header {

        margin-bottom: 18px;

    }

    .history-page-header h1 {

        font-size: 24px;

    }

    .history-page-header p {

        font-size: 9px;

        line-height: 1.6;

    }

    .history-back-btn {

        min-height: 40px;

    }


    /* STATS */

    .history-stats {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 9px;

        margin-bottom: 15px;

    }

    .history-stat {

        padding: 12px;

        gap: 8px;

        border-radius: 11px;

    }

    .history-stat-icon {

        width: 32px;

        height: 32px;

        min-width: 32px;

        border-radius: 8px;

    }

    .history-stat-icon svg {

        width: 15px;

        height: 15px;

    }

    .history-stat span {

        font-size: 6px;

    }

    .history-stat strong {

        font-size: 16px;

    }


    /* CARD */

    .history-card {

        width: 100%;

        border-radius: 13px;

    }


    /* TOOLBAR */

    .history-toolbar {

        padding: 15px;

        gap: 13px;

    }

    .history-toolbar-heading h2 {

        font-size: 13px;

    }

    .history-toolbar-heading span {

        font-size: 6px;

    }


    .history-filter {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr)
            auto;

        width: 100%;

    }

    .history-search {

        width: 100%;

    }

    .history-search input {

        height: 40px;

        font-size: 8px;

    }

    .history-select {

        grid-column: 1 / -1;

        width: 100%;

    }

    .history-select select {

        width: 100%;

        height: 40px;

    }

    .history-search-btn {

        height: 40px;

        padding: 0 12px;

    }


    /* HIDE TABLE */

    .history-table-wrap {

        display: none;

    }


    /* SHOW MOBILE */

    .history-mobile-list {

        display: flex;

        flex-direction: column;

        gap: 10px;

        padding: 10px;

        background: #f8fafc;

    }


    /* MOBILE CARD */

    .history-mobile-card {

        width: 100%;

        box-sizing: border-box;

        padding: 14px;

        background: #fff;

        border: 1px solid #e2e8f0;

        border-radius: 12px;

        box-shadow:
            0 4px 15px rgba(15,23,42,.035);

    }


    .mobile-history-top {

        display: flex;

        align-items: flex-start;

        justify-content: space-between;

        gap: 9px;

    }


    .mobile-history-report {

        display: flex;

        align-items: center;

        gap: 8px;

        min-width: 0;

        flex: 1;

    }


    .mobile-history-report > div:last-child {

        min-width: 0;

    }


    .mobile-history-report strong {

        display: block;

        max-width: 170px;

        overflow: hidden;

        color: #334155;

        font-size: 8px;

        font-weight: 800;

        text-overflow: ellipsis;

        white-space: nowrap;

    }


    .mobile-history-report span {

        display: block;

        margin-top: 3px;

        color: #94a3b8;

        font-size: 6px;

    }


    .mobile-history-top .history-status {

        flex-shrink: 0;

        min-height: 23px;

        padding: 0 7px;

        font-size: 6px;

    }


    .mobile-history-top .history-status svg {

        width: 10px;

        height: 10px;

    }


    .mobile-history-divider {

        width: 100%;

        height: 1px;

        margin: 13px 0;

        background: #eef2f7;

    }


    /* INFO */

    .mobile-history-info {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 12px 10px;

    }


    .mobile-history-item {

        min-width: 0;

    }


    .mobile-history-item.full {

        grid-column: 1 / -1;

    }


    .mobile-history-item > span {

        display: block;

        margin-bottom: 4px;

        color: #94a3b8;

        font-size: 6px;

        font-weight: 600;

    }


    .mobile-history-item > strong {

        display: block;

        overflow: hidden;

        color: #475569;

        font-size: 8px;

        font-weight: 700;

        text-overflow: ellipsis;

        white-space: nowrap;

    }


    .mobile-history-location {

        display: flex;

        align-items: flex-start;

        gap: 5px;

        color: #475569;

        font-size: 8px;

        line-height: 1.45;

    }


    .mobile-history-location svg {

        width: 11px;

        height: 11px;

        min-width: 11px;

        color: #94a3b8;

        margin-top: 1px;

    }


    /* NOTE */

    .mobile-history-note {

        display: flex;

        align-items: flex-start;

        gap: 6px;

        color: #64748b;

        font-size: 8px;

        line-height: 1.5;

    }


    .mobile-history-note svg {

        width: 12px;

        height: 12px;

        min-width: 12px;

        color: #94a3b8;

        margin-top: 1px;

    }


    .mobile-history-note span {

        display: -webkit-box;

        overflow: hidden;

        -webkit-box-orient: vertical;

        -webkit-line-clamp: 3;

    }


    .mobile-no-note {

        color: #cbd5e1;

        font-size: 8px;

        font-style: italic;

    }


    /* DETAIL */

    .mobile-history-detail {

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

        box-sizing: border-box;

    }


    .mobile-history-detail:hover {

        color: #fff;

        background: #2563eb;

    }


    .mobile-history-detail svg {

        width: 13px;

        height: 13px;

    }


    /* EMPTY */

    .mobile-history-empty {

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


    .mobile-history-empty h3 {

        margin: 0;

        color: #334155;

        font-size: 13px;

        font-weight: 800;

    }


    .mobile-history-empty p {

        margin: 6px 0 0;

        color: #94a3b8;

        font-size: 8px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 420px) {

    .staff-history-page {

        padding-left: 10px;

        padding-right: 10px;

    }

    .history-page-header h1 {

        font-size: 22px;

    }

    .history-stats {

        gap: 7px;

    }

    .history-stat {

        padding: 10px;

    }

    .history-stat-icon {

        width: 29px;

        height: 29px;

        min-width: 29px;

    }

    .history-stat strong {

        font-size: 15px;

    }

    .history-toolbar {

        padding: 14px;

    }

    .history-mobile-list {

        padding: 8px;

    }

    .history-mobile-card {

        padding: 12px;

    }

}


/* =========================================================
   VERY SMALL MOBILE
========================================================= */

@media (max-width: 360px) {

    .staff-history-page {

        padding-left: 8px;

        padding-right: 8px;

    }

    .history-stats {

        grid-template-columns: 1fr;

    }

    .mobile-history-top {

        flex-direction: column;

        align-items: stretch;

    }

    .mobile-history-top .history-status {

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



<script nonce="<?= e(cspNonce()) ?>">

/* =========================================================
   INITIALIZE
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =================================================
           LUCIDE
        ================================================= */

        if (window.lucide) {

            lucide.createIcons();

        }


        /* =================================================
           LENIS
        ================================================= */

        let lenis = null;

        if (
            typeof Lenis !== "undefined"
        ) {

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


        /* =================================================
           GSAP
        ================================================= */

        if (
            typeof gsap !== "undefined"
        ) {

            gsap.set(
                ".history-page-header, .history-stat, .history-card",
                {
                    opacity: 0,
                    y: 18
                }
            );


            gsap.to(
                ".history-page-header",
                {
                    opacity: 1,
                    y: 0,
                    duration: .65,
                    ease: "power3.out"
                }
            );


            gsap.to(
                ".history-stat",
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
                ".history-card",
                {
                    opacity: 1,
                    y: 0,
                    duration: .65,
                    delay: .28,
                    ease: "power3.out"
                }
            );

        }


        /* =================================================
           FILTER LOADING
        ================================================= */

        const filter =
            document.getElementById(
                "historyFilter"
            );


        if (filter) {

            filter.addEventListener(
                "submit",
                function () {

                    const button =
                        filter.querySelector(
                            ".history-search-btn"
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


        /* =================================================
           NAVBAR SCROLL
        ================================================= */

        const navbar =
            document.querySelector(
                ".navbar"
            );


        if (navbar) {

            const updateNavbar =
                function () {

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

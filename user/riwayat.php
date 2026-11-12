<?php


require_once "../config/database.php";
requireRole('pengguna');


/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

$id_user = $_SESSION['user_id'] ?? $_SESSION['id_user'];


/* =========================================================
   DATA USER
========================================================= */

$stmtUser = mysqli_prepare(
    $conn,
    "
    SELECT
        id_user,
        nama,
        email,
        fakultas
    FROM users
    WHERE id_user = ?
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

$user = mysqli_fetch_assoc($resultUser);

if (!$user) {
    session_destroy();

    header("Location: ../login.php");
    exit;
}


/* =========================================================
   FILTER STATUS
========================================================= */

$filter = trim(getString('status', 'Semua'));

$allowedFilter = [
    'Semua',
    'Menunggu',
    'Diproses',
    'Selesai'
];

if (!in_array($filter, $allowedFilter, true)) {
    $filter = 'Semua';
}


/* =========================================================
   SEARCH
========================================================= */

$search = mb_substr(trim(getString('q')), 0, 200);


/* =========================================================
   QUERY PENGADUAN
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

        k.nama_kategori

    FROM pengaduan p

    INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori

    WHERE p.id_user = ?
";


if ($filter !== 'Semua') {
    $sql .= " AND p.status = ? ";
}


if ($search !== '') {
    $sql .= "
        AND (
            p.no_laporan LIKE ?
            OR p.lokasi LIKE ?
            OR p.deskripsi LIKE ?
            OR k.nama_kategori LIKE ?
        )
    ";
}


$sql .= "
    ORDER BY p.tanggal_lapor DESC
";


$stmt = mysqli_prepare($conn, $sql);


if ($filter !== 'Semua' && $search !== '') {

    $searchLike = '%' . $search . '%';

    mysqli_stmt_bind_param(
        $stmt,
        "isssss",
        $id_user,
        $filter,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );

} elseif ($filter !== 'Semua') {

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $id_user,
        $filter
    );

} elseif ($search !== '') {

    $searchLike = '%' . $search . '%';

    mysqli_stmt_bind_param(
        $stmt,
        "issss",
        $id_user,
        $searchLike,
        $searchLike,
        $searchLike,
        $searchLike
    );

} else {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_user
    );

}


mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$pengaduan = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pengaduan[] = $row;
}


/* =========================================================
   STATISTIK
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
    WHERE id_user = ?
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

$total = (int)($stats['total'] ?? 0);
$totalMenunggu = (int)($stats['menunggu'] ?? 0);
$totalDiproses = (int)($stats['diproses'] ?? 0);
$totalSelesai = (int)($stats['selesai'] ?? 0);


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
   SUCCESS MESSAGE
========================================================= */

$success =
    isset($_GET['success']) &&
    $_GET['success'] === '1';

$laporanBaru =
    mb_substr(trim(getString('laporan')), 0, 80);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Riwayat Pengaduan | ADUIN</title>

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

$activePage = 'riwayat';

include '../includes/navbar.php';

?>


<main class="history-page">

    <div class="history-shell">


        <!-- =====================================================
             HEADER
        ===================================================== -->

        <section class="history-header">

            <div class="history-header-content">

                <span class="history-eyebrow">
                    PENGADUAN SAYA
                </span>

                <h1>
                    Riwayat Pengaduan
                </h1>

                <p>
                    Pantau seluruh laporan fasilitas kampus
                    yang pernah Anda kirimkan melalui ADUIN.
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

        </section>



        <!-- =====================================================
             SUCCESS ALERT
        ===================================================== -->

        <?php if ($success): ?>

            <section
                class="success-alert"
                id="successAlert"
            >

                <div class="success-alert-icon">

                    <i data-lucide="circle-check"></i>

                </div>


                <div class="success-alert-content">

                    <strong>
                        Pengaduan berhasil dikirim
                    </strong>

                    <p>

                        <?php if ($laporanBaru !== ''): ?>

                            Nomor laporan Anda adalah
                            <strong>
                                <?= htmlspecialchars($laporanBaru) ?>
                            </strong>.
                            Simpan nomor tersebut untuk
                            melacak pengaduan.

                        <?php else: ?>

                            Pengaduan Anda berhasil
                            diterima oleh sistem ADUIN.

                        <?php endif; ?>

                    </p>

                </div>


                <button
                    type="button"
                    class="alert-close"
                    id="closeAlert"
                    aria-label="Tutup"
                >

                    <i data-lucide="x"></i>

                </button>

            </section>

        <?php endif; ?>



        <!-- =====================================================
             STATISTIK
        ===================================================== -->

        <section class="history-stats">


            <div class="history-stat-card">

                <div class="history-stat-icon blue">

                    <i data-lucide="files"></i>

                </div>


                <div>

                    <span>
                        Total Pengaduan
                    </span>

                    <strong>
                        <?= $total ?>
                    </strong>

                </div>

            </div>



            <div class="history-stat-card">

                <div class="history-stat-icon amber">

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



            <div class="history-stat-card">

                <div class="history-stat-icon violet">

                    <i data-lucide="loader-circle"></i>

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



            <div class="history-stat-card">

                <div class="history-stat-icon green">

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
             TOOLBAR
        ===================================================== -->

        <section class="history-toolbar">


            <div class="filter-tabs">

                <a
                    href="riwayat.php"
                    class="<?= $filter === 'Semua' ? 'active' : '' ?>"
                >
                    Semua

                    <span>
                        <?= $total ?>
                    </span>
                </a>


                <a
                    href="riwayat.php?status=Menunggu"
                    class="<?= $filter === 'Menunggu' ? 'active' : '' ?>"
                >
                    Menunggu

                    <span>
                        <?= $totalMenunggu ?>
                    </span>
                </a>


                <a
                    href="riwayat.php?status=Diproses"
                    class="<?= $filter === 'Diproses' ? 'active' : '' ?>"
                >
                    Diproses

                    <span>
                        <?= $totalDiproses ?>
                    </span>
                </a>


                <a
                    href="riwayat.php?status=Selesai"
                    class="<?= $filter === 'Selesai' ? 'active' : '' ?>"
                >
                    Selesai

                    <span>
                        <?= $totalSelesai ?>
                    </span>
                </a>

            </div>



            <form
                action="riwayat.php"
                method="GET"
                class="history-search"
            >

                <?php if ($filter !== 'Semua'): ?>

                    <input
                        type="hidden"
                        name="status"
                        value="<?= htmlspecialchars($filter) ?>"
                    >

                <?php endif; ?>


                <div class="history-search-input">

                    <i data-lucide="search"></i>

                    <input
                        type="text"
                        name="q"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Cari laporan..."
                        autocomplete="off"
                    >

                </div>


                <button
                    type="submit"
                    aria-label="Cari"
                >

                    <i data-lucide="search"></i>

                </button>


                <?php if ($search !== ''): ?>

                    <a
                        href="riwayat.php<?= $filter !== 'Semua'
                            ? '?status=' . urlencode($filter)
                            : '' ?>"
                        class="clear-search"
                        title="Hapus pencarian"
                    >

                        <i data-lucide="x"></i>

                    </a>

                <?php endif; ?>

            </form>


        </section>



        <!-- =====================================================
             LIST PENGADUAN
        ===================================================== -->

        <section class="history-content">


            <?php if (count($pengaduan) > 0): ?>


                <div class="history-list">


                    <?php foreach ($pengaduan as $index => $row): ?>


                        <?php

                        $status = $row['status'];

                        $statusCls =
                            statusClass($status);

                        $statusIconName =
                            statusIcon($status);

                        ?>


                        <article
                            class="history-card"
                            data-index="<?= $index ?>"
                        >


                            <!-- =================================
                                 CARD HEADER
                            ================================== -->

                            <div class="history-card-top">

                                <div class="history-report-info">

                                    <div class="report-icon">

                                        <i data-lucide="file-text"></i>

                                    </div>


                                    <div class="report-info-text">

                                        <span class="report-label">
                                            Nomor Laporan
                                        </span>

                                        <strong class="report-number">
                                            <?= htmlspecialchars(
                                                $row['no_laporan']
                                            ) ?>
                                        </strong>

                                    </div>

                                </div>


                                <div
                                    class="
                                        history-status
                                        <?= $statusCls ?>
                                    "
                                >

                                    <i
                                        data-lucide="<?= $statusIconName ?>"
                                    ></i>

                                    <span>
                                        <?= htmlspecialchars($status) ?>
                                    </span>

                                </div>

                            </div>



                            <!-- =================================
                                 CARD BODY
                            ================================== -->

                            <div class="history-card-body">


                                <!-- FOTO -->

                                <div class="history-photo">

                                    <?php if (!empty($row['foto'])): ?>

                                        <img
                                            src="<?= e(uploadUrl($row['foto'], 'pengaduan')) ?>"
                                            alt="Foto pengaduan"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <div class="history-photo-empty">

                                            <i data-lucide="image-off"></i>

                                            <span>
                                                Tidak ada foto
                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>



                                <!-- INFORMASI -->

                                <div class="history-main-info">


                                    <div class="history-category">

                                        <span>
                                            Kategori
                                        </span>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $row['nama_kategori']
                                            ) ?>
                                        </strong>

                                    </div>



                                    <div class="history-location">

                                        <i data-lucide="map-pin"></i>

                                        <span>
                                            <?= htmlspecialchars(
                                                $row['lokasi']
                                            ) ?>
                                        </span>

                                    </div>



                                    <div class="history-date">

                                        <div>

                                            <i data-lucide="calendar-days"></i>

                                            <span>
                                                <?= formatTanggal(
                                                    $row['tanggal_lapor']
                                                ) ?>
                                            </span>

                                        </div>


                                        <div>

                                            <i data-lucide="clock-3"></i>

                                            <span>
                                                <?= formatWaktu(
                                                    $row['tanggal_lapor']
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>



                                    <div class="history-description">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $row['deskripsi']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            </div>



                            <!-- =================================
                                 PROGRESS
                            ================================== -->

                            <div class="history-progress">


                                <!-- STEP 1 -->

                                <div
                                    class="
                                        progress-step
                                        <?= in_array(
                                            $status,
                                            [
                                                'Menunggu',
                                                'Diproses',
                                                'Selesai'
                                            ],
                                            true
                                        )
                                            ? 'done'
                                            : '' ?>

                                        <?= $status === 'Menunggu'
                                            ? 'current'
                                            : '' ?>
                                    "
                                >

                                    <div class="progress-circle">

                                        <?php if (
                                            in_array(
                                                $status,
                                                [
                                                    'Menunggu',
                                                    'Diproses',
                                                    'Selesai'
                                                ],
                                                true
                                            )
                                        ): ?>

                                            <i data-lucide="check"></i>

                                        <?php endif; ?>

                                    </div>

                                    <span>
                                        Menunggu
                                    </span>

                                </div>



                                <div
                                    class="
                                        progress-line
                                        <?= in_array(
                                            $status,
                                            [
                                                'Diproses',
                                                'Selesai'
                                            ],
                                            true
                                        )
                                            ? 'done'
                                            : '' ?>
                                    "
                                ></div>



                                <!-- STEP 2 -->

                                <div
                                    class="
                                        progress-step
                                        <?= in_array(
                                            $status,
                                            [
                                                'Diproses',
                                                'Selesai'
                                            ],
                                            true
                                        )
                                            ? 'done'
                                            : '' ?>

                                        <?= $status === 'Diproses'
                                            ? 'current'
                                            : '' ?>
                                    "
                                >

                                    <div class="progress-circle">

                                        <?php if (
                                            in_array(
                                                $status,
                                                [
                                                    'Diproses',
                                                    'Selesai'
                                                ],
                                                true
                                            )
                                        ): ?>

                                            <i data-lucide="check"></i>

                                        <?php endif; ?>

                                    </div>

                                    <span>
                                        Diproses
                                    </span>

                                </div>



                                <div
                                    class="
                                        progress-line
                                        <?= $status === 'Selesai'
                                            ? 'done'
                                            : '' ?>
                                    "
                                ></div>



                                <!-- STEP 3 -->

                                <div
                                    class="
                                        progress-step
                                        <?= $status === 'Selesai'
                                            ? 'done current'
                                            : '' ?>
                                    "
                                >

                                    <div class="progress-circle">

                                        <?php if (
                                            $status === 'Selesai'
                                        ): ?>

                                            <i data-lucide="check"></i>

                                        <?php endif; ?>

                                    </div>

                                    <span>
                                        Selesai
                                    </span>

                                </div>


                            </div>



                            <!-- =================================
                                 CARD FOOTER
                            ================================== -->

                            <div class="history-card-footer">


                                <div class="footer-status-text">

                                    <?php if (
                                        $status === 'Menunggu'
                                    ): ?>

                                        <i data-lucide="clock-3"></i>

                                        <span>
                                            Menunggu verifikasi admin
                                        </span>

                                    <?php elseif (
                                        $status === 'Diproses'
                                    ): ?>

                                        <i data-lucide="wrench"></i>

                                        <span>
                                            Sedang ditangani petugas
                                        </span>

                                    <?php else: ?>

                                        <i data-lucide="circle-check"></i>

                                        <span>
                                            Penanganan telah selesai
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <a
                                    href="../tracking.php?nomor=<?= urlencode(
                                        $row['no_laporan']
                                    ) ?>"
                                    class="history-detail-btn"
                                >

                                    <span>
                                        Lihat Detail
                                    </span>

                                    <i data-lucide="arrow-up-right"></i>

                                </a>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <!-- =================================================
                     EMPTY STATE
                ================================================= -->

                <div class="history-empty">

                    <div class="empty-icon">

                        <?php if ($search !== ''): ?>

                            <i data-lucide="search-x"></i>

                        <?php else: ?>

                            <i data-lucide="file-question"></i>

                        <?php endif; ?>

                    </div>


                    <h2>

                        <?php if ($search !== ''): ?>

                            Pengaduan Tidak Ditemukan

                        <?php elseif ($filter !== 'Semua'): ?>

                            Belum Ada Pengaduan
                            <?= htmlspecialchars($filter) ?>

                        <?php else: ?>

                            Belum Ada Pengaduan

                        <?php endif; ?>

                    </h2>


                    <p>

                        <?php if ($search !== ''): ?>

                            Tidak ada laporan yang cocok dengan
                            kata pencarian
                            <strong>
                                "<?= htmlspecialchars($search) ?>"
                            </strong>.

                        <?php elseif ($filter !== 'Semua'): ?>

                            Anda belum memiliki pengaduan dengan
                            status
                            <strong>
                                <?= htmlspecialchars($filter) ?>
                            </strong>.

                        <?php else: ?>

                            Anda belum membuat pengaduan fasilitas
                            kampus. Silakan buat laporan pertama
                            Anda melalui ADUIN.

                        <?php endif; ?>

                    </p>


                    <?php if (
                        $search !== '' ||
                        $filter !== 'Semua'
                    ): ?>

                        <a
                            href="riwayat.php"
                            class="empty-secondary-btn"
                        >

                            <i data-lucide="rotate-ccw"></i>

                            Reset Filter

                        </a>

                    <?php else: ?>

                        <a
                            href="buat-pengaduan.php"
                            class="empty-primary-btn"
                        >

                            <i data-lucide="plus"></i>

                            Buat Pengaduan

                        </a>

                    <?php endif; ?>

                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================================
             INFO
        ===================================================== -->

        <?php if (count($pengaduan) > 0): ?>

            <section class="history-info">

                <div class="history-info-icon">

                    <i data-lucide="info"></i>

                </div>


                <div>

                    <strong>
                        Butuh informasi lebih lengkap?
                    </strong>

                    <p>
                        Klik <b>Lihat Detail</b> pada laporan
                        untuk melihat status, timeline,
                        foto pengaduan, dan informasi penanganan.
                    </p>

                </div>

            </section>

        <?php endif; ?>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - RIWAYAT PENGADUAN
========================================================= */

.history-page {

    min-height: calc(100vh - 76px);

    padding: 42px 24px 70px;

    background:
        radial-gradient(
            circle at 5% 0%,
            rgba(37, 99, 235, .07),
            transparent 28%
        ),
        radial-gradient(
            circle at 95% 12%,
            rgba(59, 130, 246, .045),
            transparent 25%
        ),
        #f8fafc;

}

.history-shell {

    width: min(1080px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.history-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 25px;

    margin-bottom: 25px;

}

.history-header-content {

    min-width: 0;

}

.history-eyebrow {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: .1em;

}

.history-header h1 {

    margin: 0;

    color: #0f172a;

    font-size: 30px;

    font-weight: 800;

    line-height: 1.15;

    letter-spacing: -.7px;

}

.history-header p {

    max-width: 620px;

    margin: 8px 0 0;

    color: #64748b;

    font-size: 12px;

    line-height: 1.6;

}

.create-report-btn {

    min-height: 42px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    flex-shrink: 0;

    padding: 0 15px;

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

.create-report-btn:hover {

    color: #fff;

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-2px);

    box-shadow:
        0 12px 25px rgba(37, 99, 235, .24);

}

.create-report-btn svg {

    width: 16px;

}


/* =========================================================
   SUCCESS
========================================================= */

.success-alert {

    position: relative;

    display: flex;

    align-items: flex-start;

    gap: 12px;

    margin-bottom: 20px;

    padding: 15px 48px 15px 15px;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

    border-radius: 13px;

}

.success-alert-icon {

    width: 36px;
    height: 36px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #15803d;

    background: #dcfce7;

    border-radius: 9px;

}

.success-alert-icon svg {

    width: 19px;

}

.success-alert-content {

    min-width: 0;

}

.success-alert-content > strong {

    display: block;

    color: #166534;

    font-size: 12px;

}

.success-alert-content p {

    margin: 4px 0 0;

    color: #4d7c5b;

    font-size: 10px;

    line-height: 1.55;

}

.success-alert-content p strong {

    display: inline;

    color: #166534;

}

.alert-close {

    position: absolute;

    top: 13px;
    right: 13px;

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;
    justify-content: center;

    color: #64748b;

    background: transparent;

    border: none;

    border-radius: 7px;

    cursor: pointer;

}

.alert-close:hover {

    color: #166534;

    background: #dcfce7;

}

.alert-close svg {

    width: 15px;

}


/* =========================================================
   STATS
========================================================= */

.history-stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 13px;

    margin-bottom: 20px;

}

.history-stat-card {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 0;

    padding: 15px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 13px;

    box-shadow:
        0 6px 22px rgba(15, 23, 42, .035);

}

.history-stat-icon {

    width: 38px;
    height: 38px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 10px;

}

.history-stat-icon svg {

    width: 18px;

}

.history-stat-icon.blue {

    color: #2563eb;

    background: #eff6ff;

}

.history-stat-icon.amber {

    color: #b45309;

    background: #fffbeb;

}

.history-stat-icon.violet {

    color: #7c3aed;

    background: #f5f3ff;

}

.history-stat-icon.green {

    color: #15803d;

    background: #f0fdf4;

}

.history-stat-card span {

    display: block;

    margin-bottom: 3px;

    color: #94a3b8;

    font-size: 9px;

}

.history-stat-card strong {

    color: #0f172a;

    font-size: 20px;

    font-weight: 800;

    line-height: 1;

}


/* =========================================================
   TOOLBAR
========================================================= */

.history-toolbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 17px;

}

.filter-tabs {

    display: flex;

    align-items: center;

    gap: 4px;

    padding: 4px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 11px;

}

.filter-tabs a {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    min-height: 32px;

    padding: 0 10px;

    color: #64748b;

    border-radius: 7px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    transition: .2s ease;

}

.filter-tabs a:hover {

    color: #2563eb;

    background: #eff6ff;

}

.filter-tabs a.active {

    color: #2563eb;

    background: #eff6ff;

}

.filter-tabs a span {

    min-width: 17px;
    height: 17px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0 4px;

    color: #94a3b8;

    background: #f8fafc;

    border-radius: 5px;

    font-size: 8px;

}

.filter-tabs a.active span {

    color: #2563eb;

    background: #dbeafe;

}


/* =========================================================
   SEARCH
========================================================= */

.history-search {

    position: relative;

    display: flex;

    align-items: center;

    width: 220px;

}

.history-search-input {

    position: relative;

    width: 100%;

}

.history-search-input > svg {

    position: absolute;

    top: 50%;
    left: 12px;

    width: 14px;
    height: 14px;

    color: #94a3b8;

    transform: translateY(-50%);

    pointer-events: none;

}

.history-search-input input {

    width: 100%;
    height: 38px;

    box-sizing: border-box;

    padding: 0 37px;

    color: #334155;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 10px;

}

.history-search-input input:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, .07);

}

.history-search > button {

    position: absolute;

    right: 4px;

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;
    justify-content: center;

    color: #64748b;

    background: transparent;

    border: none;

    border-radius: 7px;

    cursor: pointer;

}

.history-search > button:hover {

    color: #2563eb;

    background: #eff6ff;

}

.history-search > button svg {

    width: 14px;

}

.clear-search {

    position: absolute;

    right: 37px;

    width: 25px;
    height: 25px;

    display: flex;

    align-items: center;
    justify-content: center;

    color: #94a3b8;

    background: #f8fafc;

    border-radius: 6px;

    text-decoration: none;

}

.clear-search:hover {

    color: #ef4444;

}

.clear-search svg {

    width: 12px;

}


/* =========================================================
   HISTORY LIST
========================================================= */

.history-list {

    display: flex;

    flex-direction: column;

    gap: 16px;

}

.history-card {

    width: 100%;

    overflow: hidden;

    box-sizing: border-box;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

    transition:
        transform .3s ease,
        box-shadow .3s ease,
        border-color .3s ease;

}

.history-card:hover {

    border-color: #d6e2f0;

    transform: translateY(-2px);

    box-shadow:
        0 14px 35px rgba(15, 23, 42, .065);

}


/* =========================================================
   CARD TOP
========================================================= */

.history-card-top {

    width: 100%;

    min-height: 67px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-sizing: border-box;

    gap: 15px;

    padding: 14px 18px;

    border-bottom: 1px solid #eef2f7;

}

.history-report-info {

    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;

}

.report-icon {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 9px;

}

.report-icon svg {

    width: 17px;
    height: 17px;

}

.report-info-text {

    min-width: 0;

}

.report-label {

    display: block;

    margin-bottom: 3px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1;

}

.report-number {

    display: block;

    overflow: hidden;

    color: #0f172a;

    font-size: 11px;

    font-weight: 800;

    line-height: 1.2;

    letter-spacing: .25px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.history-status {

    min-width: 0;
    min-height: 30px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 6px;

    padding: 0 10px;

    flex-shrink: 0;

    box-sizing: border-box;

    border-radius: 8px;

    font-size: 9px;

    font-weight: 800;

    line-height: 1;

}

.history-status svg {

    width: 14px;
    height: 14px;

    flex-shrink: 0;

}

.history-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}

.history-status.processing {

    color: #6d28d9;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}

.history-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}


/* =========================================================
   CARD BODY
========================================================= */

.history-card-body {

    width: 100%;

    display: grid;

    grid-template-columns: 155px minmax(0, 1fr);

    gap: 17px;

    box-sizing: border-box;

    padding: 17px 18px;

}

.history-photo {

    width: 155px;
    height: 120px;

    overflow: hidden;

    box-sizing: border-box;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

}

.history-photo img {

    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;

}

.history-photo-empty {

    width: 100%;
    height: 100%;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    gap: 6px;

    color: #94a3b8;

}

.history-photo-empty svg {

    width: 21px;
    height: 21px;

}

.history-photo-empty span {

    font-size: 8px;

}

.history-main-info {

    min-width: 0;

}

.history-category {

    margin-bottom: 8px;

}

.history-category span {

    display: block;

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1;

}

.history-category strong {

    display: block;

    color: #334155;

    font-size: 12px;

    font-weight: 800;

    line-height: 1.2;

}

.history-location {

    display: flex;

    align-items: center;

    gap: 5px;

    margin-bottom: 10px;

    min-width: 0;

    color: #64748b;

}

.history-location svg {

    width: 13px;
    height: 13px;

    flex-shrink: 0;

    color: #2563eb;

}

.history-location span {

    display: block;

    overflow: hidden;

    font-size: 9px;

    line-height: 1.3;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.history-date {

    display: flex;

    align-items: center;

    flex-wrap: wrap;

    gap: 14px;

    margin-bottom: 10px;

}

.history-date > div {

    display: flex;

    align-items: center;

    gap: 5px;

    color: #94a3b8;

}

.history-date svg {

    width: 12px;
    height: 12px;

    flex-shrink: 0;

}

.history-date span {

    font-size: 8px;

    line-height: 1.2;

}

.history-description {

    display: -webkit-box;

    overflow: hidden;

    color: #64748b;

    font-size: 9px;

    line-height: 1.55;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

}


/* =========================================================
   PROGRESS
========================================================= */

.history-progress {

    width: 100%;

    height: 50px;
    min-height: 50px;

    display: flex;

    align-items: center;

    box-sizing: border-box;

    padding: 0 18px;

    border-top: 1px solid #eef2f7;

    overflow: hidden;

}

.progress-step {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    flex-shrink: 0;

    min-width: 0;

}

.progress-circle {

    width: 22px;
    height: 22px;

    min-width: 22px;
    min-height: 22px;

    display: flex;

    align-items: center;
    justify-content: center;

    box-sizing: border-box;

    color: #94a3b8;

    background: #f8fafc;

    border: 1px solid #dbe3ec;

    border-radius: 50%;

}

.progress-circle svg {

    width: 11px;
    height: 11px;

    min-width: 11px;
    min-height: 11px;

}

.progress-step > span {

    display: block;

    margin: 0;

    padding: 0;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1;

    font-weight: 700;

    white-space: nowrap;

}

.progress-step.done .progress-circle {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

}

.progress-step.done > span {

    color: #2563eb;

}

.progress-step.current .progress-circle {

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, .10);

}

.progress-line {

    flex: 1 1 auto;

    width: auto;

    height: 2px;

    min-width: 30px;

    max-width: 180px;

    margin: 0 8px;

    padding: 0;

    background: #e2e8f0;

    border: 0;

}

.progress-line.done {

    background: #93c5fd;

}


/* =========================================================
   CARD FOOTER
========================================================= */

.history-card-footer {

    width: 100%;

    min-height: 54px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-sizing: border-box;

    gap: 15px;

    padding: 10px 18px;

    background: #fafcff;

    border-top: 1px solid #eef2f7;

}

.footer-status-text {

    display: flex;

    align-items: center;

    gap: 6px;

    min-width: 0;

    color: #94a3b8;

}

.footer-status-text svg {

    width: 13px;
    height: 13px;

    min-width: 13px;
    min-height: 13px;

    flex-shrink: 0;

}

.footer-status-text span {

    display: block;

    margin: 0;

    padding: 0;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.2;

    white-space: nowrap;

}

.history-detail-btn {

    width: auto;

    min-width: 92px;

    height: 34px;

    min-height: 34px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 6px;

    box-sizing: border-box;

    flex-shrink: 0;

    padding: 0 11px;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 8px;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    line-height: 1;

    white-space: nowrap;

    transition:
        background .2s ease,
        color .2s ease,
        border-color .2s ease,
        transform .2s ease;

}

.history-detail-btn:hover {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

    transform: translateY(-1px);

}

.history-detail-btn svg {

    width: 13px;
    height: 13px;

    min-width: 13px;
    min-height: 13px;

    flex-shrink: 0;

}


/* =========================================================
   EMPTY
========================================================= */

.history-empty {

    min-height: 360px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    box-sizing: border-box;

    padding: 35px 20px;

    text-align: center;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 16px;

}

.empty-icon {

    width: 62px;
    height: 62px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 14px;

    color: #94a3b8;

    background: #f1f5f9;

    border-radius: 17px;

}

.empty-icon svg {

    width: 28px;
    height: 28px;

}

.history-empty h2 {

    margin: 0;

    color: #334155;

    font-size: 16px;

    font-weight: 800;

}

.history-empty p {

    max-width: 420px;

    margin: 7px 0 18px;

    color: #94a3b8;

    font-size: 10px;

    line-height: 1.6;

}

.history-empty p strong {

    color: #64748b;

}

.empty-primary-btn,
.empty-secondary-btn {

    min-height: 38px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 0 14px;

    border-radius: 9px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;

}

.empty-primary-btn {

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

}

.empty-secondary-btn {

    color: #475569;

    background: #fff;

    border: 1px solid #dbe3ec;

}

.empty-primary-btn svg,
.empty-secondary-btn svg {

    width: 14px;
    height: 14px;

}


/* =========================================================
   INFO
========================================================= */

.history-info {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-top: 16px;

    padding: 14px 16px;

    box-sizing: border-box;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 12px;

}

.history-info-icon {

    width: 32px;
    height: 32px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #dbeafe;

    border-radius: 8px;

}

.history-info-icon svg {

    width: 16px;
    height: 16px;

}

.history-info strong {

    display: block;

    color: #1e3a8a;

    font-size: 10px;

}

.history-info p {

    margin: 3px 0 0;

    color: #64748b;

    font-size: 9px;

    line-height: 1.5;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 900px) {

    .history-page {

        padding: 35px 18px 60px;

    }

    .history-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .history-page {

        padding: 28px 14px 50px;

    }

    .history-header {

        align-items: flex-start;

        flex-direction: column;

        gap: 16px;

    }

    .history-header h1 {

        font-size: 27px;

    }

    .create-report-btn {

        width: 100%;

        box-sizing: border-box;

    }

    .history-toolbar {

        align-items: stretch;

        flex-direction: column;

    }

    .filter-tabs {

        width: 100%;

        overflow-x: auto;

        box-sizing: border-box;

    }

    .filter-tabs a {

        flex: 1;

        min-width: 75px;

    }

    .history-search {

        width: 100%;

    }

    .history-card-body {

        grid-template-columns: 105px minmax(0, 1fr);

        gap: 13px;

    }

    .history-photo {

        width: 105px;

        height: 100px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .history-page {

        padding: 24px 12px 45px;

    }

    .history-stats {

        gap: 9px;

    }

    .history-stat-card {

        padding: 12px;

    }

    .history-stat-icon {

        width: 33px;
        height: 33px;

    }

    .history-stat-icon svg {

        width: 16px;

    }

    .history-stat-card strong {

        font-size: 17px;

    }

    .history-stat-card span {

        font-size: 8px;

    }

    .history-card-top {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;

    }

    .history-status {

        align-self: flex-start;

    }

    .history-card-body {

        grid-template-columns: 1fr;

    }

    .history-photo {

        width: 100%;

        height: 170px;

    }

    .history-date {

        gap: 10px;

    }

    .history-progress {

        height: 48px;

        min-height: 48px;

        padding: 0 14px;

        overflow-x: auto;

        overflow-y: hidden;

    }

    .progress-step {

        flex-shrink: 0;

    }

    .progress-line {

        min-width: 24px;

        margin: 0 6px;

    }

    .history-card-footer {

        min-height: auto;

        align-items: stretch;

        flex-direction: column;

        gap: 9px;

        padding: 11px 14px;

    }

    .footer-status-text {

        min-height: 20px;

    }

    .footer-status-text span {

        white-space: normal;

    }

    .history-detail-btn {

        width: 100%;

    }

    .history-info {

        align-items: flex-start;

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

            const lenis = new Lenis({

                duration: 1.05,

                smoothWheel: true,

                syncTouch: false,

                wheelMultiplier: .9,

                touchMultiplier: 1.2

            });


            function raf(time) {

                lenis.raf(time * 1000);

                requestAnimationFrame(raf);

            }

            requestAnimationFrame(raf);

        }



        /* =====================================================
           GSAP PAGE ANIMATION
        ===================================================== */

        if (window.gsap) {

            gsap.from(
                ".history-header",
                {
                    y: 20,
                    opacity: 0,
                    duration: .6,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".history-stats",
                {
                    y: 18,
                    opacity: 0,
                    duration: .55,
                    delay: .08,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".history-toolbar",
                {
                    y: 15,
                    opacity: 0,
                    duration: .5,
                    delay: .14,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".history-card",
                {
                    y: 18,
                    opacity: 0,
                    duration: .55,
                    stagger: .08,
                    delay: .2,
                    ease: "power3.out"
                }
            );


            if (
                document.querySelector(".history-empty")
            ) {

                gsap.from(
                    ".history-empty",
                    {
                        y: 18,
                        opacity: 0,
                        duration: .55,
                        delay: .2,
                        ease: "power3.out"
                    }
                );

            }


            if (
                document.querySelector(".history-info")
            ) {

                gsap.from(
                    ".history-info",
                    {
                        y: 12,
                        opacity: 0,
                        duration: .5,
                        delay: .35,
                        ease: "power3.out"
                    }
                );

            }


            if (
                document.querySelector(".success-alert")
            ) {

                gsap.from(
                    ".success-alert",
                    {
                        y: -10,
                        opacity: 0,
                        duration: .5,
                        ease: "power3.out"
                    }
                );

            }

        }



        /* =====================================================
           SUCCESS ALERT
        ===================================================== */

        const closeAlert =
            document.getElementById("closeAlert");

        const successAlert =
            document.getElementById("successAlert");


        if (
            closeAlert &&
            successAlert
        ) {

            closeAlert.addEventListener(
                "click",
                function () {

                    if (window.gsap) {

                        gsap.to(
                            successAlert,
                            {
                                height: 0,
                                opacity: 0,
                                marginBottom: 0,
                                paddingTop: 0,
                                paddingBottom: 0,
                                duration: .3,
                                ease: "power2.inOut",

                                onComplete: function () {
                                    successAlert.remove();
                                }

                            }
                        );

                    } else {

                        successAlert.remove();

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
           DETAIL BUTTON HOVER
        ===================================================== */

        document
            .querySelectorAll(".history-detail-btn")
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
                                    y: -2,
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
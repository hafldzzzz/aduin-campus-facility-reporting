<?php

require_once "../config/database.php";
requireRole('admin');

/* =========================================================
   CEK LOGIN ADMIN
========================================================= */

if (!isset($_SESSION["id_user"])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$id_admin = (int) $_SESSION["id_user"];


/* =========================================================
   DATA ADMIN
========================================================= */

$query_admin = dbQuery(
    $conn,
    "SELECT id_user, nama, email, no_wa, fakultas
     FROM users
     WHERE id_user = ?
     LIMIT 1",
    'i',
    [$id_admin]
);

$admin = mysqli_fetch_assoc($query_admin);

if (!$admin) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


/* =========================================================
   HELPER COUNT
========================================================= */

function getCount($conn, $query)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        return 0;
    }

    $data = mysqli_fetch_assoc($result);

    return (int) ($data["total"] ?? 0);
}


/* =========================================================
   STATISTIK
========================================================= */

$total_pengaduan = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM pengaduan"
);

$menunggu = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Menunggu'"
);

$diproses = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Diproses'"
);

$selesai = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Selesai'"
);


/* =========================================================
   DATA PENGGUNA & PETUGAS
========================================================= */

$total_pengguna = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'pengguna'"
);

$total_petugas = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'petugas'"
);


/* =========================================================
   PENGADUAN HARI INI
========================================================= */

$hari_ini = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE DATE(tanggal_lapor) = CURDATE()"
);


/* =========================================================
   PENGADUAN TERBARU
========================================================= */

$query_terbaru = mysqli_query(
    $conn,
    "SELECT
        p.id_pengaduan,
        p.no_laporan,
        p.lokasi,
        p.deskripsi,
        p.status,
        p.tanggal_lapor,
        k.nama_kategori,
        u.nama AS nama_pengguna
     FROM pengaduan p
     INNER JOIN kategori k
        ON p.id_kategori = k.id_kategori
     INNER JOIN users u
        ON p.id_user = u.id_user
     ORDER BY p.tanggal_lapor DESC
     LIMIT 5"
);


/* =========================================================
   KATEGORI
========================================================= */

$query_kategori = mysqli_query(
    $conn,
    "SELECT
        k.nama_kategori,
        COUNT(p.id_pengaduan) AS total
     FROM kategori k
     LEFT JOIN pengaduan p
        ON k.id_kategori = p.id_kategori
     GROUP BY
        k.id_kategori,
        k.nama_kategori
     ORDER BY total DESC
     LIMIT 5"
);

$kategori_data = [];

if ($query_kategori) {

    while ($row = mysqli_fetch_assoc($query_kategori)) {

        $kategori_data[] = $row;
    }
}

$max_kategori = 1;

foreach ($kategori_data as $kategori) {

    if ((int) $kategori["total"] > $max_kategori) {

        $max_kategori =
            (int) $kategori["total"];
    }
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
    Dashboard Admin - Pengaduan Kampus
</title>

<link rel="stylesheet" href="../assets/css/style.css">


<!-- =====================================================
     GOOGLE FONT
====================================================== -->

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>


<!-- =====================================================
     LENIS
====================================================== -->

<link
    rel="stylesheet"
    href="https://unpkg.com/lenis@1.3.26/dist/lenis.css"
>


<!-- =====================================================
     LUCIDE
====================================================== -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://unpkg.com/lucide@latest"
></script>

<script nonce="<?= e(cspNonce()) ?>">
    lucide.createIcons();
</script>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    scroll-behavior: auto;
}

body {
    min-height: 100vh;

    font-family:
        "Inter",
        Arial,
        sans-serif;

    color: #0f172a;

    background: #f8fafc;

    overflow-x: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

button,
input,
select {
    font-family: inherit;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {
    position: sticky;

    top: 0;

    z-index: 1000;

    background:
        rgba(255, 255, 255, .95);

    border-bottom:
        1px solid #e8edf3;

    backdrop-filter:
        blur(16px);

    transition:
        box-shadow .3s ease;
}

.navbar.scrolled {
    box-shadow:
        0 8px 30px
        rgba(15, 23, 42, .07);
}

.nav-container {
    width:
        min(1120px, 92%);

    min-height:
        70px;

    margin:
        auto;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;
}


/* =========================================================
   BRAND
========================================================= */

.brand {
    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    flex-shrink:
        0;
}

.brand-icon {
    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        11px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #1769aa,
            #2563eb
        );

    font-size:
        12px;

    font-weight:
        800;

    box-shadow:
        0 7px 18px
        rgba(37, 99, 235, .18);
}

.brand-text strong {
    display:
        block;

    color:
        #172033;

    font-size:
        14px;

    font-weight:
        800;
}

.brand-text span {
    display:
        block;

    margin-top:
        2px;

    color:
        #64748b;

    font-size:
        8px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav-links {
    display:
        flex;

    align-items:
        center;

    gap:
        3px;
}

.nav-link {
    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        8px 11px;

    border-radius:
        9px;

    color:
        #64748b;

    font-size:
        9px;

    font-weight:
        600;

    transition:
        .2s ease;
}

.nav-link svg {
    width:
        14px;

    height:
        14px;
}

.nav-link:hover,
.nav-link.active {
    color:
        #2563eb;

    background:
        #eff6ff;
}


/* =========================================================
   PROFILE
========================================================= */

.nav-profile {
    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        4px 8px 4px 4px;

    border:
        1px solid #e5eaf0;

    border-radius:
        999px;

    background:
        white;
}

.nav-avatar {
    width:
        30px;

    height:
        30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        50%;

    color:
        white;

    background:
        #2563eb;

    font-size:
        10px;

    font-weight:
        800;
}

.nav-name {
    max-width:
        90px;

    overflow:
        hidden;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;

    font-size:
        8px;

    font-weight:
        700;
}

.logout {
    display:
        flex;

    color:
        #64748b;

    transition:
        .2s ease;
}

.logout:hover {
    color:
        #dc2626;
}

.logout svg {
    width:
        14px;

    height:
        14px;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    width:
        min(1120px, 92%);

    margin:
        auto;

    padding:
        30px 0 55px;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        20px;
}

.hero-content {
    min-width:
        0;
}

.hero-badge {
    width:
        fit-content;

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        6px 10px;

    margin-bottom:
        10px;

    border:
        1px solid #dbeafe;

    border-radius:
        999px;

    color:
        #2563eb;

    background:
        #eff6ff;

    font-size:
        8px;

    font-weight:
        700;
}

.hero-badge svg {
    width:
        13px;

    height:
        13px;
}

.hero h1 {
    font-size:
        clamp(28px, 4vw, 40px);

    line-height:
        1.1;

    letter-spacing:
        -1.5px;

    font-weight:
        800;
}

.hero h1 span {
    color:
        #2563eb;
}

.hero-description {
    max-width:
        650px;

    margin-top:
        8px;

    color:
        #64748b;

    font-size:
        10px;

    line-height:
        1.65;
}

.hero-date {
    flex-shrink:
        0;

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        9px 12px;

    border:
        1px solid #e4eaf1;

    border-radius:
        10px;

    color:
        #64748b;

    background:
        white;

    font-size:
        8px;

    box-shadow:
        0 5px 18px
        rgba(15, 23, 42, .03);
}

.hero-date svg {
    width:
        14px;

    height:
        14px;

    color:
        #2563eb;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats-grid {
    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        12px;

    margin-bottom:
        16px;
}

.stat-card {
    position:
        relative;

    overflow:
        hidden;

    padding:
        16px;

    border:
        1px solid #e4eaf1;

    border-radius:
        15px;

    background:
        white;

    box-shadow:
        0 7px 22px
        rgba(15, 23, 42, .035);

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        border-color .25s ease;
}

.stat-card:hover {
    transform:
        translateY(-4px);

    border-color:
        #d5e4f8;

    box-shadow:
        0 14px 30px
        rgba(15, 23, 42, .07);
}

.stat-card::after {
    content:
        "";

    position:
        absolute;

    width:
        75px;

    height:
        75px;

    right:
        -40px;

    bottom:
        -40px;

    border-radius:
        50%;

    background:
        #eff6ff;

    opacity:
        .6;
}

.stat-icon {
    position:
        relative;

    z-index:
        1;

    width:
        35px;

    height:
        35px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin-bottom:
        11px;

    border-radius:
        10px;
}

.stat-icon svg {
    width:
        16px;

    height:
        16px;
}

.stat-blue .stat-icon {
    color:
        #2563eb;

    background:
        #eff6ff;
}

.stat-orange .stat-icon {
    color:
        #ea580c;

    background:
        #fff7ed;
}

.stat-yellow .stat-icon {
    color:
        #ca8a04;

    background:
        #fefce8;
}

.stat-green .stat-icon {
    color:
        #16a34a;

    background:
        #ecfdf5;
}

.stat-label {
    position:
        relative;

    z-index:
        1;

    color:
        #64748b;

    font-size:
        8px;

    font-weight:
        600;
}

.stat-number {
    position:
        relative;

    z-index:
        1;

    margin-top:
        3px;

    font-size:
        25px;

    line-height:
        1;

    font-weight:
        800;
}

.stat-description {
    position:
        relative;

    z-index:
        1;

    margin-top:
        6px;

    color:
        #94a3b8;

    font-size:
        7px;
}


/* =========================================================
   QUICK ACTION
========================================================= */

.quick-grid {
    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        11px;

    margin-bottom:
        18px;
}

.quick-card {
    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    padding:
        13px;

    border:
        1px solid #e4eaf1;

    border-radius:
        13px;

    color:
        #0f172a;

    background:
        white;

    box-shadow:
        0 6px 20px
        rgba(15, 23, 42, .03);

    transition:
        transform .25s ease,
        box-shadow .25s ease,
        border-color .25s ease;
}

.quick-card:hover {
    transform:
        translateY(-3px);

    border-color:
        #bfdbfe;

    box-shadow:
        0 12px 26px
        rgba(15, 23, 42, .06);
}

.quick-icon {
    width:
        36px;

    height:
        36px;

    flex-shrink:
        0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    color:
        #2563eb;

    background:
        #eff6ff;
}

.quick-icon svg {
    width:
        16px;

    height:
        16px;
}

.quick-text strong {
    display:
        block;

    font-size:
        9px;

    font-weight:
        800;
}

.quick-text span {
    display:
        block;

    margin-top:
        3px;

    color:
        #94a3b8;

    font-size:
        7px;
}


/* =========================================================
   CONTENT GRID
========================================================= */

.content-grid {
    display:
        grid;

    grid-template-columns:
        minmax(0, 1.65fr)
        minmax(280px, .9fr);

    gap:
        16px;

    align-items:
        start;
}


/* =========================================================
   PANEL
========================================================= */

.panel {
    overflow:
        hidden;

    border:
        1px solid #e4eaf1;

    border-radius:
        17px;

    background:
        white;

    box-shadow:
        0 8px 25px
        rgba(15, 23, 42, .035);
}

.panel-header {
    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    padding:
        17px 19px;

    border-bottom:
        1px solid #edf1f5;
}

.panel-heading strong {
    display:
        block;

    font-size:
        12px;

    font-weight:
        800;
}

.panel-heading span {
    display:
        block;

    margin-top:
        3px;

    color:
        #94a3b8;

    font-size:
        7px;
}

.panel-link {
    display:
        inline-flex;

    align-items:
        center;

    gap:
        4px;

    color:
        #2563eb;

    font-size:
        8px;

    font-weight:
        700;
}

.panel-link:hover {
    text-decoration:
        underline;
}

.panel-link svg {
    width:
        12px;

    height:
        12px;
}


/* =========================================================
   REPORT LIST
========================================================= */

.report-list {
    display:
        flex;

    flex-direction:
        column;
}

.report-item {
    display:
        grid;

    grid-template-columns:
        38px 1fr auto;

    gap:
        10px;

    padding:
        13px 19px;

    border-bottom:
        1px solid #f0f3f6;

    transition:
        background .2s ease;
}

.report-item:last-child {
    border-bottom:
        none;
}

.report-item:hover {
    background:
        #fbfdff;
}

.report-icon {
    width:
        36px;

    height:
        36px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        10px;

    color:
        #2563eb;

    background:
        #eff6ff;
}

.report-icon svg {
    width:
        15px;

    height:
        15px;
}

.report-info {
    min-width:
        0;
}

.report-number {
    color:
        #2563eb;

    font-size:
        8px;

    font-weight:
        800;
}

.report-name {
    overflow:
        hidden;

    margin-top:
        3px;

    color:
        #172033;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;

    font-size:
        8px;

    font-weight:
        700;
}

.report-meta {
    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    margin-top:
        4px;

    color:
        #94a3b8;

    font-size:
        6.5px;
}

.report-meta span {
    display:
        inline-flex;

    align-items:
        center;

    gap:
        3px;

    min-width:
        0;
}

.report-meta span:first-child {
    overflow:
        hidden;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;
}

.report-meta svg {
    width:
        10px;

    height:
        10px;

    flex-shrink:
        0;
}

.report-status {
    align-self:
        center;

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        5px 8px;

    border-radius:
        999px;

    font-size:
        6.5px;

    font-weight:
        800;

    white-space:
        nowrap;
}

.status-dot {
    width:
        5px;

    height:
        5px;

    border-radius:
        50%;

    background:
        currentColor;
}

.status-menunggu {
    color:
        #ea580c;

    background:
        #fff7ed;
}

.status-diproses {
    color:
        #ca8a04;

    background:
        #fefce8;
}

.status-selesai {
    color:
        #16a34a;

    background:
        #ecfdf5;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-report {
    padding:
        38px 20px;

    text-align:
        center;
}

.empty-report-icon {
    width:
        48px;

    height:
        48px;

    margin:
        0 auto 11px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        14px;

    color:
        #2563eb;

    background:
        #eff6ff;
}

.empty-report-icon svg {
    width:
        22px;

    height:
        22px;
}

.empty-report strong {
    display:
        block;

    font-size:
        10px;
}

.empty-report p {
    margin-top:
        4px;

    color:
        #94a3b8;

    font-size:
        7px;
}


/* =========================================================
   CATEGORY
========================================================= */

.overview-list {
    padding:
        4px 19px 16px;
}

.overview-item {
    padding:
        11px 0;

    border-bottom:
        1px solid #f0f3f6;
}

.overview-item:last-child {
    border-bottom:
        none;
}

.overview-top {
    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}

.overview-name {
    color:
        #475569;

    font-size:
        7.5px;

    font-weight:
        600;
}

.overview-number {
    color:
        #172033;

    font-size:
        8px;

    font-weight:
        800;
}

.progress {
    height:
        4px;

    margin-top:
        6px;

    overflow:
        hidden;

    border-radius:
        999px;

    background:
        #edf2f7;
}

.progress-bar {
    height:
        100%;

    border-radius:
        inherit;

    background:
        linear-gradient(
            90deg,
            #1769aa,
            #2563eb
        );
}


/* =========================================================
   SYSTEM SUMMARY
========================================================= */

.system-card {
    margin-top:
        16px;

    padding:
        15px;

    border:
        1px solid #dbeafe;

    border-radius:
        15px;

    background:
        #eff6ff;
}

.system-title {
    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    color:
        #1e40af;

    font-size:
        8px;

    font-weight:
        800;
}

.system-title svg {
    width:
        14px;

    height:
        14px;
}

.system-grid {
    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        7px;

    margin-top:
        10px;
}

.system-item {
    padding:
        9px;

    border-radius:
        9px;

    background:
        rgba(255,255,255,.78);
}

.system-item span {
    display:
        block;

    color:
        #60a5fa;

    font-size:
        6.5px;
}

.system-item strong {
    display:
        block;

    margin-top:
        2px;

    color:
        #1e3a8a;

    font-size:
        13px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    border-top:
        1px solid #e8edf3;

    background:
        white;
}

.footer-container {
    width:
        min(1120px, 92%);

    min-height:
        65px;

    margin:
        auto;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}

.footer p {
    color:
        #94a3b8;

    font-size:
        8px;
}

.footer-links {
    display:
        flex;

    gap:
        14px;
}

.footer-links a {
    color:
        #94a3b8;

    font-size:
        8px;

    transition:
        .2s ease;
}

.footer-links a:hover {
    color:
        #2563eb;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 950px) {

    .content-grid {
        grid-template-columns:
            1fr;
    }

}


@media (max-width: 780px) {

    .stats-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .quick-grid {
        grid-template-columns:
            1fr;
    }

    .hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .hero-date {
        align-self:
            flex-start;
    }

}


@media (max-width: 650px) {

    .main {
        width:
            94%;

        padding:
            25px 0 45px;
    }

    .nav-links {
        display:
            none;
    }

    .brand-text span {
        display:
            none;
    }

    .brand-text strong {
        font-size:
            12px;
    }

    .nav-name {
        display:
            none;
    }

    .hero h1 {
        font-size:
            29px;
    }

    .report-item {
        grid-template-columns:
            36px 1fr;
    }

    .report-status {
        grid-column:
            2;

        justify-self:
            start;
    }

    .footer-container {
        min-height:
            85px;

        flex-direction:
            column;

        justify-content:
            center;

        gap:
            8px;
    }

}


@media (max-width: 430px) {

    .stats-grid {
        gap:
            8px;
    }

    .stat-card {
        padding:
            13px;
    }

    .stat-number {
        font-size:
            21px;
    }

    .hero h1 {
        font-size:
            27px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<?php

$basePath = '../';
$activePage = 'dashboard';

include '../includes/navbar.php';

?>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="hero">


        <div class="hero-content">


            <div class="hero-badge">

                <i data-lucide="layout-dashboard"></i>

                Dashboard Administrator

            </div>


            <h1>

                Halo,
                <span>
                    <?= htmlspecialchars(
                        $admin["nama"]
                    ); ?>
                </span>

            </h1>


            <p class="hero-description">

                Pantau seluruh pengaduan fasilitas kampus,
                kelola pengguna, dan pastikan setiap laporan
                mendapatkan tindak lanjut yang sesuai.

            </p>


        </div>


        <div class="hero-date">

            <i data-lucide="calendar-days"></i>

            <?= date("d F Y"); ?>

        </div>


    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">


        <div class="stat-card stat-blue">


            <div class="stat-icon">

                <i data-lucide="file-text"></i>

            </div>


            <div class="stat-label">
                Total Pengaduan
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $total_pengaduan; ?>"
            >
                <?= $total_pengaduan; ?>
            </div>


            <div class="stat-description">
                Seluruh laporan masuk
            </div>


        </div>


        <div class="stat-card stat-orange">


            <div class="stat-icon">

                <i data-lucide="clock-3"></i>

            </div>


            <div class="stat-label">
                Menunggu
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $menunggu; ?>"
            >
                <?= $menunggu; ?>
            </div>


            <div class="stat-description">
                Perlu diverifikasi
            </div>


        </div>


        <div class="stat-card stat-yellow">


            <div class="stat-icon">

                <i data-lucide="loader-circle"></i>

            </div>


            <div class="stat-label">
                Diproses
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $diproses; ?>"
            >
                <?= $diproses; ?>
            </div>


            <div class="stat-description">
                Sedang ditangani
            </div>


        </div>


        <div class="stat-card stat-green">


            <div class="stat-icon">

                <i data-lucide="circle-check"></i>

            </div>


            <div class="stat-label">
                Selesai
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $selesai; ?>"
            >
                <?= $selesai; ?>
            </div>


            <div class="stat-description">
                Laporan selesai
            </div>


        </div>


    </section>


    <!-- =====================================================
         QUICK ACTION
    ====================================================== -->

    <section class="quick-grid">


        <a
            href="pengaduan.php"
            class="quick-card"
        >

            <div class="quick-icon">

                <i data-lucide="file-search"></i>

            </div>


            <div class="quick-text">

                <strong>
                    Kelola Pengaduan
                </strong>

                <span>
                    Verifikasi dan proses laporan
                </span>

            </div>

        </a>


        <a
            href="pengguna.php"
            class="quick-card"
        >

            <div class="quick-icon">

                <i data-lucide="users"></i>

            </div>


            <div class="quick-text">

                <strong>
                    Data Pengguna
                </strong>

                <span>
                    Lihat pengguna sistem
                </span>

            </div>

        </a>


        <a
            href="petugas.php"
            class="quick-card"
        >

            <div class="quick-icon">

                <i data-lucide="user-cog"></i>

            </div>


            <div class="quick-text">

                <strong>
                    Data Petugas
                </strong>

                <span>
                    Pantau petugas dan tugas
                </span>

            </div>

        </a>


    </section>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <section class="content-grid">


        <!-- =================================================
             PENGADUAN TERBARU
        ================================================== -->

        <div class="panel">


            <div class="panel-header">


                <div class="panel-heading">

                    <strong>
                        Pengaduan Terbaru
                    </strong>

                    <span>
                        5 laporan terakhir
                    </span>

                </div>


                <a
                    href="pengaduan.php"
                    class="panel-link"
                >

                    Lihat Semua

                    <i data-lucide="arrow-right"></i>

                </a>


            </div>


            <div class="report-list">


                <?php if (
                    $query_terbaru &&
                    mysqli_num_rows(
                        $query_terbaru
                    ) > 0
                ): ?>


                    <?php while (
                        $row =
                        mysqli_fetch_assoc(
                            $query_terbaru
                        )
                    ): ?>


                        <?php

                        $status_class = [
                            'Menunggu' => 'status-menunggu',
                            'Diproses' => 'status-diproses',
                            'Selesai' => 'status-selesai'
                        ][$row['status']] ?? 'status-menunggu';

                        ?>


                        <div class="report-item">


                            <div class="report-icon">


                                <?php if (
                                    $row["status"]
                                    === "Selesai"
                                ): ?>

                                    <i
                                        data-lucide="circle-check"
                                    ></i>

                                <?php elseif (
                                    $row["status"]
                                    === "Diproses"
                                ): ?>

                                    <i
                                        data-lucide="loader-circle"
                                    ></i>

                                <?php else: ?>

                                    <i
                                        data-lucide="clock-3"
                                    ></i>

                                <?php endif; ?>


                            </div>


                            <div class="report-info">


                                <div class="report-number">

                                    <?= htmlspecialchars(
                                        $row["no_laporan"]
                                    ); ?>

                                </div>


                                <div class="report-name">

                                    <?= htmlspecialchars(
                                        $row["nama_kategori"]
                                    ); ?>

                                    —

                                    <?= htmlspecialchars(
                                        $row["nama_pengguna"]
                                    ); ?>

                                </div>


                                <div class="report-meta">


                                    <span>

                                        <i
                                            data-lucide="map-pin"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $row["lokasi"]
                                        ); ?>

                                    </span>


                                    <span>

                                        <i
                                            data-lucide="calendar-days"
                                        ></i>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $row[
                                                    "tanggal_lapor"
                                                ]
                                            )
                                        ); ?>

                                    </span>


                                </div>


                            </div>


                            <div
                                class="
                                    report-status
                                    <?= $status_class; ?>
                                "
                            >

                                <span
                                    class="status-dot"
                                ></span>

                                <?= htmlspecialchars(
                                    $row["status"]
                                ); ?>

                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="empty-report">


                        <div class="empty-report-icon">

                            <i data-lucide="inbox"></i>

                        </div>


                        <strong>
                            Belum ada pengaduan
                        </strong>


                        <p>
                            Belum terdapat laporan
                            fasilitas yang masuk.
                        </p>


                    </div>


                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             RIGHT COLUMN
        ================================================== -->

        <div>


            <!-- =============================================
                 KATEGORI
            ============================================== -->

            <div class="panel">


                <div class="panel-header">


                    <div class="panel-heading">

                        <strong>
                            Kategori Pengaduan
                        </strong>

                        <span>
                            Kategori terbanyak
                        </span>

                    </div>


                    <i
                        data-lucide="chart-no-axes-column"
                        style="
                            width:17px;
                            height:17px;
                            color:#2563eb;
                        "
                    ></i>


                </div>


                <div class="overview-list">


                    <?php if (
                        count(
                            $kategori_data
                        ) > 0
                    ): ?>


                        <?php foreach (
                            $kategori_data
                            as $kategori
                        ): ?>


                            <?php

                            $jumlah =
                                (int)
                                $kategori["total"];

                            $percentage =
                                $max_kategori > 0
                                ? (
                                    $jumlah /
                                    $max_kategori
                                ) * 100
                                : 0;

                            ?>


                            <div class="overview-item">


                                <div class="overview-top">


                                    <span
                                        class="overview-name"
                                    >

                                        <?= htmlspecialchars(
                                            $kategori[
                                                "nama_kategori"
                                            ]
                                        ); ?>

                                    </span>


                                    <span
                                        class="overview-number"
                                    >

                                        <?= $jumlah; ?>

                                    </span>


                                </div>


                                <div class="progress">

                                    <div
                                        class="progress-bar"
                                        style="
                                            width:
                                            <?= $percentage; ?>%;
                                        "
                                    ></div>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div
                            style="
                                padding:25px 0;
                                text-align:center;
                                color:#94a3b8;
                                font-size:8px;
                            "
                        >

                            Belum ada data kategori.

                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =============================================
                 SYSTEM SUMMARY
            ============================================== -->

            <div class="system-card">


                <div class="system-title">

                    <i data-lucide="activity"></i>

                    Ringkasan Sistem

                </div>


                <div class="system-grid">


                    <div class="system-item">

                        <span>
                            Pengguna
                        </span>

                        <strong>
                            <?= $total_pengguna; ?>
                        </strong>

                    </div>


                    <div class="system-item">

                        <span>
                            Petugas
                        </span>

                        <strong>
                            <?= $total_petugas; ?>
                        </strong>

                    </div>


                    <div class="system-item">

                        <span>
                            Hari Ini
                        </span>

                        <strong>
                            <?= $hari_ini; ?>
                        </strong>

                    </div>


                    <div class="system-item">

                        <span>
                            Selesai
                        </span>

                        <strong>
                            <?= $selesai; ?>
                        </strong>

                    </div>


                </div>


            </div>


        </div>


    </section>


</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">


    <div class="footer-container">


        <p>

            Sistem Pengaduan Kampus
            © <?= date("Y"); ?>

        </p>


        <div class="footer-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="pengaduan.php">
                Pengaduan
            </a>

            <a href="../logout.php">
                Keluar
            </a>

        </div>


    </div>

</footer>


<!-- =========================================================
     GSAP
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"
></script>


<!-- =========================================================
     SCROLLTRIGGER
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"
></script>


<!-- =========================================================
     LENIS
========================================================= -->

<script nonce="<?= e(cspNonce()) ?>"
    src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"
></script>


<script nonce="<?= e(cspNonce()) ?>">

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /* =====================================================
           LUCIDE
        ===================================================== */

        if (
            typeof lucide !== "undefined"
        ) {

            lucide.createIcons();

        }


        /* =====================================================
           CEK GSAP
        ===================================================== */

        const hasGSAP =
            typeof gsap !== "undefined";

        const hasScrollTrigger =
            typeof ScrollTrigger !== "undefined";


        if (
            hasGSAP &&
            hasScrollTrigger
        ) {

            gsap.registerPlugin(
                ScrollTrigger
            );

        }


        /* =====================================================
           LENIS
        ===================================================== */

        let lenis = null;


        if (
            typeof Lenis !== "undefined"
        ) {

            lenis =
                new Lenis({

                    duration:
                        1.05,

                    smoothWheel:
                        true,

                    syncTouch:
                        false,

                    wheelMultiplier:
                        .9,

                    touchMultiplier:
                        1.2

                });


            if (
                hasGSAP &&
                hasScrollTrigger
            ) {

                lenis.on(
                    "scroll",
                    ScrollTrigger.update
                );


                gsap.ticker.add(
                    function (time) {

                        lenis.raf(
                            time * 1000
                        );

                    }
                );


                gsap.ticker.lagSmoothing(
                    0
                );

            }

        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        const navbar =
            document.querySelector(
                "#navbar"
            );


        function updateNavbar() {

            const scrollPosition =
                lenis
                    ? lenis.scroll
                    : window.scrollY;


            if (!navbar) {
                return;
            }


            if (
                scrollPosition > 25
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


        window.addEventListener(
            "scroll",
            updateNavbar
        );


        if (lenis) {

            lenis.on(
                "scroll",
                updateNavbar
            );

        }


        updateNavbar();


        /* =====================================================
           HERO ANIMATION
           
           HANYA HERO yang menggunakan opacity.
           Card lain tetap terlihat walaupun GSAP gagal.
        ===================================================== */

        if (hasGSAP) {

            gsap.fromTo(
                ".hero",
                {
                    y: 18,
                    opacity: 0
                },
                {
                    y: 0,
                    opacity: 1,
                    duration: .55,
                    ease: "power3.out"
                }
            );


            /* =================================================
               STAT CARD
               
               Tidak menggunakan opacity agar tidak pernah
               menghasilkan area kosong.
            ================================================= */

            gsap.from(
                ".stat-card",
                {
                    y: 16,
                    duration: .45,
                    stagger: .07,
                    delay: .12,
                    ease: "power3.out"
                }
            );


            /* =================================================
               QUICK ACTION
            ================================================= */

            gsap.from(
                ".quick-card",
                {
                    y: 14,
                    duration: .4,
                    stagger: .07,
                    delay: .25,
                    ease: "power3.out"
                }
            );


            /* =================================================
               PANEL
            ================================================= */

            gsap.from(
                ".content-grid > *",
                {
                    y: 16,
                    duration: .5,
                    stagger: .08,
                    delay: .35,
                    ease: "power3.out"
                }
            );

        }


        /* =====================================================
           COUNTER
        ===================================================== */

        if (hasGSAP) {

            const counters =
                document.querySelectorAll(
                    ".counter"
                );


            counters.forEach(
                function (counter) {


                    const target =
                        parseInt(
                            counter.dataset.value
                        ) || 0;


                    const number = {
                        value:
                            0
                    };


                    gsap.to(
                        number,
                        {

                            value:
                                target,

                            duration:
                                .9,

                            delay:
                                .15,

                            ease:
                                "power2.out",

                            onUpdate:
                                function () {

                                    counter.textContent =
                                        Math.round(
                                            number.value
                                        );

                                }

                        }
                    );


                }
            );

        }


        /* =====================================================
           SCROLLTRIGGER
           
           Digunakan untuk elemen bagian bawah.
        ===================================================== */

        if (
            hasGSAP &&
            hasScrollTrigger
        ) {


            const reportItems =
                document.querySelectorAll(
                    ".report-item"
                );


            reportItems.forEach(
                function (item, index) {


                    gsap.from(
                        item,
                        {

                            scrollTrigger: {

                                trigger:
                                    item,

                                start:
                                    "top 92%",

                                once:
                                    true

                            },

                            x:
                                -15,

                            duration:
                                .4,

                            delay:
                                index * .03,

                            ease:
                                "power3.out"

                        }
                    );


                }
            );


            const overviewItems =
                document.querySelectorAll(
                    ".overview-item"
                );


            overviewItems.forEach(
                function (item, index) {


                    gsap.from(
                        item,
                        {

                            scrollTrigger: {

                                trigger:
                                    item,

                                start:
                                    "top 92%",

                                once:
                                    true

                            },

                            x:
                                15,

                            duration:
                                .4,

                            delay:
                                index * .04,

                            ease:
                                "power3.out"

                        }
                    );


                }
            );


            const systemCard =
                document.querySelector(
                    ".system-card"
                );


            if (systemCard) {

                gsap.from(
                    systemCard,
                    {

                        scrollTrigger: {

                            trigger:
                                systemCard,

                            start:
                                "top 92%",

                            once:
                                true

                        },

                        y:
                            15,

                        duration:
                            .45,

                        ease:
                            "power3.out"

                    }
                );

            }


            setTimeout(
                function () {

                    ScrollTrigger.refresh();

                },
                500
            );

        }


        /* =====================================================
           QUICK CARD HOVER
        ===================================================== */

        if (hasGSAP) {


            document
                .querySelectorAll(
                    ".quick-card"
                )
                .forEach(
                    function (card) {


                        const icon =
                            card.querySelector(
                                ".quick-icon"
                            );


                        card.addEventListener(
                            "mouseenter",
                            function () {

                                gsap.to(
                                    icon,
                                    {
                                        scale:
                                            1.08,

                                        rotate:
                                            4,

                                        duration:
                                            .2
                                    }
                                );

                            }
                        );


                        card.addEventListener(
                            "mouseleave",
                            function () {

                                gsap.to(
                                    icon,
                                    {
                                        scale:
                                            1,

                                        rotate:
                                            0,

                                        duration:
                                            .2
                                    }
                                );

                            }
                        );


                    }
                );


        }


        /* =====================================================
           STAT ICON HOVER
        ===================================================== */

        if (hasGSAP) {


            document
                .querySelectorAll(
                    ".stat-card"
                )
                .forEach(
                    function (card) {


                        const icon =
                            card.querySelector(
                                ".stat-icon"
                            );


                        card.addEventListener(
                            "mouseenter",
                            function () {

                                gsap.to(
                                    icon,
                                    {
                                        scale:
                                            1.08,

                                        rotate:
                                            4,

                                        duration:
                                            .2
                                    }
                                );

                            }
                        );


                        card.addEventListener(
                            "mouseleave",
                            function () {

                                gsap.to(
                                    icon,
                                    {
                                        scale:
                                            1,

                                        rotate:
                                            0,

                                        duration:
                                            .2
                                    }
                                );

                            }
                        );


                    }
                );


        }


    }
);

</script>

<script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lucide@latest"></script>
<script nonce="<?= e(cspNonce()) ?>" src="../assets/js/script.js"></script>

</body>

</html>

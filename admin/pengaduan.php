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
    "SELECT COUNT(*) AS total
     FROM pengaduan"
);

$total_menunggu = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Menunggu'"
);

$total_diproses = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Diproses'"
);

$total_selesai = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pengaduan
     WHERE status = 'Selesai'"
);


/* =========================================================
   FILTER
========================================================= */

$search = substr(trim(getString('search')), 0, 200);

$status_filter = getString('status');

$kategori_filter = getString('kategori');


/* =========================================================
   VALIDASI STATUS FILTER
========================================================= */

$allowed_status = [
    "Menunggu",
    "Diproses",
    "Selesai"
];

if (
    $status_filter !== "" &&
    !in_array(
        $status_filter,
        $allowed_status,
        true
    )
) {

    $status_filter = "";

}


/* =========================================================
   KATEGORI
========================================================= */

$kategori_list = [];

$query_kategori = mysqli_query(
    $conn,
    "SELECT
        id_kategori,
        nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC"
);

if ($query_kategori) {

    while (
        $row =
        mysqli_fetch_assoc(
            $query_kategori
        )
    ) {

        $kategori_list[] = $row;

    }

}


/* =========================================================
   BUILD QUERY
========================================================= */

$where = [];
$queryTypes = '';
$queryParams = [];


/* Search */

if ($search !== "") {

    $where[] = "(
        p.no_laporan LIKE ?
        OR p.lokasi LIKE ?
        OR p.deskripsi LIKE ?
        OR u.nama LIKE ?
        OR k.nama_kategori LIKE ?
    )";
    $queryTypes .= 'sssss';
    $queryParams = array_fill(0, 5, '%' . $search . '%');

}


/* Status */

if ($status_filter !== "") {

    $where[] = 'p.status = ?';
    $queryTypes .= 's';
    $queryParams[] = $status_filter;

}


/* Kategori */

if ($kategori_filter !== "") {

    $kategori_id = validId($kategori_filter);
    $where[] = 'p.id_kategori = ?';
    $queryTypes .= 'i';
    $queryParams[] = $kategori_id;

}


/* WHERE */

$where_sql = "";

if (count($where) > 0) {

    $where_sql =
        "WHERE " .
        implode(
            " AND ",
            $where
        );

}


/* =========================================================
   DATA PENGADUAN
========================================================= */

$query_pengaduan = dbQuery(
    $conn,
    "SELECT
        p.id_pengaduan,
        p.no_laporan,
        p.lokasi,
        p.deskripsi,
        p.foto,
        p.status,
        p.tanggal_lapor,
        p.id_petugas,

        k.nama_kategori,

        u.nama AS nama_pengguna,
        u.email AS email_pengguna

     FROM pengaduan p

     INNER JOIN kategori k
        ON p.id_kategori =
           k.id_kategori

     INNER JOIN users u
        ON p.id_user =
           u.id_user

     $where_sql

     ORDER BY
        p.tanggal_lapor DESC",
    $queryTypes,
    $queryParams
);


/* =========================================================
   TOTAL HASIL FILTER
========================================================= */

$total_hasil = 0;

if ($query_pengaduan) {

    $total_hasil =
        mysqli_num_rows(
            $query_pengaduan
        );

}


/* =========================================================
   STATUS CLASS
========================================================= */

function statusClass($status)
{

    if ($status === "Selesai") {
        return "status-selesai";
    }

    if ($status === "Diproses") {
        return "status-diproses";
    }

    return "status-menunggu";
}


/* =========================================================
   STATUS ICON
========================================================= */

function statusIcon($status)
{

    if ($status === "Selesai") {
        return "circle-check";
    }

    if ($status === "Diproses") {
        return "loader-circle";
    }

    return "clock-3";
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
    Kelola Pengaduan - Admin
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
     LENIS CSS
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
    box-sizing:
        border-box;

    margin:
        0;

    padding:
        0;
}

html {
    scroll-behavior:
        auto;
}

body {

    min-height:
        100vh;

    font-family:
        "Inter",
        Arial,
        sans-serif;

    color:
        #0f172a;

    background:
        #f8fafc;

    overflow-x:
        hidden;
}

a {
    color:
        inherit;

    text-decoration:
        none;
}

button,
input,
select {
    font-family:
        inherit;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    position:
        sticky;

    top:
        0;

    z-index:
        1000;

    background:
        rgba(
            255,
            255,
            255,
            .95
        );

    border-bottom:
        1px solid
        #e8edf3;

    backdrop-filter:
        blur(16px);

    transition:
        box-shadow .3s ease;
}

.navbar.scrolled {

    box-shadow:
        0 8px 30px
        rgba(
            15,
            23,
            42,
            .07
        );
}

.nav-container {

    width:
        min(
            1120px,
            92%
        );

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
        rgba(
            37,
            99,
            235,
            .18
        );
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
        1px solid
        #e5eaf0;

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
        min(
            1120px,
            92%
        );

    margin:
        auto;

    padding:
        30px 0 55px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        22px;
}

.page-header-left {

    min-width:
        0;
}

.page-badge {

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
        1px solid
        #dbeafe;

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

.page-badge svg {

    width:
        13px;

    height:
        13px;
}

.page-title {

    font-size:
        clamp(
            26px,
            4vw,
            38px
        );

    line-height:
        1.1;

    letter-spacing:
        -1.4px;

    font-weight:
        800;
}

.page-title span {

    color:
        #2563eb;
}

.page-description {

    max-width:
        650px;

    margin-top:
        7px;

    color:
        #64748b;

    font-size:
        10px;

    line-height:
        1.6;
}


/* =========================================================
   HEADER ACTION
========================================================= */

.header-action {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        10px 13px;

    border:
        1px solid
        #dbeafe;

    border-radius:
        10px;

    color:
        #2563eb;

    background:
        white;

    font-size:
        8px;

    font-weight:
        700;

    box-shadow:
        0 5px 18px
        rgba(
            15,
            23,
            42,
            .03
        );

    transition:
        .2s ease;
}

.header-action:hover {

    transform:
        translateY(-2px);

    border-color:
        #bfdbfe;

    box-shadow:
        0 9px 24px
        rgba(
            37,
            99,
            235,
            .09
        );
}

.header-action svg {

    width:
        14px;

    height:
        14px;
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            4,
            1fr
        );

    gap:
        12px;

    margin-bottom:
        18px;
}

.stat-card {

    position:
        relative;

    overflow:
        hidden;

    padding:
        15px;

    border:
        1px solid
        #e4eaf1;

    border-radius:
        15px;

    background:
        white;

    box-shadow:
        0 7px 22px
        rgba(
            15,
            23,
            42,
            .035
        );

    transition:
        .25s ease;
}

.stat-card:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 14px 30px
        rgba(
            15,
            23,
            42,
            .07
        );
}

.stat-top {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}

.stat-icon {

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

    margin-top:
        11px;

    color:
        #64748b;

    font-size:
        8px;

    font-weight:
        600;
}

.stat-number {

    margin-top:
        3px;

    font-size:
        24px;

    line-height:
        1;

    font-weight:
        800;
}

.stat-note {

    margin-top:
        5px;

    color:
        #94a3b8;

    font-size:
        7px;
}


/* =========================================================
   FILTER CARD
========================================================= */

.filter-card {

    padding:
        15px;

    margin-bottom:
        17px;

    border:
        1px solid
        #e4eaf1;

    border-radius:
        15px;

    background:
        white;

    box-shadow:
        0 7px 22px
        rgba(
            15,
            23,
            42,
            .03
        );
}

.filter-title {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    margin-bottom:
        11px;

    color:
        #172033;

    font-size:
        9px;

    font-weight:
        800;
}

.filter-title svg {

    width:
        14px;

    height:
        14px;

    color:
        #2563eb;
}

.filter-form {

    display:
        grid;

    grid-template-columns:
        minmax(
            250px,
            1fr
        )
        180px
        190px
        auto;

    gap:
        9px;
}

.search-box,
.select-box {

    position:
        relative;
}

.search-box svg {

    position:
        absolute;

    left:
        11px;

    top:
        50%;

    width:
        15px;

    height:
        15px;

    color:
        #94a3b8;

    transform:
        translateY(-50%);
}

.search-box input,
.select-box select {

    width:
        100%;

    height:
        38px;

    border:
        1px solid
        #dce3eb;

    border-radius:
        9px;

    outline:
        none;

    color:
        #172033;

    background:
        white;

    font-size:
        8px;

    transition:
        .2s ease;
}

.search-box input {

    padding:
        0 12px 0 34px;
}

.select-box select {

    padding:
        0 10px;
}

.search-box input:focus,
.select-box select:focus {

    border-color:
        #93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(
            37,
            99,
            235,
            .08
        );
}

.filter-button {

    height:
        38px;

    padding:
        0 15px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        6px;

    border:
        none;

    border-radius:
        9px;

    color:
        white;

    background:
        linear-gradient(
            135deg,
            #1769aa,
            #2563eb
        );

    cursor:
        pointer;

    font-size:
        8px;

    font-weight:
        700;

    transition:
        .2s ease;
}

.filter-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 18px
        rgba(
            37,
            99,
            235,
            .18
        );
}

.filter-button svg {

    width:
        14px;

    height:
        14px;
}

.reset-button {

    height:
        38px;

    padding:
        0 12px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    border:
        1px solid
        #dce3eb;

    border-radius:
        9px;

    color:
        #64748b;

    background:
        white;

    font-size:
        8px;

    font-weight:
        700;

    transition:
        .2s ease;
}

.reset-button:hover {

    color:
        #2563eb;

    border-color:
        #bfdbfe;

    background:
        #eff6ff;
}


/* =========================================================
   RESULT INFO
========================================================= */

.result-bar {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    margin-bottom:
        9px;
}

.result-info {

    color:
        #64748b;

    font-size:
        8px;
}

.result-info strong {

    color:
        #172033;

    font-weight:
        800;
}

.active-filter {

    display:
        flex;

    align-items:
        center;

    gap:
        5px;

    color:
        #2563eb;

    font-size:
        7px;
    }

.active-filter svg {

    width:
        12px;

    height:
        12px;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card {

    overflow:
        hidden;

    border:
        1px solid
        #e4eaf1;

    border-radius:
        17px;

    background:
        white;

    box-shadow:
        0 8px 25px
        rgba(
            15,
            23,
            42,
            .035
        );
}

.table-wrapper {

    width:
        100%;

    overflow-x:
        auto;
}

.data-table {

    width:
        100%;

    min-width:
        820px;

    border-collapse:
        collapse;
}

.data-table th {

    padding:
        12px 15px;

    border-bottom:
        1px solid
        #edf1f5;

    color:
        #64748b;

    background:
        #fbfcfe;

    text-align:
        left;

    font-size:
        7px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .3px;
}

.data-table td {

    padding:
        13px 15px;

    border-bottom:
        1px solid
        #f0f3f6;

    vertical-align:
        middle;

    font-size:
        8px;
}

.data-table tbody tr {

    transition:
        background .2s ease;
}

.data-table tbody tr:hover {

    background:
        #fbfdff;
}

.data-table tbody tr:last-child td {

    border-bottom:
        none;
}


/* =========================================================
   REPORT ID
========================================================= */

.report-id {

    color:
        #2563eb;

    font-size:
        7.5px;

    font-weight:
        800;
}

.report-date {

    display:
        flex;

    align-items:
        center;

    gap:
        4px;

    margin-top:
        4px;

    color:
        #94a3b8;

    font-size:
        6.5px;
}

.report-date svg {

    width:
        9px;

    height:
        9px;
}


/* =========================================================
   USER
========================================================= */

.user-cell {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;
}

.user-avatar {

    width:
        30px;

    height:
        30px;

    flex-shrink:
        0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        9px;

    color:
        #2563eb;

    background:
        #eff6ff;

    font-size:
        8px;

    font-weight:
        800;
}

.user-info strong {

    display:
        block;

    max-width:
        145px;

    overflow:
        hidden;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;

    color:
        #172033;

    font-size:
        8px;

    font-weight:
        700;
}

.user-info span {

    display:
        block;

    max-width:
        145px;

    margin-top:
        2px;

    overflow:
        hidden;

    white-space:
        nowrap;

    text-overflow:
        ellipsis;

    color:
        #94a3b8;

    font-size:
        6.5px;
}


/* =========================================================
   CATEGORY
========================================================= */

.category-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        5px 7px;

    border-radius:
        7px;

    color:
        #475569;

    background:
        #f1f5f9;

    font-size:
        7px;

    font-weight:
        700;
}

.category-badge svg {

    width:
        11px;

    height:
        11px;
}


/* =========================================================
   LOCATION
========================================================= */

.location-cell {

    max-width:
        155px;

    color:
        #64748b;

    line-height:
        1.4;

    font-size:
        7px;
}

.location-cell span {

    display:
        inline-flex;

    align-items:
        flex-start;

    gap:
        4px;
}

.location-cell svg {

    width:
        11px;

    height:
        11px;

    flex-shrink:
        0;

    margin-top:
        1px;

    color:
        #94a3b8;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge {

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

    white-space:
        nowrap;

    font-size:
        6.5px;

    font-weight:
        800;
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
   ACTION
========================================================= */

.action-button {

    width:
        31px;

    height:
        31px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    border:
        1px solid
        #dbeafe;

    border-radius:
        8px;

    color:
        #2563eb;

    background:
        #eff6ff;

    transition:
        .2s ease;
}

.action-button:hover {

    transform:
        translateY(-2px);

    color:
        white;

    background:
        #2563eb;

    border-color:
        #2563eb;

    box-shadow:
        0 7px 15px
        rgba(
            37,
            99,
            235,
            .16
        );
}

.action-button svg {

    width:
        14px;

    height:
        14px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {

    padding:
        65px 20px;

    text-align:
        center;
}

.empty-icon {

    width:
        55px;

    height:
        55px;

    margin:
        0 auto 13px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        16px;

    color:
        #2563eb;

    background:
        #eff6ff;
}

.empty-icon svg {

    width:
        24px;

    height:
        24px;
}

.empty-state strong {

    display:
        block;

    color:
        #172033;

    font-size:
        11px;

    font-weight:
        800;
}

.empty-state p {

    max-width:
        350px;

    margin:
        5px auto 0;

    color:
        #94a3b8;

    font-size:
        7.5px;

    line-height:
        1.5;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    border-top:
        1px solid
        #e8edf3;

    background:
        white;
}

.footer-container {

    width:
        min(
            1120px,
            92%
        );

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
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .filter-form {

        grid-template-columns:
            1fr 1fr;
    }

    .filter-button,
    .reset-button {

        width:
            100%;
    }

}


@media (max-width: 780px) {

    .stats-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }

    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .header-action {

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

    .nav-name {

        display:
            none;
    }

    .page-title {

        font-size:
            29px;
    }

    .filter-form {

        grid-template-columns:
            1fr;
    }

    .result-bar {

        align-items:
            flex-start;

        flex-direction:
            column;
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

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<?php

$basePath = '../';

$activePage = 'pengaduan';

include '../includes/navbar.php';

?>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <section class="page-header">


        <div class="page-header-left">


            <div class="page-badge">

                <i data-lucide="file-search"></i>

                Manajemen Pengaduan

            </div>


            <h1 class="page-title">

                Kelola
                <span>
                    Pengaduan
                </span>

            </h1>


            <p class="page-description">

                Kelola, pantau, dan tindak lanjuti
                seluruh laporan kerusakan fasilitas
                kampus yang masuk ke dalam sistem.

            </p>


        </div>


        <a
            href="dashboard.php"
            class="header-action"
        >

            <i data-lucide="arrow-left"></i>

            Kembali ke Dashboard

        </a>


    </section>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats-grid">


        <div class="stat-card stat-blue">


            <div class="stat-top">

                <div class="stat-icon">

                    <i data-lucide="files"></i>

                </div>

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


            <div class="stat-note">
                Semua laporan
            </div>


        </div>


        <div class="stat-card stat-orange">


            <div class="stat-top">

                <div class="stat-icon">

                    <i data-lucide="clock-3"></i>

                </div>

            </div>


            <div class="stat-label">
                Menunggu
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $total_menunggu; ?>"
            >
                <?= $total_menunggu; ?>
            </div>


            <div class="stat-note">
                Perlu diverifikasi
            </div>


        </div>


        <div class="stat-card stat-yellow">


            <div class="stat-top">

                <div class="stat-icon">

                    <i data-lucide="loader-circle"></i>

                </div>

            </div>


            <div class="stat-label">
                Diproses
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $total_diproses; ?>"
            >
                <?= $total_diproses; ?>
            </div>


            <div class="stat-note">
                Sedang ditangani
            </div>


        </div>


        <div class="stat-card stat-green">


            <div class="stat-top">

                <div class="stat-icon">

                    <i data-lucide="circle-check"></i>

                </div>

            </div>


            <div class="stat-label">
                Selesai
            </div>


            <div
                class="stat-number counter"
                data-value="<?= $total_selesai; ?>"
            >
                <?= $total_selesai; ?>
            </div>


            <div class="stat-note">
                Telah selesai
            </div>


        </div>


    </section>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <section class="filter-card">


        <div class="filter-title">

            <i data-lucide="sliders-horizontal"></i>

            Filter Pengaduan

        </div>


        <form
            action="pengaduan.php"
            method="GET"
            class="filter-form"
        >


            <!-- SEARCH -->

            <div class="search-box">

                <i data-lucide="search"></i>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars(
                        $search
                    ); ?>"
                    placeholder="Cari nomor laporan, nama, lokasi..."
                >

            </div>


            <!-- STATUS -->

            <div class="select-box">

                <select
                    name="status"
                >

                    <option value="">
                        Semua Status
                    </option>

                    <option
                        value="Menunggu"
                        <?= $status_filter === "Menunggu"
                            ? "selected"
                            : ""; ?>
                    >
                        Menunggu
                    </option>

                    <option
                        value="Diproses"
                        <?= $status_filter === "Diproses"
                            ? "selected"
                            : ""; ?>
                    >
                        Diproses
                    </option>

                    <option
                        value="Selesai"
                        <?= $status_filter === "Selesai"
                            ? "selected"
                            : ""; ?>
                    >
                        Selesai
                    </option>

                </select>

            </div>


            <!-- KATEGORI -->

            <div class="select-box">

                <select
                    name="kategori"
                >

                    <option value="">
                        Semua Kategori
                    </option>


                    <?php foreach (
                        $kategori_list
                        as $kategori
                    ): ?>

                        <option
                            value="<?= (int)
                                $kategori[
                                    "id_kategori"
                                ]; ?>"
                            <?= (
                                $kategori_filter !== ""
                                &&
                                (int)
                                $kategori_filter ===
                                (int)
                                $kategori[
                                    "id_kategori"
                                ]
                            )
                                ? "selected"
                                : ""; ?>
                        >

                            <?= htmlspecialchars(
                                $kategori[
                                    "nama_kategori"
                                ]
                            ); ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <!-- SUBMIT -->

            <button
                type="submit"
                class="filter-button"
            >

                <i data-lucide="search"></i>

                Terapkan

            </button>


        </form>


        <?php if (
            $search !== "" ||
            $status_filter !== "" ||
            $kategori_filter !== ""
        ): ?>


            <div
                style="
                    display:flex;
                    justify-content:flex-end;
                    margin-top:9px;
                "
            >

                <a
                    href="pengaduan.php"
                    class="reset-button"
                >

                    Reset Filter

                </a>

            </div>


        <?php endif; ?>


    </section>


    <!-- =====================================================
         RESULT BAR
    ====================================================== -->

    <div class="result-bar">


        <div class="result-info">

            Menampilkan

            <strong>
                <?= $total_hasil; ?>
            </strong>

            pengaduan

        </div>


        <?php if (
            $search !== "" ||
            $status_filter !== "" ||
            $kategori_filter !== ""
        ): ?>

            <div class="active-filter">

                <i data-lucide="filter"></i>

                Filter sedang aktif

            </div>

        <?php endif; ?>


    </div>


    <!-- =====================================================
         TABLE
    ====================================================== -->

    <section class="table-card">


        <?php if (
            $query_pengaduan &&
            mysqli_num_rows(
                $query_pengaduan
            ) > 0
        ): ?>


            <div class="table-wrapper">


                <table class="data-table">


                    <thead>

                        <tr>

                            <th>
                                Laporan
                            </th>

                            <th>
                                Pengguna
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Lokasi
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php while (
                            $row =
                            mysqli_fetch_assoc(
                                $query_pengaduan
                            )
                        ): ?>


                            <?php

                            $initial =
                                strtoupper(
                                    substr(
                                        $row[
                                            "nama_pengguna"
                                        ],
                                        0,
                                        1
                                    )
                                );

                            ?>


                            <tr class="table-row">


                                <!-- LAPORAN -->

                                <td>


                                    <div class="report-id">

                                        <?= htmlspecialchars(
                                            $row[
                                                "no_laporan"
                                            ]
                                        ); ?>

                                    </div>


                                    <div class="report-date">

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

                                    </div>


                                </td>


                                <!-- USER -->

                                <td>


                                    <div class="user-cell">


                                        <div class="user-avatar">

                                            <?= htmlspecialchars(
                                                $initial
                                            ); ?>

                                        </div>


                                        <div class="user-info">


                                            <strong>

                                                <?= htmlspecialchars(
                                                    $row[
                                                        "nama_pengguna"
                                                    ]
                                                ); ?>

                                            </strong>


                                            <span>

                                                <?= htmlspecialchars(
                                                    $row[
                                                        "email_pengguna"
                                                    ]
                                                ); ?>

                                            </span>


                                        </div>


                                    </div>


                                </td>


                                <!-- CATEGORY -->

                                <td>


                                    <span
                                        class="category-badge"
                                    >

                                        <i
                                            data-lucide="tag"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $row[
                                                "nama_kategori"
                                            ]
                                        ); ?>

                                    </span>


                                </td>


                                <!-- LOCATION -->

                                <td>


                                    <div
                                        class="location-cell"
                                    >

                                        <span>

                                            <i
                                                data-lucide="map-pin"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $row[
                                                    "lokasi"
                                                ]
                                            ); ?>

                                        </span>

                                    </div>


                                </td>


                                <!-- STATUS -->

                                <td>


                                    <span
                                        class="
                                            status-badge
                                            <?= statusClass(
                                                $row[
                                                    "status"
                                                ]
                                            ); ?>
                                        "
                                    >

                                        <span
                                            class="status-dot"
                                        ></span>

                                        <?= htmlspecialchars(
                                            $row[
                                                "status"
                                            ]
                                        ); ?>

                                    </span>


                                </td>


                                <!-- ACTION -->

                                <td>


                                    <a
                                        href="
                                            detail-pengaduan.php?id=<?= (int)
                                                $row[
                                                    "id_pengaduan"
                                                ]; ?>
                                        "
                                        class="action-button"
                                        title="Lihat Detail"
                                    >

                                        <i
                                            data-lucide="arrow-up-right"
                                        ></i>

                                    </a>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <!-- EMPTY STATE -->

            <div class="empty-state">


                <div class="empty-icon">

                    <i data-lucide="search-x"></i>

                </div>


                <strong>
                    Pengaduan tidak ditemukan
                </strong>


                <p>

                    Tidak ada laporan yang sesuai
                    dengan pencarian atau filter
                    yang digunakan.

                </p>


                <a
                    href="pengaduan.php"
                    class="header-action"
                    style="
                        margin-top:14px;
                    "
                >

                    <i data-lucide="rotate-ccw"></i>

                    Tampilkan Semua

                </a>


            </div>


        <?php endif; ?>


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
            typeof lucide !==
            "undefined"
        ) {

            lucide.createIcons();

        }


        /* =====================================================
           GSAP
        ===================================================== */

        const hasGSAP =
            typeof gsap !==
            "undefined";

        const hasScrollTrigger =
            typeof ScrollTrigger !==
            "undefined";


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
            typeof Lenis !==
            "undefined"
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

            const position =
                lenis
                    ? lenis.scroll
                    : window.scrollY;


            if (!navbar) {
                return;
            }


            if (
                position > 25
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
           PAGE ENTRANCE
        ===================================================== */

        if (hasGSAP) {


            gsap.fromTo(
                ".page-header",
                {
                    y:
                        18,

                    opacity:
                        0
                },
                {
                    y:
                        0,

                    opacity:
                        1,

                    duration:
                        .55,

                    ease:
                        "power3.out"
                }
            );


            /* =================================================
               STAT CARDS
            ================================================= */

            gsap.from(
                ".stat-card",
                {
                    y:
                        16,

                    duration:
                        .45,

                    stagger:
                        .07,

                    delay:
                        .12,

                    ease:
                        "power3.out"
                }
            );


            /* =================================================
               FILTER
            ================================================= */

            gsap.from(
                ".filter-card",
                {
                    y:
                        14,

                    duration:
                        .45,

                    delay:
                        .3,

                    ease:
                        "power3.out"
                }
            );


            /* =================================================
               TABLE
            ================================================= */

            gsap.from(
                ".table-card",
                {
                    y:
                        16,

                    duration:
                        .5,

                    delay:
                        .38,

                    ease:
                        "power3.out"
                }
            );

        }


        /* =====================================================
           COUNTER
        ===================================================== */

        if (hasGSAP) {


            document
                .querySelectorAll(
                    ".counter"
                )
                .forEach(
                    function (
                        counter
                    ) {


                        const target =
                            parseInt(
                                counter.dataset
                                    .value
                            ) || 0;


                        const object =
                            {
                                value:
                                    0
                            };


                        gsap.to(
                            object,
                            {

                                value:
                                    target,

                                duration:
                                    .8,

                                delay:
                                    .15,

                                ease:
                                    "power2.out",

                                onUpdate:
                                    function () {

                                        counter.textContent =
                                            Math.round(
                                                object.value
                                            );

                                    }

                            }
                        );


                    }
                );

        }


        /* =====================================================
           TABLE ROW ANIMATION
        ===================================================== */

        if (
            hasGSAP &&
            hasScrollTrigger
        ) {


            const rows =
                document.querySelectorAll(
                    ".table-row"
                );


            rows.forEach(
                function (
                    row,
                    index
                ) {


                    gsap.from(
                        row,
                        {

                            scrollTrigger: {

                                trigger:
                                    row,

                                start:
                                    "top 94%",

                                once:
                                    true

                            },

                            x:
                                -12,

                            duration:
                                .35,

                            delay:
                                index *
                                .035,

                            ease:
                                "power3.out"

                        }
                    );


                }
            );


            setTimeout(
                function () {

                    ScrollTrigger.refresh();

                },
                500
            );

        }


        /* =====================================================
           STAT CARD HOVER
        ===================================================== */

        if (hasGSAP) {


            document
                .querySelectorAll(
                    ".stat-card"
                )
                .forEach(
                    function (
                        card
                    ) {


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


        /* =====================================================
           FILTER INPUT FOCUS
        ===================================================== */

        const searchInput =
            document.querySelector(
                ".search-box input"
            );


        if (searchInput) {

            searchInput.addEventListener(
                "keydown",
                function (event) {

                    if (
                        event.key ===
                        "Enter"
                    ) {

                        event.preventDefault();

                        this
                            .closest(
                                "form"
                            )
                            .submit();

                    }

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

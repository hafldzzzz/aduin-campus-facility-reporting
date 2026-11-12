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
   DATA PENGGUNA
========================= */

$query = "
    SELECT
        u.id_user,
        u.nama,
        u.email,
        u.no_wa,
        u.role,
        u.fakultas,
        u.created_at,
        COUNT(p.id_pengaduan) AS total_pengaduan
    FROM users u
    LEFT JOIN pengaduan p
        ON u.id_user = p.id_user
    GROUP BY
        u.id_user,
        u.nama,
        u.email,
        u.no_wa,
        u.role,
        u.fakultas,
        u.created_at
    ORDER BY u.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    throw new RuntimeException('Data pengguna tidak dapat dimuat.');
}


/* =========================
   STATISTIK
========================= */

$qTotal = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users"
);

$total = mysqli_fetch_assoc($qTotal)['total'] ?? 0;


$qPengguna = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role='pengguna'"
);

$totalPengguna =
    mysqli_fetch_assoc($qPengguna)['total'] ?? 0;


$qAdmin = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role='admin'"
);

$totalAdmin =
    mysqli_fetch_assoc($qAdmin)['total'] ?? 0;


$qPetugas = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role='petugas'"
);

$totalPetugas =
    mysqli_fetch_assoc($qPetugas)['total'] ?? 0;

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
        Pengguna | ADUIN
    </title>

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/logo-aduin.png"
    >

    <!-- CSS UTAMA -->
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

$activePage = 'pengguna';

include '../includes/navbar.php';

?>


<main class="admin-users-page">


    <!-- =========================
         HEADER
    ========================== -->

    <section class="users-header">

        <div class="users-container">

            <div class="users-heading">

                <div class="heading-icon">
                    <i data-lucide="users"></i>
                </div>

                <div>

                    <span class="page-eyebrow">
                        ADMINISTRATOR
                    </span>

                    <h1>
                        Pengguna
                    </h1>

                    <p>
                        Kelola data pengguna yang terdaftar pada sistem ADUIN.
                    </p>

                </div>

            </div>

        </div>

    </section>



    <!-- =========================
         CONTENT
    ========================== -->

    <section class="users-content">

        <div class="users-container">


            <!-- =========================
                 STATISTIK
            ========================== -->

            <div class="user-stats">


                <div class="user-stat-card">

                    <div class="user-stat-icon blue">
                        <i data-lucide="users"></i>
                    </div>

                    <div class="user-stat-info">

                        <span>
                            Total Pengguna
                        </span>

                        <strong>
                            <?= $total; ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon green">
                        <i data-lucide="graduation-cap"></i>
                    </div>

                    <div class="user-stat-info">

                        <span>
                            Pengguna
                        </span>

                        <strong>
                            <?= $totalPengguna; ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon purple">
                        <i data-lucide="shield-check"></i>
                    </div>

                    <div class="user-stat-info">

                        <span>
                            Admin
                        </span>

                        <strong>
                            <?= $totalAdmin; ?>
                        </strong>

                    </div>

                </div>


                <div class="user-stat-card">

                    <div class="user-stat-icon orange">
                        <i data-lucide="wrench"></i>
                    </div>

                    <div class="user-stat-info">

                        <span>
                            Petugas
                        </span>

                        <strong>
                            <?= $totalPetugas; ?>
                        </strong>

                    </div>

                </div>


            </div>



            <!-- =========================
                 TABLE CARD
            ========================== -->

            <div class="users-card">


                <!-- CARD HEADER -->

                <div class="users-card-header">

                    <div>

                        <h2>
                            Daftar Pengguna
                        </h2>

                        <p>
                            Seluruh akun yang terdaftar dalam sistem.
                        </p>

                    </div>

                    <div class="user-total-badge">

                        <?= $total; ?> pengguna

                    </div>

                </div>



                <!-- =========================
                     FILTER
                ========================== -->

                <div class="users-filter">


                    <div class="user-search">

                        <i data-lucide="search"></i>

                        <input
                            type="text"
                            id="searchUser"
                            placeholder="Cari nama, email, fakultas..."
                        >

                    </div>


                    <div class="role-filter">

                        <i data-lucide="filter"></i>

                        <select id="roleFilter">

                            <option value="">
                                Semua Role
                            </option>

                            <option value="pengguna">
                                Pengguna
                            </option>

                            <option value="admin">
                                Admin
                            </option>

                            <option value="petugas">
                                Petugas
                            </option>

                        </select>

                    </div>


                </div>



                <!-- =========================
                     TABLE
                ========================== -->

                <div class="users-table-wrapper">

                    <table class="users-table">

                        <thead>

                            <tr>

                                <th>
                                    Pengguna
                                </th>

                                <th>
                                    Kontak
                                </th>

                                <th>
                                    Fakultas
                                </th>

                                <th>
                                    Role
                                </th>

                                <th>
                                    Pengaduan
                                </th>

                                <th>
                                    Terdaftar
                                </th>

                            </tr>

                        </thead>


                        <tbody id="usersTableBody">


                        <?php if (mysqli_num_rows($result) > 0): ?>


                            <?php while (
                                $row =
                                    mysqli_fetch_assoc($result)
                            ): ?>


                                <?php

                                $role =
                                    strtolower(
                                        $row['role']
                                    );

                                $roleClass =
                                    'user';

                                if ($role === 'admin') {
                                    $roleClass = 'admin';
                                } elseif (
                                    $role === 'petugas'
                                ) {
                                    $roleClass = 'petugas';
                                }

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

                                $searchText =
                                    strtolower(
                                        $row['nama'] .
                                        ' ' .
                                        $row['email'] .
                                        ' ' .
                                        ($row['fakultas'] ?? '') .
                                        ' ' .
                                        $row['role']
                                    );

                                ?>


                                <tr
                                    data-role="<?= htmlspecialchars($role); ?>"
                                    data-search="<?= htmlspecialchars($searchText); ?>"
                                >


                                    <!-- PENGGUNA -->

                                    <td>

                                        <div class="user-profile">

                                            <div class="user-avatar">

                                                <?= htmlspecialchars(
                                                    $initial
                                                ); ?>

                                            </div>


                                            <div class="user-profile-info">

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

                                        <div class="contact-info">

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

                                            <span class="faculty-badge">

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



                                    <!-- ROLE -->

                                    <td>

                                        <span
                                            class="role-badge <?= $roleClass; ?>"
                                        >

                                            <?php if (
                                                $role === 'admin'
                                            ): ?>

                                                <i data-lucide="shield-check"></i>

                                                Admin

                                            <?php elseif (
                                                $role === 'petugas'
                                            ): ?>

                                                <i data-lucide="wrench"></i>

                                                Petugas

                                            <?php else: ?>

                                                <i data-lucide="graduation-cap"></i>

                                                Pengguna

                                            <?php endif; ?>

                                        </span>

                                    </td>



                                    <!-- TOTAL PENGADUAN -->

                                    <td>

                                        <div class="complaint-count">

                                            <strong>
                                                <?= $row['total_pengaduan']; ?>
                                            </strong>

                                            <span>
                                                laporan
                                            </span>

                                        </div>

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

                                <td colspan="6">

                                    <div class="users-empty">

                                        <div class="empty-users-icon">

                                            <i data-lucide="users-round"></i>

                                        </div>

                                        <h3>
                                            Belum Ada Pengguna
                                        </h3>

                                        <p>
                                            Belum ada akun yang terdaftar dalam sistem.
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
   USERS PAGE
===================================================== */

.admin-users-page {
    min-height: calc(100vh - 76px);
    background: #f8fafc;
}

.users-container {
    width: min(92%, 1160px);
    margin: 0 auto;
}


/* =====================================================
   HEADER
===================================================== */

.users-header {
    padding: 42px 0 28px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.users-heading {
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

.users-heading h1 {
    margin: 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.7px;
}

.users-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
}


/* =====================================================
   CONTENT
===================================================== */

.users-content {
    padding: 28px 0 55px;
}


/* =====================================================
   STATISTICS
===================================================== */

.user-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.user-stat-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
}

.user-stat-icon {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    flex-shrink: 0;
}

.user-stat-icon svg {
    width: 20px;
    height: 20px;
}

.user-stat-icon.blue {
    color: #2563eb;
    background: #eff6ff;
}

.user-stat-icon.green {
    color: #16a34a;
    background: #f0fdf4;
}

.user-stat-icon.purple {
    color: #7c3aed;
    background: #f5f3ff;
}

.user-stat-icon.orange {
    color: #ea580c;
    background: #fff7ed;
}

.user-stat-info {
    display: flex;
    flex-direction: column;
}

.user-stat-info span {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.user-stat-info strong {
    margin-top: 3px;
    color: #0f172a;
    font-size: 23px;
    font-weight: 800;
}


/* =====================================================
   CARD
===================================================== */

.users-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 17px;
    box-shadow: 0 8px 25px rgba(15, 23, 42, .04);
}

.users-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 21px 22px;
    border-bottom: 1px solid #e2e8f0;
}

.users-card-header h2 {
    margin: 0;
    color: #0f172a;
    font-size: 16px;
    font-weight: 800;
}

.users-card-header p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 11px;
}

.user-total-badge {
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

.users-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 22px;
    background: #fbfdff;
    border-bottom: 1px solid #e2e8f0;
}

.user-search,
.role-filter {
    height: 42px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 12px;
    background: #ffffff;
    border: 1px solid #dbe3ee;
    border-radius: 10px;
}

.user-search {
    flex: 1;
}

.user-search svg,
.role-filter svg {
    width: 17px;
    height: 17px;
    color: #94a3b8;
    flex-shrink: 0;
}

.user-search input,
.role-filter select {
    width: 100%;
    height: 100%;
    border: none;
    outline: none;
    color: #334155;
    background: transparent;
    font-family: inherit;
    font-size: 12px;
}

.role-filter {
    width: 175px;
}

.role-filter select {
    cursor: pointer;
}


/* =====================================================
   TABLE
===================================================== */

.users-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.users-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.users-table th {
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

.users-table td {
    padding: 15px 18px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}

.users-table tbody tr {
    transition: background .2s ease;
}

.users-table tbody tr:hover {
    background: #f8fbff;
}


/* =====================================================
   USER PROFILE
===================================================== */

.user-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 800;
    flex-shrink: 0;
}

.user-profile-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.user-profile-info strong {
    max-width: 170px;
    overflow: hidden;
    color: #1e293b;
    font-size: 11px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-profile-info small {
    margin-top: 3px;
    color: #94a3b8;
    font-size: 9px;
}


/* =====================================================
   CONTACT
===================================================== */

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.contact-info div {
    display: flex;
    align-items: center;
    gap: 6px;
    max-width: 190px;
}

.contact-info svg {
    width: 13px;
    height: 13px;
    color: #94a3b8;
    flex-shrink: 0;
}

.contact-info span {
    overflow: hidden;
    color: #64748b;
    font-size: 9px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.no-contact span {
    color: #cbd5e1;
}


/* =====================================================
   FACULTY
===================================================== */

.faculty-badge {
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

.faculty-badge svg {
    width: 13px;
    height: 13px;
}

.not-filled {
    color: #94a3b8;
    font-size: 9px;
}


/* =====================================================
   ROLE
===================================================== */

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
}

.role-badge svg {
    width: 13px;
    height: 13px;
}

.role-badge.user {
    color: #2563eb;
    background: #eff6ff;
}

.role-badge.admin {
    color: #7c3aed;
    background: #f5f3ff;
}

.role-badge.petugas {
    color: #ea580c;
    background: #fff7ed;
}


/* =====================================================
   COMPLAINT COUNT
===================================================== */

.complaint-count {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.complaint-count strong {
    color: #1e293b;
    font-size: 14px;
    font-weight: 800;
}

.complaint-count span {
    color: #94a3b8;
    font-size: 9px;
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

.users-empty {
    padding: 55px 20px;
    text-align: center;
}

.empty-users-icon {
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

.empty-users-icon svg {
    width: 25px;
    height: 25px;
}

.users-empty h3 {
    margin: 0;
    color: #334155;
    font-size: 15px;
}

.users-empty p {
    margin: 5px 0 0;
    color: #94a3b8;
    font-size: 11px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .users-container {
        width: calc(100% - 32px);
    }

    .user-stats {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .users-header {
        padding: 30px 0 22px;
    }

    .users-heading h1 {
        font-size: 23px;
    }

    .users-heading p {
        font-size: 11px;
    }

    .heading-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
    }

    .users-card-header {
        padding: 17px;
    }

    .users-filter {
        flex-direction: column;
        align-items: stretch;
        padding: 13px 17px;
    }

    .role-filter {
        width: auto;
    }

}


@media (max-width: 430px) {

    .users-container {
        width: calc(100% - 24px);
    }

    .users-heading {
        gap: 11px;
    }

    .users-heading h1 {
        font-size: 21px;
    }

    .users-heading p {
        font-size: 10px;
    }

    .user-stats {
        gap: 10px;
    }

    .user-stat-card {
        gap: 9px;
        padding: 12px;
    }

    .user-stat-icon {
        width: 34px;
        height: 34px;
    }

    .user-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .user-stat-info span {
        font-size: 9px;
    }

    .user-stat-info strong {
        font-size: 17px;
    }

    .user-total-badge {
        display: none;
    }

}


/* =====================================================
   ANIMATION
===================================================== */

.users-header,
.user-stats,
.users-card {
    opacity: 0;
}

.admin-users-page.loaded .users-header,
.admin-users-page.loaded .user-stats,
.admin-users-page.loaded .users-card {
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
   SEARCH
===================================================== */

const searchUser =
    document.getElementById(
        "searchUser"
    );

const roleFilter =
    document.getElementById(
        "roleFilter"
    );

const userRows =
    document.querySelectorAll(
        "#usersTableBody tr[data-role]"
    );


function filterUsers() {

    const searchValue =
        searchUser.value
            .toLowerCase()
            .trim();

    const roleValue =
        roleFilter.value;


    userRows.forEach(
        function(row) {

            const text =
                row.dataset.search || "";

            const role =
                row.dataset.role || "";


            const matchSearch =
                text.includes(
                    searchValue
                );


            const matchRole =
                !roleValue ||
                role === roleValue;


            row.style.display =
                matchSearch &&
                matchRole
                    ? ""
                    : "none";

        }
    );

}


if (searchUser) {

    searchUser.addEventListener(
        "input",
        filterUsers
    );

}


if (roleFilter) {

    roleFilter.addEventListener(
        "change",
        filterUsers
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
                ".admin-users-page"
            );

        if (page) {

            page.classList.add(
                "loaded"
            );

        }


        gsap.from(
            ".users-heading",
            {
                opacity: 0,
                y: 18,
                duration: .7,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".user-stat-card",
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
            ".users-card",
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

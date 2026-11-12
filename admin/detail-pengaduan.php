<?php
require_once "../config/database.php";
requireRole('admin');

$id_user = (int) $_SESSION['id_user'];
$id_pengaduan = validId(getString('id'));
if ($id_pengaduan <= 0) {
    header('Location: pengaduan.php');
    exit;
}

// Confirm the report exists before permitting an assignment.
$reportExists = dbOne($conn, 'SELECT id_pengaduan FROM pengaduan WHERE id_pengaduan = ?', 'i', [$id_pengaduan]);
if (!$reportExists) {
    http_response_code(404);
    exit('Pengaduan tidak ditemukan.');
}

$assignSuccess = '';
$assignError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (isset($_POST['assign_petugas'])) {
        $id_petugas_baru = validId(postString('id_petugas'));
        if ($id_petugas_baru <= 0) {
            $assignError = 'Silakan pilih petugas terlebih dahulu.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $lockedReport = dbOne($conn, 'SELECT id_pengaduan FROM pengaduan WHERE id_pengaduan = ? FOR UPDATE', 'i', [$id_pengaduan]);
                $petugasData = dbOne($conn, "SELECT id_user, nama FROM users WHERE id_user = ? AND role = 'petugas' LIMIT 1 FOR UPDATE", 'i', [$id_petugas_baru]);
                if (!$lockedReport || !$petugasData) {
                    throw new InvalidArgumentException('Petugas yang dipilih tidak valid.');
                }
                dbStatement($conn, 'UPDATE pengaduan SET id_petugas = ? WHERE id_pengaduan = ?', 'ii', [$id_petugas_baru, $id_pengaduan]);
                mysqli_commit($conn);
                $assignSuccess = 'Pengaduan berhasil ditugaskan kepada ' . $petugasData['nama'] . '.';
            } catch (InvalidArgumentException $exception) {
                mysqli_rollback($conn);
                $assignError = $exception->getMessage();
            } catch (Throwable $exception) {
                mysqli_rollback($conn);
                safeLog('assignment_failed', ['pengaduan_id' => $id_pengaduan]);
                $assignError = 'Gagal menugaskan petugas. Silakan coba lagi.';
            }
        }
    }
}

$petugasResult = dbQuery($conn, "SELECT id_user, nama, email, no_wa, fakultas FROM users WHERE role = 'petugas' ORDER BY nama ASC");
$result = dbQuery($conn, '
    SELECT p.id_pengaduan, p.no_laporan, p.lokasi, p.deskripsi, p.foto,
        p.status, p.tanggal_lapor, p.id_petugas,
        u.id_user, u.nama AS nama_pengguna, u.email, u.no_wa, u.fakultas,
        k.id_kategori, k.nama_kategori, pt.nama AS nama_petugas
    FROM pengaduan p
    INNER JOIN users u ON p.id_user = u.id_user
    INNER JOIN kategori k ON p.id_kategori = k.id_kategori
    LEFT JOIN users pt ON p.id_petugas = pt.id_user
    WHERE p.id_pengaduan = ? LIMIT 1', 'i', [$id_pengaduan]);
$data = mysqli_fetch_assoc($result);
if (!$data) {
    http_response_code(404);
    exit('Pengaduan tidak ditemukan.');
}

$riwayatResult = dbQuery($conn, 'SELECT id_riwayat, status, keterangan, created_at FROM riwayat_status WHERE id_pengaduan = ? ORDER BY created_at ASC', 'i', [$id_pengaduan]);
$penangananResult = dbQuery($conn, '
    SELECT pn.id_penanganan, pn.catatan, pn.foto_perbaikan, pn.tanggal_penanganan, u.nama AS nama_petugas
    FROM penanganan pn INNER JOIN users u ON pn.id_petugas = u.id_user
    WHERE pn.id_pengaduan = ? ORDER BY pn.tanggal_penanganan DESC', 'i', [$id_pengaduan]);
$statusClass = ['Menunggu' => 'waiting', 'Diproses' => 'processing', 'Selesai' => 'completed'][$data['status']] ?? 'waiting';
$tanggalLapor = date('d F Y, H:i', strtotime($data['tanggal_lapor']));

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
        Detail Pengaduan | ADUIN
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

    <!-- ScrollTrigger -->
    <script nonce="<?= e(cspNonce()) ?>" src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/ScrollTrigger.min.js"></script>

    <!-- Lenis -->
    <script nonce="<?= e(cspNonce()) ?>" src="https://unpkg.com/lenis@1.3.26/dist/lenis.min.js"></script>

</head>


<body>


<?php

$basePath = '../';

$activePage = 'pengaduan';

include '../includes/navbar.php';

?>


<main class="admin-detail-page">


    <!-- =========================
         HEADER
    ========================== -->

    <section class="detail-header">

        <div class="detail-container">

            <a
                href="pengaduan.php"
                class="back-button"
            >
                <i data-lucide="arrow-left"></i>

                <span>
                    Kembali ke Pengaduan
                </span>
            </a>


            <div class="detail-heading">

                <div>

                    <span class="detail-eyebrow">
                        DETAIL LAPORAN
                    </span>

                    <h1>
                        <?= htmlspecialchars($data['no_laporan']); ?>
                    </h1>

                    <p>
                        Detail informasi dan perkembangan pengaduan fasilitas kampus.
                    </p>

                </div>


                <span class="detail-status <?= $statusClass; ?>">

                    <span class="status-dot"></span>

                    <?= htmlspecialchars($data['status']); ?>

                </span>

            </div>

        </div>

    </section>



    <!-- =========================
         CONTENT
    ========================== -->

    <section class="detail-content">

        <div class="detail-container">


            <div class="detail-grid">


                <!-- =========================
                     KOLOM UTAMA
                ========================== -->

                <div class="detail-main">


                    <!-- INFORMASI LAPORAN -->

                    <div class="detail-card">

                        <div class="detail-card-title">

                            <div class="card-title-icon">
                                <i data-lucide="file-text"></i>
                            </div>

                            <div>

                                <h2>
                                    Informasi Laporan
                                </h2>

                                <p>
                                    Data utama pengaduan fasilitas.
                                </p>

                            </div>

                        </div>


                        <div class="info-grid">


                            <div class="info-item">

                                <span class="info-label">
                                    Nomor Laporan
                                </span>

                                <strong>
                                    <?= htmlspecialchars($data['no_laporan']); ?>
                                </strong>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Kategori
                                </span>

                                <strong>
                                    <?= htmlspecialchars($data['nama_kategori']); ?>
                                </strong>

                            </div>


                            <div class="info-item info-full">

                                <span class="info-label">
                                    Lokasi
                                </span>

                                <strong class="location-value">

                                    <i data-lucide="map-pin"></i>

                                    <?= htmlspecialchars($data['lokasi']); ?>

                                </strong>

                            </div>


                            <div class="info-item info-full">

                                <span class="info-label">
                                    Deskripsi Kerusakan
                                </span>

                                <div class="description-box">

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $data['deskripsi']
                                        )
                                    ); ?>

                                </div>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Tanggal Lapor
                                </span>

                                <strong class="date-value">

                                    <i data-lucide="calendar-days"></i>

                                    <?= $tanggalLapor; ?>

                                </strong>

                            </div>


                            <div class="info-item">

                                <span class="info-label">
                                    Petugas
                                </span>

                                <strong>

                                    <?php if (!empty($data['nama_petugas'])): ?>

                                        <?= htmlspecialchars(
                                            $data['nama_petugas']
                                        ); ?>

                                    <?php else: ?>

                                        <span class="not-assigned">
                                            Belum ditugaskan
                                        </span>

                                    <?php endif; ?>

                                </strong>

                            </div>

                        </div>

                    </div>



                    <!-- FOTO KERUSAKAN -->

                    <div class="detail-card">

                        <div class="detail-card-title">

                            <div class="card-title-icon">
                                <i data-lucide="image"></i>
                            </div>

                            <div>

                                <h2>
                                    Foto Kerusakan
                                </h2>

                                <p>
                                    Dokumentasi yang dilampirkan oleh pelapor.
                                </p>

                            </div>

                        </div>


                        <?php if (!empty($data['foto'])): ?>

                            <div class="photo-wrapper">

                                <img
                                    src="<?= uploadUrl($data['foto'], 'pengaduan') ?>"
                                    alt="Foto kerusakan"
                                    class="complaint-photo"
                                >

                                <a
                                    href="<?= uploadUrl($data['foto'], 'pengaduan') ?>"
                                    target="_blank"
                                    class="photo-open"
                                >
                                    <i data-lucide="maximize-2"></i>

                                    Lihat Foto
                                </a>

                            </div>

                        <?php else: ?>

                            <div class="no-photo">

                                <div class="no-photo-icon">
                                    <i data-lucide="image-off"></i>
                                </div>

                                <strong>
                                    Tidak ada foto
                                </strong>

                                <span>
                                    Pelapor tidak melampirkan dokumentasi foto.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>



                    <!-- RIWAYAT STATUS -->

                    <div class="detail-card">

                        <div class="detail-card-title">

                            <div class="card-title-icon">
                                <i data-lucide="history"></i>
                            </div>

                            <div>

                                <h2>
                                    Riwayat Status
                                </h2>

                                <p>
                                    Perkembangan status pengaduan.
                                </p>

                            </div>

                        </div>


                        <div class="timeline">

                            <?php if (
                                $riwayatResult &&
                                mysqli_num_rows($riwayatResult) > 0
                            ): ?>

                                <?php while (
                                    $riwayat =
                                    mysqli_fetch_assoc(
                                        $riwayatResult
                                    )
                                ): ?>

                                    <?php

                                    $timelineClass = 'waiting';

                                    if (
                                        $riwayat['status']
                                        === 'Diproses'
                                    ) {
                                        $timelineClass =
                                            'processing';
                                    }

                                    if (
                                        $riwayat['status']
                                        === 'Selesai'
                                    ) {
                                        $timelineClass =
                                            'completed';
                                    }

                                    ?>

                                    <div class="timeline-item">

                                        <div
                                            class="timeline-dot <?= $timelineClass; ?>"
                                        ></div>

                                        <div class="timeline-content">

                                            <div class="timeline-top">

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $riwayat['status']
                                                    ); ?>
                                                </strong>

                                                <span>
                                                    <?= date(
                                                        'd M Y, H:i',
                                                        strtotime(
                                                            $riwayat['created_at']
                                                        )
                                                    ); ?>
                                                </span>

                                            </div>

                                            <?php if (
                                                !empty(
                                                    $riwayat['keterangan']
                                                )
                                            ): ?>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        $riwayat['keterangan']
                                                    ); ?>
                                                </p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <div class="timeline-empty">

                                    <i data-lucide="clock-3"></i>

                                    <span>
                                        Belum ada riwayat status.
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- PENANGANAN -->

                    <div class="detail-card">

                        <div class="detail-card-title">

                            <div class="card-title-icon">
                                <i data-lucide="wrench"></i>
                            </div>

                            <div>

                                <h2>
                                    Penanganan
                                </h2>

                                <p>
                                    Catatan dan dokumentasi proses perbaikan.
                                </p>

                            </div>

                        </div>


                        <?php if (
                            $penangananResult &&
                            mysqli_num_rows($penangananResult) > 0
                        ): ?>

                            <div class="handling-list">

                                <?php while (
                                    $penanganan =
                                        mysqli_fetch_assoc(
                                            $penangananResult
                                        )
                                ): ?>

                                    <div class="handling-item">

                                        <div class="handling-header">

                                            <div class="handling-person">

                                                <div class="handling-avatar">

                                                    <?= e(strtoupper(
                                                        substr(
                                                            $penanganan[
                                                                'nama_petugas'
                                                            ],
                                                            0,
                                                            1
                                                        )
                                                    )); ?>

                                                </div>

                                                <div>

                                                    <strong>
                                                        <?= htmlspecialchars(
                                                            $penanganan[
                                                                'nama_petugas'
                                                            ]
                                                        ); ?>
                                                    </strong>

                                                    <span>
                                                        <?= date(
                                                            'd M Y, H:i',
                                                            strtotime(
                                                                $penanganan[
                                                                    'tanggal_penanganan'
                                                                ]
                                                            )
                                                        ); ?>
                                                    </span>

                                                </div>

                                            </div>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $penanganan['catatan']
                                            )
                                        ): ?>

                                            <div class="handling-note">

                                                <i data-lucide="message-square"></i>

                                                <span>
                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $penanganan[
                                                                'catatan'
                                                            ]
                                                        )
                                                    ); ?>
                                                </span>

                                            </div>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $penanganan[
                                                    'foto_perbaikan'
                                                ]
                                            )
                                        ): ?>

                                            <div class="repair-photo">

                                                <img
                                                    src="<?= uploadUrl($penanganan[ 'foto_perbaikan' ], 'perbaikan') ?>"
                                                    alt="Foto perbaikan"
                                                >

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endwhile; ?>

                            </div>

                        <?php else: ?>

                            <div class="no-handling">

                                <div class="no-handling-icon">

                                    <i data-lucide="wrench"></i>

                                </div>

                                <strong>
                                    Belum ada penanganan
                                </strong>

                                <span>
                                    Belum terdapat catatan proses perbaikan untuk laporan ini.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>



                <!-- =========================
                     SIDEBAR
                ========================== -->

                <aside class="detail-sidebar">


                    <!-- DATA PELAPOR -->

                    <div class="detail-card">

                        <div class="detail-card-title compact">

                            <div class="card-title-icon">
                                <i data-lucide="user"></i>
                            </div>

                            <div>

                                <h2>
                                    Pelapor
                                </h2>

                            </div>

                        </div>


                        <div class="reporter-profile">

                            <div class="reporter-avatar">

                                <?= e(strtoupper(
                                    substr(
                                        $data['nama_pengguna'],
                                        0,
                                        1
                                    )
                                )); ?>

                            </div>


                            <div class="reporter-name">

                                <strong>
                                    <?= htmlspecialchars(
                                        $data['nama_pengguna']
                                    ); ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars(
                                        $data['email']
                                    ); ?>
                                </span>

                            </div>

                        </div>


                        <div class="sidebar-info">

                            <?php if (!empty($data['no_wa'])): ?>

                                <div>

                                    <i data-lucide="phone"></i>

                                    <span>
                                        <?= htmlspecialchars(
                                            $data['no_wa']
                                        ); ?>
                                    </span>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($data['fakultas'])): ?>

                                <div>

                                    <i data-lucide="building-2"></i>

                                    <span>
                                        <?= htmlspecialchars(
                                            $data['fakultas']
                                        ); ?>
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- RINGKASAN -->

                    <div class="detail-card">

                        <div class="detail-card-title compact">

                            <div class="card-title-icon">
                                <i data-lucide="clipboard-list"></i>
                            </div>

                            <div>

                                <h2>
                                    Ringkasan
                                </h2>

                            </div>

                        </div>


                        <div class="summary-list">

                            <div class="summary-row">

                                <span>
                                    ID Pengaduan
                                </span>

                                <strong>
                                    #<?= $data['id_pengaduan']; ?>
                                </strong>

                            </div>


                            <div class="summary-row">

                                <span>
                                    Kategori
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $data['nama_kategori']
                                    ); ?>
                                </strong>

                            </div>


                            <div class="summary-row">

                                <span>
                                    Status
                                </span>

                                <span
                                    class="status-badge <?= $statusClass; ?>"
                                >

                                    <span class="status-dot"></span>

                                    <?= htmlspecialchars(
                                        $data['status']
                                    ); ?>

                                </span>

                            </div>


                            <div class="summary-row">

                                <span>
                                    Petugas
                                </span>

                                <strong>

                                    <?= !empty(
                                        $data['nama_petugas']
                                    )
                                        ? htmlspecialchars(
                                            $data['nama_petugas']
                                        )
                                        : 'Belum ditugaskan';
                                    ?>

                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- AKSI -->

                    <div class="detail-card action-card">

                        <div class="detail-card-title compact">

                            <div class="card-title-icon">
                                <i data-lucide="settings-2"></i>
                            </div>

                            <div>

                                <h2>
                                    Aksi
                                </h2>

                                <p>
                                    Kelola penugasan laporan.
                                </p>

                            </div>

                        </div>


                        <?php if (!empty($assignSuccess)): ?>

                            <div class="assign-alert success">

                                <i data-lucide="circle-check"></i>

                                <span>
                                    <?= htmlspecialchars($assignSuccess); ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($assignError)): ?>

                            <div class="assign-alert error">

                                <i data-lucide="circle-alert"></i>

                                <span>
                                    <?= htmlspecialchars($assignError); ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- PENUGASAN PETUGAS -->

                        <form
                            method="POST"
                            class="assign-form"
                        >
                        <?= csrfField() ?>

                            <label for="id_petugas">
                                Petugas Penanganan
                            </label>

                            <div class="select-wrapper">

                                <i data-lucide="user-round"></i>

                                <select
                                    name="id_petugas"
                                    id="id_petugas"
                                    required
                                >

                                    <option value="">
                                        Pilih petugas
                                    </option>

                                    <?php if (
                                        $petugasResult &&
                                        mysqli_num_rows($petugasResult) > 0
                                    ): ?>

                                        <?php while (
                                            $petugas = mysqli_fetch_assoc($petugasResult)
                                        ): ?>

                                            <option
                                                value="<?= $petugas['id_user']; ?>"
                                                <?= (
                                                    (int)$data['id_petugas']
                                                    ===
                                                    (int)$petugas['id_user']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $petugas['nama']
                                                ); ?>

                                            </option>

                                        <?php endwhile; ?>

                                    <?php else: ?>

                                        <option value="" disabled>
                                            Belum ada petugas
                                        </option>

                                    <?php endif; ?>

                                </select>

                            </div>


                            <button
                                type="submit"
                                name="assign_petugas"
                                class="action-button primary assign-button"
                            >

                                <i data-lucide="user-check"></i>

                                <span>
                                    <?= !empty($data['id_petugas'])
                                        ? 'Ubah Penugasan'
                                        : 'Tugaskan Petugas'
                                    ?>
                                </span>

                            </button>

                        </form>


                        <!-- REFRESH -->

                        <a
                            href="detail-pengaduan.php?id=<?= $data['id_pengaduan']; ?>"
                            class="action-button secondary"
                        >

                            <i data-lucide="refresh-cw"></i>

                            <span>
                                Refresh Data
                            </span>

                        </a>


                        <!-- KEMBALI -->

                        <a
                            href="pengaduan.php"
                            class="action-button secondary"
                        >

                            <i data-lucide="arrow-left"></i>

                            <span>
                                Kembali
                            </span>

                        </a>

                    </div>

                </aside>

            </div>

        </div>

    </section>

</main>


<style>

/* =====================================================
   DETAIL PAGE
===================================================== */

.admin-detail-page {
    min-height: calc(100vh - 76px);
    background: #f8fafc;
}

.detail-container {
    width: min(92%, 1160px);
    margin: 0 auto;
}


/* =====================================================
   HEADER
===================================================== */

.detail-header {
    padding: 30px 0 28px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 22px;
    color: #64748b;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: .2s ease;
}

.back-button svg {
    width: 16px;
    height: 16px;
}

.back-button:hover {
    color: #2563eb;
    transform: translateX(-2px);
}

.detail-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
}

.detail-eyebrow {
    display: block;
    margin-bottom: 5px;
    color: #2563eb;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.1px;
}

.detail-heading h1 {
    margin: 0;
    color: #0f172a;
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -.6px;
}

.detail-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 12px;
}


/* =====================================================
   STATUS
===================================================== */

.detail-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.detail-status.waiting {
    color: #b45309;
    background: #fffbeb;
}

.detail-status.processing {
    color: #6d28d9;
    background: #f5f3ff;
}

.detail-status.completed {
    color: #15803d;
    background: #f0fdf4;
}

.detail-status .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.detail-status.waiting .status-dot {
    background: #f59e0b;
}

.detail-status.processing .status-dot {
    background: #8b5cf6;
}

.detail-status.completed .status-dot {
    background: #22c55e;
}


/* =====================================================
   CONTENT
===================================================== */

.detail-content {
    padding: 28px 0 60px;
}

.detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 20px;
    align-items: start;
}

.detail-main {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.detail-sidebar {
    display: flex;
    flex-direction: column;
    gap: 18px;
    position: sticky;
    top: 96px;
}


/* =====================================================
   CARD
===================================================== */

.detail-card {
    padding: 21px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 6px 22px rgba(15, 23, 42, .035);
}

.detail-card-title {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 20px;
}

.detail-card-title.compact {
    margin-bottom: 17px;
}

.card-title-icon {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 10px;
    flex-shrink: 0;
}

.card-title-icon svg {
    width: 18px;
    height: 18px;
}

.detail-card-title h2 {
    margin: 0;
    color: #0f172a;
    font-size: 14px;
    font-weight: 800;
}

.detail-card-title p {
    margin: 3px 0 0;
    color: #94a3b8;
    font-size: 10px;
}


/* =====================================================
   INFORMATION
===================================================== */

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.info-item {
    min-width: 0;
}

.info-full {
    grid-column: 1 / -1;
}

.info-label {
    display: block;
    margin-bottom: 6px;
    color: #94a3b8;
    font-size: 10px;
    font-weight: 700;
}

.info-item > strong {
    display: block;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.location-value,
.date-value {
    display: flex !important;
    align-items: center;
    gap: 6px;
}

.location-value svg,
.date-value svg {
    width: 15px;
    height: 15px;
    color: #2563eb;
    flex-shrink: 0;
}

.not-assigned {
    color: #94a3b8 !important;
    font-weight: 600 !important;
}

.description-box {
    padding: 13px 14px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 10px;
    font-size: 12px;
    line-height: 1.7;
}


/* =====================================================
   PHOTO
===================================================== */

.photo-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    background: #f1f5f9;
}

.complaint-photo {
    display: block;
    width: 100%;
    max-height: 420px;
    object-fit: cover;
}

.photo-open {
    position: absolute;
    right: 12px;
    bottom: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 11px;
    color: #ffffff;
    background: rgba(15, 23, 42, .78);
    border-radius: 8px;
    text-decoration: none;
    font-size: 10px;
    font-weight: 700;
    backdrop-filter: blur(8px);
    transition: .2s ease;
}

.photo-open:hover {
    background: #2563eb;
}

.photo-open svg {
    width: 14px;
    height: 14px;
}

.no-photo,
.no-handling {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 38px 20px;
    text-align: center;
    background: #f8fafc;
    border: 1px dashed #dbe3ee;
    border-radius: 12px;
}

.no-photo-icon,
.no-handling-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    color: #94a3b8;
    background: #eef2f7;
    border-radius: 13px;
}

.no-photo-icon svg,
.no-handling-icon svg {
    width: 21px;
    height: 21px;
}

.no-photo strong,
.no-handling strong {
    color: #475569;
    font-size: 12px;
}

.no-photo span,
.no-handling span {
    max-width: 340px;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 10px;
    line-height: 1.5;
}


/* =====================================================
   TIMELINE
===================================================== */

.timeline {
    position: relative;
    padding-left: 12px;
}

.timeline::before {
    content: "";
    position: absolute;
    top: 8px;
    bottom: 8px;
    left: 17px;
    width: 1px;
    background: #e2e8f0;
}

.timeline-item {
    position: relative;
    display: flex;
    gap: 15px;
    padding-bottom: 22px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-dot {
    position: relative;
    z-index: 2;
    width: 12px;
    height: 12px;
    margin-top: 4px;
    border: 3px solid #ffffff;
    border-radius: 50%;
    box-shadow: 0 0 0 1px #dbe3ee;
    flex-shrink: 0;
}

.timeline-dot.waiting {
    background: #f59e0b;
}

.timeline-dot.processing {
    background: #8b5cf6;
}

.timeline-dot.completed {
    background: #22c55e;
}

.timeline-content {
    flex: 1;
    min-width: 0;
}

.timeline-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.timeline-top strong {
    color: #334155;
    font-size: 11px;
}

.timeline-top span {
    color: #94a3b8;
    font-size: 9px;
    white-space: nowrap;
}

.timeline-content p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 10px;
    line-height: 1.6;
}

.timeline-empty {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 11px;
}

.timeline-empty svg {
    width: 17px;
    height: 17px;
}


/* =====================================================
   HANDLING
===================================================== */

.handling-list {
    display: flex;
    flex-direction: column;
    gap: 13px;
}

.handling-item {
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 11px;
}

.handling-person {
    display: flex;
    align-items: center;
    gap: 9px;
}

.handling-avatar {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
}

.handling-person div:last-child {
    display: flex;
    flex-direction: column;
}

.handling-person strong {
    color: #334155;
    font-size: 11px;
}

.handling-person span {
    margin-top: 2px;
    color: #94a3b8;
    font-size: 9px;
}

.handling-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 13px;
    padding: 11px;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    font-size: 10px;
    line-height: 1.6;
}

.handling-note svg {
    width: 15px;
    height: 15px;
    color: #2563eb;
    flex-shrink: 0;
    margin-top: 1px;
}

.repair-photo {
    overflow: hidden;
    margin-top: 12px;
    border-radius: 9px;
}

.repair-photo img {
    display: block;
    width: 100%;
    max-height: 280px;
    object-fit: cover;
}


/* =====================================================
   SIDEBAR
===================================================== */

.reporter-profile {
    display: flex;
    align-items: center;
    gap: 11px;
}

.reporter-avatar {
    width: 43px;
    height: 43px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    background: #eff6ff;
    border-radius: 50%;
    font-size: 15px;
    font-weight: 800;
    flex-shrink: 0;
}

.reporter-name {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.reporter-name strong {
    overflow: hidden;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reporter-name span {
    overflow: hidden;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 9px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-info {
    display: flex;
    flex-direction: column;
    gap: 9px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #eef2f7;
}

.sidebar-info div {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 10px;
}

.sidebar-info svg {
    width: 15px;
    height: 15px;
    color: #94a3b8;
    flex-shrink: 0;
}


/* =====================================================
   SUMMARY
===================================================== */

.summary-list {
    display: flex;
    flex-direction: column;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f7;
}

.summary-row:first-child {
    padding-top: 0;
}

.summary-row:last-child {
    padding-bottom: 0;
    border-bottom: none;
}

.summary-row > span:first-child {
    color: #94a3b8;
    font-size: 10px;
}

.summary-row > strong {
    max-width: 150px;
    color: #334155;
    font-size: 10px;
    font-weight: 700;
    text-align: right;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border-radius: 7px;
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
}

.status-badge .status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.status-badge.waiting {
    color: #b45309;
    background: #fffbeb;
}

.status-badge.waiting .status-dot {
    background: #f59e0b;
}

.status-badge.processing {
    color: #6d28d9;
    background: #f5f3ff;
}

.status-badge.processing .status-dot {
    background: #8b5cf6;
}

.status-badge.completed {
    color: #15803d;
    background: #f0fdf4;
}

.status-badge.completed .status-dot {
    background: #22c55e;
}


/* =====================================================
   ACTION
===================================================== */

.action-card {
    background: #fbfdff;
}

.action-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    min-height: 40px;
    margin-top: 9px;
    padding: 0 12px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 10px;
    font-weight: 700;
    transition: .2s ease;
    box-sizing: border-box;
}

.action-button:first-of-type {
    margin-top: 0;
}

.action-button svg {
    width: 15px;
    height: 15px;
}

.action-button.primary {
    color: #ffffff;
    background: #2563eb;
    box-shadow: 0 7px 17px rgba(37, 99, 235, .16);
}

.action-button.primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.action-button.secondary {
    color: #475569;
    background: #ffffff;
    border: 1px solid #dbe3ee;
}

.action-button.secondary:hover {
    color: #2563eb;
    border-color: #bfdbfe;
    background: #eff6ff;
}


/* =====================================================
   PENUGASAN PETUGAS
===================================================== */

.assign-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 10px;
}

.assign-form label {
    color: #475569;
    font-size: 10px;
    font-weight: 700;
}

.select-wrapper {
    position: relative;
}

.select-wrapper > svg {
    position: absolute;
    top: 50%;
    left: 12px;
    width: 15px;
    height: 15px;
    color: #94a3b8;
    transform: translateY(-50%);
    pointer-events: none;
}

.select-wrapper select {
    width: 100%;
    height: 40px;
    padding: 0 34px 0 36px;
    color: #334155;
    background: #ffffff;
    border: 1px solid #dbe3ee;
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    box-sizing: border-box;
    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.select-wrapper select:hover {
    border-color: #bfdbfe;
}

.select-wrapper select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.assign-button {
    border: none;
    cursor: pointer;
    font-family: inherit;
}

.assign-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 12px;
    padding: 10px 11px;
    border-radius: 9px;
    font-size: 9px;
    line-height: 1.5;
}

.assign-alert svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    margin-top: 1px;
}

.assign-alert.success {
    color: #166534;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.assign-alert.success svg {
    color: #16a34a;
}

.assign-alert.error {
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
}

.assign-alert.error svg {
    color: #dc2626;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 900px) {

    .detail-container {
        width: calc(100% - 32px);
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-sidebar {
        position: static;
    }

}


@media (max-width: 600px) {

    .detail-header {
        padding: 24px 0;
    }

    .detail-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 13px;
    }

    .detail-heading h1 {
        font-size: 22px;
    }

    .detail-heading p {
        font-size: 10px;
    }

    .detail-content {
        padding-top: 20px;
    }

    .detail-card {
        padding: 17px;
        border-radius: 14px;
    }

    .info-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .info-full {
        grid-column: auto;
    }

    .timeline-top {
        align-items: flex-start;
        flex-direction: column;
        gap: 3px;
    }

}


@media (max-width: 430px) {

    .detail-container {
        width: calc(100% - 24px);
    }

    .detail-heading h1 {
        font-size: 20px;
    }

    .back-button {
        font-size: 10px;
    }

    .detail-card {
        padding: 15px;
    }

    .detail-card-title h2 {
        font-size: 13px;
    }

    .detail-card-title p {
        font-size: 9px;
    }

    .description-box {
        font-size: 11px;
    }

    .photo-open {
        right: 8px;
        bottom: 8px;
    }

    .assign-form label {
        font-size: 9px;
    }

    .select-wrapper select {
        height: 42px;
        font-size: 10px;
    }

    .assign-button {
        min-height: 42px;
    }

    .assign-alert {
        font-size: 9px;
    }

}


/* =====================================================
   ANIMATION
===================================================== */

.detail-header,
.detail-card {
    opacity: 0;
}

.admin-detail-page.loaded .detail-header,
.admin-detail-page.loaded .detail-card {
    opacity: 1;
}
/* =====================================================
   FIX CARD AKSI - ADMIN DETAIL PENGADUAN
===================================================== */

.action-card {
    display: block !important;
    width: 100%;
    overflow: hidden;
}

/* Header Aksi harus satu baris sendiri */
.action-card > .detail-card-title {
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    gap: 11px !important;
    margin-bottom: 18px !important;
}

/* Judul tidak boleh terdorong */
.action-card > .detail-card-title > div:last-child {
    min-width: 0;
    flex: 1;
}

.action-card .detail-card-title h2 {
    white-space: nowrap;
}

.action-card .detail-card-title p {
    white-space: normal;
    line-height: 1.4;
}

/* Form penugasan full width */
.action-card .assign-form {
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    gap: 8px !important;
    margin: 0 0 10px !important;
}

/* Label */
.action-card .assign-form label {
    display: block;
    width: 100%;
    color: #475569;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.4;
}

/* Select full width */
.action-card .select-wrapper {
    position: relative !important;
    display: block !important;
    width: 100% !important;
}

.action-card .select-wrapper select {
    display: block !important;
    width: 100% !important;
    height: 42px !important;
    min-height: 42px !important;
    padding: 0 34px 0 36px !important;
}

/* Semua tombol full width dan tersusun vertikal */
.action-card .action-button {
    display: flex !important;
    width: 100% !important;
    min-width: 0 !important;
    min-height: 40px !important;
    margin: 8px 0 0 !important;
    box-sizing: border-box !important;
}

/* Tombol pertama dalam form */
.action-card .assign-button {
    width: 100% !important;
    margin-top: 0 !important;
}

/* Refresh dan kembali */
.action-card > .action-button {
    width: 100% !important;
}

/* Alert juga full width */
.action-card .assign-alert {
    display: flex !important;
    width: 100% !important;
    box-sizing: border-box;
    margin-bottom: 12px;
}

/* Mobile */
@media (max-width: 768px) {

    .action-card {
        padding: 15px !important;
    }

    .action-card > .detail-card-title {
        margin-bottom: 16px !important;
    }

    .action-card .card-title-icon {
        width: 36px;
        height: 36px;
        min-width: 36px;
    }

    .action-card .detail-card-title h2 {
        font-size: 13px;
    }

    .action-card .detail-card-title p {
        font-size: 9px;
    }

    .action-card .action-button {
        min-height: 42px !important;
    }

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
   NAVBAR
===================================================== */

window.addEventListener(
    "scroll",
    function() {

        const navbar =
            document.getElementById("navbar");

        if (!navbar) return;

        if (window.scrollY > 25) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
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
                ".admin-detail-page"
            );

        if (page) {
            page.classList.add("loaded");
        }


        gsap.from(
            ".detail-header",
            {
                opacity: 0,
                y: 18,
                duration: .65,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".detail-main .detail-card",
            {
                opacity: 0,
                y: 22,
                duration: .6,
                stagger: .08,
                delay: .12,
                ease: "power3.out"
            }
        );


        gsap.from(
            ".detail-sidebar .detail-card",
            {
                opacity: 0,
                y: 22,
                duration: .6,
                stagger: .1,
                delay: .18,
                ease: "power3.out"
            }
        );

    }
);


/* =====================================================
   MOBILE MENU
===================================================== */

const mobileMenuBtn =
    document.getElementById("mobileMenuBtn");

const mobileMenu =
    document.getElementById("mobileMenu");


if (mobileMenuBtn && mobileMenu) {

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
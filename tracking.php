<?php

require_once "config/database.php";


/* =========================================================
   INPUT NOMOR LAPORAN
========================================================= */

$nomorLaporan = trim(getString('nomor'));

$error = '';
$dataPengaduan = null;
$riwayatStatus = [];
$dataPenanganan = null;


/* =========================================================
   HELPER
========================================================= */

function formatTanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
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


/* =========================================================
   PROSES PENCARIAN
========================================================= */

if ($nomorLaporan !== '' && (!rateLimit('tracking_ip', clientAddress(), 60, 900) || strlen($nomorLaporan) > 80)) {
    http_response_code(429);
    $error = 'Pencarian terlalu sering atau nomor laporan tidak valid. Silakan coba lagi nanti.';
}
if ($nomorLaporan !== '' && $error === '') {

    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            p.id_pengaduan,
            p.no_laporan,
            p.id_user,
            p.id_petugas,
            p.id_kategori,
            p.lokasi,
            p.deskripsi,
            p.foto,
            p.status,
            p.tanggal_lapor,

            k.nama_kategori,

            u.nama AS nama_pelapor,
            u.email AS email_pelapor,

            pt.nama AS nama_petugas

        FROM pengaduan p

        INNER JOIN kategori k
            ON p.id_kategori = k.id_kategori

        INNER JOIN users u
            ON p.id_user = u.id_user

        LEFT JOIN users pt
            ON p.id_petugas = pt.id_user

        WHERE p.no_laporan = ?

        LIMIT 1
        "
    );


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nomorLaporan
    );


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    $dataPengaduan =
        mysqli_fetch_assoc($result);


    /* =====================================================
       JIKA LAPORAN DITEMUKAN
    ===================================================== */

    if ($dataPengaduan) {

        $idPengaduan =
            (int)$dataPengaduan['id_pengaduan'];


        /* ================================================
           RIWAYAT STATUS
        ================================================ */

        $stmtHistory = mysqli_prepare(
            $conn,
            "
            SELECT
                id_riwayat,
                status,
                keterangan,
                created_at
            FROM riwayat_status
            WHERE id_pengaduan = ?
            ORDER BY created_at ASC
            "
        );


        mysqli_stmt_bind_param(
            $stmtHistory,
            "i",
            $idPengaduan
        );


        mysqli_stmt_execute(
            $stmtHistory
        );


        $historyResult =
            mysqli_stmt_get_result(
                $stmtHistory
            );


        while (
            $history =
            mysqli_fetch_assoc(
                $historyResult
            )
        ) {

            $riwayatStatus[] =
                $history;

        }


        /* ================================================
           DATA PENANGANAN TERBARU
        ================================================ */

        $stmtHandling = mysqli_prepare(
            $conn,
            "
            SELECT
                pn.id_penanganan,
                pn.catatan,
                pn.foto_perbaikan,
                pn.tanggal_penanganan,

                u.nama AS nama_petugas

            FROM penanganan pn

            INNER JOIN users u
                ON pn.id_petugas = u.id_user

            WHERE pn.id_pengaduan = ?

            ORDER BY pn.tanggal_penanganan DESC

            LIMIT 1
            "
        );


        mysqli_stmt_bind_param(
            $stmtHandling,
            "i",
            $idPengaduan
        );


        mysqli_stmt_execute(
            $stmtHandling
        );


        $handlingResult =
            mysqli_stmt_get_result(
                $stmtHandling
            );


        $dataPenanganan =
            mysqli_fetch_assoc(
                $handlingResult
            );

    } else {

        $error =
            "Nomor laporan tidak ditemukan. Pastikan nomor laporan yang Anda masukkan sudah benar.";

    }

}


// Public tracking exposes progress, not reporter details or user-supplied text.
// Full details retain their existing layout for the owner, assigned staff and admin.
if ($dataPengaduan) {
    $viewer = currentUser();
    $canReadDetails = $viewer && ($viewer['role'] === 'admin'
        || ($viewer['role'] === 'pengguna' && (int)$viewer['id_user'] === (int)$dataPengaduan['id_user'])
        || ($viewer['role'] === 'petugas' && (int)$viewer['id_user'] === (int)$dataPengaduan['id_petugas']));
    if (!$canReadDetails) {
        $dataPengaduan['lokasi'] = 'Detail lokasi tersedia bagi pemilik laporan.';
        $dataPengaduan['deskripsi'] = 'Detail pengaduan tersedia bagi pemilik laporan.';
        $dataPengaduan['foto'] = null;
        unset($dataPengaduan['nama_pelapor'], $dataPengaduan['email_pelapor']);
        foreach ($riwayatStatus as &$history) {
            $history['keterangan'] = '';
        }
        unset($history);
        if ($dataPenanganan) {
            $dataPenanganan['nama_petugas'] = 'Petugas kampus';
            $dataPenanganan['catatan'] = 'Detail penanganan tersedia bagi pemilik laporan.';
            $dataPenanganan['foto_perbaikan'] = null;
        }
    }
}

/* =========================================================
   STATUS
========================================================= */

$currentStatus =
    $dataPengaduan['status'] ?? '';

$currentStatusClass =
    statusClass($currentStatus);


/* =========================================================
   CEK STATUS TIMELINE
========================================================= */

$menungguSelesai = false;
$diprosesSelesai = false;
$selesaiSelesai = false;

foreach ($riwayatStatus as $history) {

    if ($history['status'] === 'Menunggu') {
        $menungguSelesai = true;
    }

    if ($history['status'] === 'Diproses') {
        $diprosesSelesai = true;
    }

    if ($history['status'] === 'Selesai') {
        $selesaiSelesai = true;
    }

}


/*
 * Jika status saat ini sudah lebih tinggi,
 * status sebelumnya dianggap selesai.
 */

if (
    $currentStatus === 'Diproses' ||
    $currentStatus === 'Selesai'
) {

    $menungguSelesai = true;

}

if ($currentStatus === 'Selesai') {

    $menungguSelesai = true;
    $diprosesSelesai = true;
    $selesaiSelesai = true;

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

    <title>Lacak Pengaduan | ADUIN</title>

    <link
        rel="icon"
        type="image/png"
        href="assets/img/logo-aduin.png"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
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

$basePath = '';

$activePage = 'tracking';

include 'includes/navbar.php';

?>


<main class="tracking-page">

    <div class="tracking-shell">


        <!-- =====================================================
             HEADER
        ===================================================== -->

        <section class="tracking-header">

            <div class="tracking-heading">

                <div class="tracking-icon">

                    <i data-lucide="search-check"></i>

                </div>


                <div>

                    <span class="tracking-eyebrow">
                        ADUIN
                    </span>

                    <h1>
                        Lacak Pengaduan
                    </h1>

                    <p>
                        Pantau perkembangan laporan fasilitas
                        kampus Anda secara mudah dan transparan.
                    </p>

                </div>

            </div>

        </section>



        <!-- =====================================================
             SEARCH
        ===================================================== -->

        <section class="tracking-search-card">

            <div class="search-card-heading">

                <div class="search-card-icon">

                    <i data-lucide="file-search"></i>

                </div>


                <div>

                    <h2>
                        Cari Nomor Laporan
                    </h2>

                    <p>
                        Masukkan nomor laporan yang Anda terima
                        setelah membuat pengaduan.
                    </p>

                </div>

            </div>


            <form
                action="tracking.php"
                method="GET"
                class="tracking-form"
                id="trackingForm"
            >

                <div class="tracking-input-wrap">

                    <i data-lucide="hash"></i>

                    <input
                        type="text"
                        name="nomor"
                        id="nomor"
                        placeholder="Contoh: LAP-20260918151153"
                        value="<?= htmlspecialchars($nomorLaporan) ?>"
                        autocomplete="off"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="tracking-submit"
                    id="trackingSubmit"
                >

                    <i data-lucide="search"></i>

                    <span>
                        Lacak Laporan
                    </span>

                </button>

            </form>


            <div class="search-hint">

                <i data-lucide="info"></i>

                <span>
                    Nomor laporan dapat ditemukan pada halaman
                    <strong>Riwayat Pengaduan</strong>.
                </span>

            </div>

        </section>



        <!-- =====================================================
             ERROR
        ===================================================== -->

        <?php if ($error !== ''): ?>

            <section class="tracking-error">

                <div class="tracking-error-icon">

                    <i data-lucide="file-x-2"></i>

                </div>


                <div>

                    <strong>
                        Laporan tidak ditemukan
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            </section>

        <?php endif; ?>



        <!-- =====================================================
             HASIL TRACKING
        ===================================================== -->

        <?php if ($dataPengaduan): ?>


            <!-- =================================================
                 STATUS HERO
            ================================================= -->

            <section class="tracking-result">


                <div class="result-top">

                    <div>

                        <span class="result-label">
                            Nomor Laporan
                        </span>

                        <h2>
                            <?= htmlspecialchars(
                                $dataPengaduan['no_laporan']
                            ) ?>
                        </h2>

                    </div>


                    <div
                        class="result-status <?= $currentStatusClass ?>"
                    >

                        <?php if (
                            $currentStatus === 'Menunggu'
                        ): ?>

                            <i data-lucide="clock-3"></i>

                        <?php elseif (
                            $currentStatus === 'Diproses'
                        ): ?>

                            <i data-lucide="loader-circle"></i>

                        <?php else: ?>

                            <i data-lucide="circle-check"></i>

                        <?php endif; ?>


                        <span>
                            <?= htmlspecialchars(
                                $currentStatus
                            ) ?>
                        </span>

                    </div>

                </div>



                <!-- STATUS MESSAGE -->

                <div class="status-message">

                    <?php if (
                        $currentStatus === 'Menunggu'
                    ): ?>

                        <div class="status-message-icon">

                            <i data-lucide="clock-3"></i>

                        </div>


                        <div>

                            <strong>
                                Pengaduan sedang menunggu verifikasi
                            </strong>

                            <p>
                                Laporan Anda sudah diterima dan
                                menunggu pemeriksaan oleh admin.
                            </p>

                        </div>


                    <?php elseif (
                        $currentStatus === 'Diproses'
                    ): ?>

                        <div class="status-message-icon">

                            <i data-lucide="wrench"></i>

                        </div>


                        <div>

                            <strong>
                                Pengaduan sedang ditangani
                            </strong>

                            <p>
                                Petugas sedang melakukan
                                penanganan terhadap fasilitas
                                yang dilaporkan.
                            </p>

                        </div>


                    <?php elseif (
                        $currentStatus === 'Selesai'
                    ): ?>

                        <div class="status-message-icon">

                            <i data-lucide="circle-check"></i>

                        </div>


                        <div>

                            <strong>
                                Pengaduan telah selesai ditangani
                            </strong>

                            <p>
                                Penanganan terhadap laporan ini
                                telah diselesaikan oleh petugas.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>



                <!-- =================================================
                     TIMELINE
                ================================================= -->

                <div class="tracking-timeline">


                    <!-- MENUNGGU -->

                    <div
                        class="tracking-step
                        <?= $menungguSelesai ? 'done' : '' ?>
                        <?= $currentStatus === 'Menunggu'
                            ? 'current'
                            : ''
                        ?>"
                    >

                        <div class="tracking-step-circle">

                            <?php if ($menungguSelesai): ?>

                                <i data-lucide="check"></i>

                            <?php else: ?>

                                <span></span>

                            <?php endif; ?>

                        </div>


                        <div class="tracking-step-content">

                            <strong>
                                Menunggu
                            </strong>

                            <span>
                                Laporan diterima
                            </span>


                            <?php

                            $menungguHistory = null;

                            foreach (
                                $riwayatStatus
                                as $history
                            ) {

                                if (
                                    $history['status'] ===
                                    'Menunggu'
                                ) {

                                    $menungguHistory =
                                        $history;

                                    break;

                                }

                            }

                            ?>


                            <?php if (
                                $menungguHistory
                            ): ?>

                                <small>

                                    <?= formatTanggalIndonesia(
                                        $menungguHistory['created_at']
                                    ) ?>

                                    •
                                    <?= formatWaktu(
                                        $menungguHistory['created_at']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- LINE -->

                    <div
                        class="tracking-line
                        <?= $diprosesSelesai
                            ? 'done'
                            : ''
                        ?>"
                    ></div>



                    <!-- DIPROSES -->

                    <div
                        class="tracking-step
                        <?= $diprosesSelesai ? 'done' : '' ?>
                        <?= $currentStatus === 'Diproses'
                            ? 'current'
                            : ''
                        ?>"
                    >

                        <div class="tracking-step-circle">

                            <?php if (
                                $diprosesSelesai
                            ): ?>

                                <i data-lucide="check"></i>

                            <?php else: ?>

                                <span></span>

                            <?php endif; ?>

                        </div>


                        <div class="tracking-step-content">

                            <strong>
                                Diproses
                            </strong>

                            <span>
                                Sedang ditangani
                            </span>


                            <?php

                            $diprosesHistory = null;

                            foreach (
                                $riwayatStatus
                                as $history
                            ) {

                                if (
                                    $history['status'] ===
                                    'Diproses'
                                ) {

                                    $diprosesHistory =
                                        $history;

                                    break;

                                }

                            }

                            ?>


                            <?php if (
                                $diprosesHistory
                            ): ?>

                                <small>

                                    <?= formatTanggalIndonesia(
                                        $diprosesHistory['created_at']
                                    ) ?>

                                    •
                                    <?= formatWaktu(
                                        $diprosesHistory['created_at']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- LINE -->

                    <div
                        class="tracking-line
                        <?= $selesaiSelesai
                            ? 'done'
                            : ''
                        ?>"
                    ></div>



                    <!-- SELESAI -->

                    <div
                        class="tracking-step
                        <?= $selesaiSelesai ? 'done' : '' ?>
                        <?= $currentStatus === 'Selesai'
                            ? 'current'
                            : ''
                        ?>"
                    >

                        <div class="tracking-step-circle">

                            <?php if (
                                $selesaiSelesai
                            ): ?>

                                <i data-lucide="check"></i>

                            <?php else: ?>

                                <span></span>

                            <?php endif; ?>

                        </div>


                        <div class="tracking-step-content">

                            <strong>
                                Selesai
                            </strong>

                            <span>
                                Penanganan selesai
                            </span>


                            <?php

                            $selesaiHistory = null;

                            foreach (
                                $riwayatStatus
                                as $history
                            ) {

                                if (
                                    $history['status'] ===
                                    'Selesai'
                                ) {

                                    $selesaiHistory =
                                        $history;

                                    break;

                                }

                            }

                            ?>


                            <?php if (
                                $selesaiHistory
                            ): ?>

                                <small>

                                    <?= formatTanggalIndonesia(
                                        $selesaiHistory['created_at']
                                    ) ?>

                                    •
                                    <?= formatWaktu(
                                        $selesaiHistory['created_at']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </section>



            <!-- =================================================
                 DETAIL
            ================================================= -->

            <section class="tracking-detail-grid">


                <!-- =============================================
                     INFORMASI LAPORAN
                ============================================== -->

                <div class="tracking-detail-card">

                    <div class="detail-card-heading">

                        <div class="detail-heading-icon">

                            <i data-lucide="file-text"></i>

                        </div>


                        <div>

                            <h2>
                                Detail Pengaduan
                            </h2>

                            <p>
                                Informasi laporan yang Anda kirimkan.
                            </p>

                        </div>

                    </div>



                    <div class="detail-list">


                        <!-- KATEGORI -->

                        <div class="detail-row">

                            <div class="detail-row-icon">

                                <i data-lucide="layers"></i>

                            </div>


                            <div>

                                <span>
                                    Kategori
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $dataPengaduan['nama_kategori']
                                    ) ?>
                                </strong>

                            </div>

                        </div>



                        <!-- LOKASI -->

                        <div class="detail-row">

                            <div class="detail-row-icon">

                                <i data-lucide="map-pin"></i>

                            </div>


                            <div>

                                <span>
                                    Lokasi
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $dataPengaduan['lokasi']
                                    ) ?>
                                </strong>

                            </div>

                        </div>



                        <!-- TANGGAL -->

                        <div class="detail-row">

                            <div class="detail-row-icon">

                                <i data-lucide="calendar-days"></i>

                            </div>


                            <div>

                                <span>
                                    Tanggal Laporan
                                </span>

                                <strong>
                                    <?= formatTanggalIndonesia(
                                        $dataPengaduan['tanggal_lapor']
                                    ) ?>
                                </strong>

                            </div>

                        </div>



                        <!-- WAKTU -->

                        <div class="detail-row">

                            <div class="detail-row-icon">

                                <i data-lucide="clock-3"></i>

                            </div>


                            <div>

                                <span>
                                    Waktu Laporan
                                </span>

                                <strong>
                                    <?= formatWaktu(
                                        $dataPengaduan['tanggal_lapor']
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </div>



                    <!-- DESKRIPSI -->

                    <div class="detail-description">

                        <span>
                            Deskripsi Kerusakan
                        </span>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $dataPengaduan['deskripsi']
                                )
                            ) ?>
                        </p>

                    </div>

                </div>



                <!-- =============================================
                     FOTO
                ============================================== -->

                <div class="tracking-detail-card">

                    <div class="detail-card-heading">

                        <div class="detail-heading-icon">

                            <i data-lucide="image"></i>

                        </div>


                        <div>

                            <h2>
                                Foto Pengaduan
                            </h2>

                            <p>
                                Foto yang dilampirkan saat laporan.
                            </p>

                        </div>

                    </div>


                    <?php if (
                        !empty(
                            $dataPengaduan['foto']
                        )
                    ): ?>

                        <div class="tracking-photo">

                            <img
                                src="<?= e(uploadUrl($dataPengaduan['foto'], 'pengaduan', '')) ?>"
                                alt="Foto pengaduan"
                                id="complaintImage"
                            >

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
                                Tidak ada foto yang dilampirkan
                                pada laporan ini.
                            </span>

                        </div>

                    <?php endif; ?>

                </div>

            </section>



            <!-- =================================================
                 PENANGANAN
            ================================================= -->

            <?php if (
                $dataPenanganan
            ): ?>

                <section class="handling-card">

                    <div class="detail-card-heading">

                        <div class="detail-heading-icon">

                            <i data-lucide="wrench"></i>

                        </div>


                        <div>

                            <h2>
                                Informasi Penanganan
                            </h2>

                            <p>
                                Informasi penanganan dari petugas.
                            </p>

                        </div>

                    </div>


                    <div class="handling-grid">


                        <div class="handling-info">

                            <span>
                                Petugas
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $dataPenanganan['nama_petugas']
                                ) ?>
                            </strong>

                        </div>


                        <div class="handling-info">

                            <span>
                                Tanggal Penanganan
                            </span>

                            <strong>
                                <?= formatTanggalIndonesia(
                                    $dataPenanganan[
                                        'tanggal_penanganan'
                                    ]
                                ) ?>
                            </strong>

                        </div>


                        <div class="handling-info">

                            <span>
                                Waktu
                            </span>

                            <strong>
                                <?= formatWaktu(
                                    $dataPenanganan[
                                        'tanggal_penanganan'
                                    ]
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <?php if (
                        !empty(
                            $dataPenanganan['catatan']
                        )
                    ): ?>

                        <div class="handling-note">

                            <span>
                                Catatan Penanganan
                            </span>

                            <p>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $dataPenanganan['catatan']
                                    )
                                ) ?>

                            </p>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        !empty(
                            $dataPenanganan[
                                'foto_perbaikan'
                            ]
                        )
                    ): ?>

                        <div class="repair-photo-wrapper">

                            <span>
                                Foto Perbaikan
                            </span>


                            <div class="repair-photo">

                                <img
                                    src="<?= e(uploadUrl($dataPenanganan['foto_perbaikan'], 'perbaikan', '')) ?>"
                                    alt="Foto perbaikan"
                                >

                            </div>

                        </div>

                    <?php endif; ?>

                </section>

            <?php endif; ?>



            <!-- =================================================
                 FOOTER INFO
            ================================================= -->

            <section class="tracking-bottom">

                <div class="tracking-bottom-info">

                    <div class="bottom-icon">

                        <i data-lucide="shield-check"></i>

                    </div>


                    <div>

                        <strong>
                            Informasi Laporan
                        </strong>

                        <p>
                            Data laporan ditampilkan berdasarkan
                            nomor pengaduan yang terdaftar pada
                            sistem ADUIN.
                        </p>

                    </div>

                </div>


                <a
                    href="tracking.php"
                    class="new-tracking-btn"
                >

                    <i data-lucide="search"></i>

                    Lacak Laporan Lain

                </a>

            </section>


        <?php endif; ?>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - TRACKING PAGE
========================================================= */

.tracking-page {

    min-height: calc(100vh - 76px);

    padding: 44px 24px 70px;

    background:
        radial-gradient(
            circle at 8% 0%,
            rgba(37, 99, 235, .07),
            transparent 28%
        ),
        radial-gradient(
            circle at 92% 18%,
            rgba(59, 130, 246, .05),
            transparent 25%
        ),
        #f8fafc;
}

.tracking-shell {

    width: min(1000px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.tracking-header {

    margin-bottom: 28px;

}

.tracking-heading {

    display: flex;

    align-items: flex-start;

    gap: 15px;

}

.tracking-icon {

    width: 52px;
    height: 52px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 14px;

}

.tracking-icon svg {

    width: 25px;
    height: 25px;

}

.tracking-eyebrow {

    display: block;

    margin: 1px 0 4px;

    color: #2563eb;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: .08em;

}

.tracking-heading h1 {

    margin: 0;

    color: #0f172a;

    font-size: 32px;

    line-height: 1.15;

    font-weight: 800;

    letter-spacing: -.8px;

}

.tracking-heading p {

    max-width: 650px;

    margin: 8px 0 0;

    color: #64748b;

    font-size: 13px;

    line-height: 1.6;

}


/* =========================================================
   SEARCH CARD
========================================================= */

.tracking-search-card {

    padding: 24px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 17px;

    box-shadow:
        0 8px 30px rgba(15, 23, 42, .045);

    margin-bottom: 22px;

}

.search-card-heading {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 19px;

}

.search-card-icon {

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 11px;

}

.search-card-icon svg {

    width: 19px;

}

.search-card-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 15px;

    font-weight: 800;

}

.search-card-heading p {

    margin: 4px 0 0;

    color: #94a3b8;

    font-size: 11px;

}


.tracking-form {

    display: grid;

    grid-template-columns: minmax(0, 1fr) 165px;

    gap: 10px;

}

.tracking-input-wrap {

    position: relative;

}

.tracking-input-wrap > svg {

    position: absolute;

    top: 50%;
    left: 14px;

    width: 17px;
    height: 17px;

    color: #94a3b8;

    transform: translateY(-50%);

    pointer-events: none;

}

.tracking-input-wrap input {

    width: 100%;
    height: 46px;

    box-sizing: border-box;

    padding: 0 15px 0 42px;

    color: #0f172a;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 11px;

    outline: none;

    font-family: inherit;

    font-size: 12px;

    font-weight: 600;

    letter-spacing: .2px;

    transition: .2s ease;

}

.tracking-input-wrap input::placeholder {

    color: #a8b3c2;

    font-weight: 400;

}

.tracking-input-wrap input:focus {

    border-color: #60a5fa;

    box-shadow:
        0 0 0 4px rgba(37, 99, 235, .08);

}

.tracking-submit {

    height: 46px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 8px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 11px;

    font-family: inherit;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;

    box-shadow:
        0 8px 20px rgba(37, 99, 235, .18);

    transition: .25s ease;

}

.tracking-submit:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-2px);

    box-shadow:
        0 12px 25px rgba(37, 99, 235, .24);

}

.tracking-submit:active {

    transform: translateY(0) scale(.98);

}

.tracking-submit svg {

    width: 17px;

}


.search-hint {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-top: 11px;

    color: #94a3b8;

    font-size: 10px;

}

.search-hint svg {

    width: 14px;

    flex-shrink: 0;

}

.search-hint strong {

    color: #64748b;

}


/* =========================================================
   ERROR
========================================================= */

.tracking-error {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    margin-bottom: 22px;

    padding: 15px 16px;

    color: #991b1b;

    background: #fef2f2;

    border: 1px solid #fecaca;

    border-radius: 13px;

}

.tracking-error-icon {

    width: 36px;
    height: 36px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    background: #fee2e2;

    border-radius: 9px;

}

.tracking-error-icon svg {

    width: 19px;

}

.tracking-error strong {

    display: block;

    font-size: 13px;

}

.tracking-error p {

    margin: 4px 0 0;

    font-size: 11px;

    line-height: 1.5;

}


/* =========================================================
   RESULT
========================================================= */

.tracking-result {

    overflow: hidden;

    margin-bottom: 20px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 17px;

    box-shadow:
        0 8px 30px rgba(15, 23, 42, .045);

}

.result-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 21px 23px;

}

.result-label {

    display: block;

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .05em;

}

.result-top h2 {

    margin: 0;

    color: #0f172a;

    font-size: 17px;

    font-weight: 800;

    letter-spacing: .3px;

}

.result-status {

    min-height: 34px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 0 12px;

    border-radius: 9px;

    font-size: 10px;

    font-weight: 800;

}

.result-status svg {

    width: 15px;
    height: 15px;

}

.result-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}

.result-status.processing {

    color: #6d28d9;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}

.result-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}


/* =========================================================
   STATUS MESSAGE
========================================================= */

.status-message {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 0 23px;

    padding: 14px;

    background: #f8fafc;

    border: 1px solid #eef2f7;

    border-radius: 12px;

}

.status-message-icon {

    width: 38px;
    height: 38px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 10px;

}

.status-message-icon svg {

    width: 18px;

}

.status-message strong {

    display: block;

    color: #334155;

    font-size: 11px;

}

.status-message p {

    margin: 4px 0 0;

    color: #94a3b8;

    font-size: 10px;

    line-height: 1.5;

}


/* =========================================================
   TIMELINE
========================================================= */

.tracking-timeline {

    display: flex;

    align-items: flex-start;

    padding: 28px 35px 30px;

}

.tracking-step {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    min-width: 145px;

}

.tracking-step-circle {

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #94a3b8;

    background: #fff;

    border: 1px solid #cbd5e1;

    border-radius: 50%;

    transition: .25s ease;

}

.tracking-step-circle svg {

    width: 14px;

}

.tracking-step-circle span {

    width: 7px;
    height: 7px;

    background: #cbd5e1;

    border-radius: 50%;

}

.tracking-step.done
.tracking-step-circle {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

}

.tracking-step.current
.tracking-step-circle {

    box-shadow:
        0 0 0 5px rgba(37, 99, 235, .10);

}

.tracking-step-content {

    padding-top: 1px;

}

.tracking-step-content strong {

    display: block;

    color: #475569;

    font-size: 10px;

    font-weight: 800;

}

.tracking-step.done
.tracking-step-content strong {

    color: #1d4ed8;

}

.tracking-step-content > span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 8px;

    white-space: nowrap;

}

.tracking-step-content small {

    display: block;

    margin-top: 5px;

    color: #94a3b8;

    font-size: 7px;

    white-space: nowrap;

}

.tracking-line {

    flex: 1;

    height: 2px;

    min-width: 35px;

    margin: 14px 9px 0;

    background: #e2e8f0;

}

.tracking-line.done {

    background: #93c5fd;

}


/* =========================================================
   DETAIL GRID
========================================================= */

.tracking-detail-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        330px;

    gap: 20px;

    margin-bottom: 20px;

}

.tracking-detail-card {

    min-width: 0;

    padding: 22px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 17px;

    box-shadow:
        0 8px 30px rgba(15, 23, 42, .045);

}

.detail-card-heading {

    display: flex;

    align-items: center;

    gap: 11px;

    margin-bottom: 20px;

}

.detail-heading-icon {

    width: 39px;
    height: 39px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 10px;

}

.detail-heading-icon svg {

    width: 18px;

}

.detail-card-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

}

.detail-card-heading p {

    margin: 4px 0 0;

    color: #94a3b8;

    font-size: 10px;

}


/* =========================================================
   DETAIL LIST
========================================================= */

.detail-list {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 16px;

}

.detail-row {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    min-width: 0;

}

.detail-row-icon {

    width: 29px;
    height: 29px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 8px;

}

.detail-row-icon svg {

    width: 14px;

}

.detail-row > div:last-child {

    min-width: 0;

}

.detail-row span {

    display: block;

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 8px;

}

.detail-row strong {

    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 10px;

    line-height: 1.45;

    text-overflow: ellipsis;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.detail-description {

    margin-top: 20px;

    padding-top: 18px;

    border-top: 1px solid #eef2f7;

}

.detail-description > span {

    display: block;

    margin-bottom: 7px;

    color: #94a3b8;

    font-size: 9px;

    font-weight: 700;

}

.detail-description p {

    margin: 0;

    color: #475569;

    font-size: 10px;

    line-height: 1.7;

}


/* =========================================================
   PHOTO
========================================================= */

.tracking-photo {

    width: 100%;

    height: 220px;

    overflow: hidden;

    background: #f1f5f9;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

}

.tracking-photo img {

    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;

    transition: transform .45s ease;

}

.tracking-photo:hover img {

    transform: scale(1.035);

}

.no-photo {

    min-height: 220px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    text-align: center;

    padding: 20px;

    box-sizing: border-box;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 12px;

}

.no-photo-icon {

    width: 46px;
    height: 46px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 10px;

    color: #94a3b8;

    background: #f1f5f9;

    border-radius: 12px;

}

.no-photo-icon svg {

    width: 21px;

}

.no-photo strong {

    color: #475569;

    font-size: 11px;

}

.no-photo span {

    max-width: 190px;

    margin-top: 4px;

    color: #94a3b8;

    font-size: 9px;

    line-height: 1.5;

}


/* =========================================================
   HANDLING
========================================================= */

.handling-card {

    margin-bottom: 20px;

    padding: 22px;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 17px;

    box-shadow:
        0 8px 30px rgba(15, 23, 42, .045);

}

.handling-grid {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 15px;

}

.handling-info {

    padding: 13px;

    background: #f8fafc;

    border: 1px solid #eef2f7;

    border-radius: 10px;

}

.handling-info span {

    display: block;

    margin-bottom: 5px;

    color: #94a3b8;

    font-size: 8px;

}

.handling-info strong {

    display: block;

    color: #334155;

    font-size: 10px;

}

.handling-note {

    margin-top: 15px;

    padding: 14px;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 11px;

}

.handling-note > span,
.repair-photo-wrapper > span {

    display: block;

    margin-bottom: 6px;

    color: #64748b;

    font-size: 9px;

    font-weight: 700;

}

.handling-note p {

    margin: 0;

    color: #475569;

    font-size: 10px;

    line-height: 1.65;

}

.repair-photo-wrapper {

    margin-top: 16px;

}

.repair-photo {

    width: 180px;
    height: 120px;

    overflow: hidden;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

}

.repair-photo img {

    width: 100%;
    height: 100%;

    display: block;

    object-fit: cover;

}


/* =========================================================
   BOTTOM
========================================================= */

.tracking-bottom {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 18px 20px;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 14px;

}

.tracking-bottom-info {

    display: flex;

    align-items: center;

    gap: 11px;

}

.bottom-icon {

    width: 37px;
    height: 37px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #dbeafe;

    border-radius: 10px;

}

.bottom-icon svg {

    width: 18px;

}

.tracking-bottom-info strong {

    display: block;

    color: #1e3a8a;

    font-size: 10px;

}

.tracking-bottom-info p {

    max-width: 540px;

    margin: 4px 0 0;

    color: #64748b;

    font-size: 9px;

    line-height: 1.5;

}

.new-tracking-btn {

    min-height: 38px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    flex-shrink: 0;

    padding: 0 13px;

    color: #2563eb;

    background: #fff;

    border: 1px solid #bfdbfe;

    border-radius: 9px;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;

    transition: .2s ease;

}

.new-tracking-btn:hover {

    color: #fff;

    background: #2563eb;

    border-color: #2563eb;

}

.new-tracking-btn svg {

    width: 14px;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 900px) {

    .tracking-page {

        padding: 35px 18px 60px;

    }

    .tracking-detail-grid {

        grid-template-columns:
            minmax(0, 1fr)
            290px;

    }

    .tracking-step {

        min-width: 125px;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .tracking-page {

        padding: 28px 14px 50px;

    }

    .tracking-heading h1 {

        font-size: 27px;

    }

    .tracking-heading p {

        font-size: 11px;

    }

    .tracking-search-card {

        padding: 18px;

    }

    .tracking-form {

        grid-template-columns: 1fr;

    }

    .tracking-submit {

        width: 100%;

    }

    .result-top {

        align-items: flex-start;

        flex-direction: column;

        padding: 18px;

    }

    .status-message {

        margin: 0 18px;

    }

    .tracking-timeline {

        overflow-x: auto;

        padding: 25px 18px;

    }

    .tracking-detail-grid {

        grid-template-columns: 1fr;

    }

    .tracking-detail-card {

        padding: 18px;

    }

    .handling-card {

        padding: 18px;

    }

    .handling-grid {

        grid-template-columns: 1fr;

    }

    .tracking-bottom {

        align-items: stretch;

        flex-direction: column;

    }

    .new-tracking-btn {

        width: 100%;

        box-sizing: border-box;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 430px) {

    .tracking-page {

        padding: 24px 12px 45px;

    }

    .tracking-heading {

        gap: 11px;

    }

    .tracking-icon {

        width: 46px;
        height: 46px;

        border-radius: 12px;

    }

    .tracking-icon svg {

        width: 21px;

    }

    .tracking-heading h1 {

        font-size: 23px;

    }

    .tracking-search-card {

        border-radius: 14px;

    }

    .search-card-heading {

        align-items: flex-start;

    }

    .tracking-result {

        border-radius: 14px;

    }

    .tracking-detail-card,

    .handling-card {

        border-radius: 14px;

    }

    .detail-list {

        grid-template-columns: 1fr;

    }

    .tracking-photo,

    .no-photo {

        height: 190px;

        min-height: 190px;

    }

    .tracking-bottom {

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

                lenis.raf(time);

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
                ".tracking-heading",
                {
                    y: 20,
                    opacity: 0,
                    duration: .65,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".tracking-search-card",
                {
                    y: 20,
                    opacity: 0,
                    duration: .6,
                    delay: .08,
                    ease: "power3.out"
                }
            );


            <?php if ($dataPengaduan): ?>

            gsap.from(
                ".tracking-result",
                {
                    y: 22,
                    opacity: 0,
                    duration: .65,
                    delay: .15,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".tracking-detail-card",
                {
                    y: 20,
                    opacity: 0,
                    duration: .55,
                    stagger: .08,
                    delay: .25,
                    ease: "power3.out"
                }
            );


            <?php if ($dataPenanganan): ?>

            gsap.from(
                ".handling-card",
                {
                    y: 20,
                    opacity: 0,
                    duration: .55,
                    delay: .35,
                    ease: "power3.out"
                }
            );

            <?php endif; ?>


            gsap.from(
                ".tracking-bottom",
                {
                    y: 15,
                    opacity: 0,
                    duration: .5,
                    delay: .4,
                    ease: "power2.out"
                }
            );

            <?php endif; ?>



            <?php if ($error !== ''): ?>

            gsap.from(
                ".tracking-error",
                {
                    y: 15,
                    opacity: 0,
                    duration: .5,
                    delay: .1,
                    ease: "power3.out"
                }
            );

            <?php endif; ?>

        }



        /* =====================================================
           SEARCH FORM
        ===================================================== */

        const form =
            document.getElementById(
                "trackingForm"
            );

        const submitButton =
            document.getElementById(
                "trackingSubmit"
            );


        if (
            form &&
            submitButton
        ) {

            form.addEventListener(
                "submit",
                function () {

                    submitButton.disabled =
                        true;

                    submitButton.innerHTML = `
                        <i data-lucide="loader-circle"></i>
                        <span>Mencari...</span>
                    `;


                    if (window.lucide) {

                        lucide.createIcons();

                    }

                }
            );

        }



        /* =====================================================
           AUTO UPPERCASE NOMOR
        ===================================================== */

        const nomor =
            document.getElementById(
                "nomor"
            );


        if (nomor) {

            nomor.addEventListener(
                "input",
                function () {

                    this.value =
                        this.value
                            .toUpperCase()
                            .replace(
                                /\s+/g,
                                ""
                            );

                }
            );

        }



        /* =====================================================
           NAVBAR SCROLL
        ===================================================== */

        const navbar =
            document.querySelector(
                ".navbar"
            );


        if (navbar) {

            function updateNavbar() {

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

    }
);

</script>


</body>

</html>
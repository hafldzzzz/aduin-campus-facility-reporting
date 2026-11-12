<?php
require_once "../config/database.php";
requireRole('petugas');

$id_user = (int) $_SESSION['id_user'];
$id_pengaduan = validId(getString('id'));
if ($id_pengaduan <= 0) {
    header('Location: tugas.php');
    exit;
}

$detailSql = '
    SELECT p.id_pengaduan, p.no_laporan, p.lokasi, p.deskripsi, p.foto,
        p.status, p.tanggal_lapor, p.id_petugas, k.nama_kategori,
        u.id_user AS id_pelapor, u.nama AS nama_pelapor,
        u.email AS email_pelapor, u.no_wa AS wa_pelapor,
        u.fakultas AS fakultas_pelapor, pt.nama AS nama_petugas
    FROM pengaduan p
    INNER JOIN kategori k ON p.id_kategori = k.id_kategori
    INNER JOIN users u ON p.id_user = u.id_user
    LEFT JOIN users pt ON p.id_petugas = pt.id_user
    WHERE p.id_pengaduan = ? AND p.id_petugas = ? LIMIT 1';
$pengaduan = dbOne($conn, $detailSql, 'ii', [$id_pengaduan, $id_user]);
if (!$pengaduan) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke pengaduan ini.');
}

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $statusBaru = trim(postString('status'));
    $catatan = trim(postString('catatan'));
    $fotoPerbaikan = null;
    if (!in_array($statusBaru, ['Menunggu', 'Diproses', 'Selesai'], true)) {
        $error = 'Status yang dipilih tidak valid.';
    } elseif (strlen($catatan) > 10000) {
        $error = 'Catatan terlalu panjang. Maksimal 10000 karakter.';
    }

    if ($error === '') {
        try {
            $fotoPerbaikan = storeImageUpload($_FILES['foto_perbaikan'] ?? null, 'perbaikan');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            safeLog('repair_upload_failed', ['pengaduan_id' => $id_pengaduan]);
            $error = 'Foto perbaikan tidak dapat disimpan.';
        }
    }

    if ($error === '') {
        try {
            mysqli_begin_transaction($conn);
            // Recheck ownership while locked: an admin may have reassigned the report.
            $lockedReport = dbOne($conn, 'SELECT status, id_petugas FROM pengaduan WHERE id_pengaduan = ? FOR UPDATE', 'i', [$id_pengaduan]);
            if (!$lockedReport || (int) $lockedReport['id_petugas'] !== $id_user) {
                throw new InvalidArgumentException('Pengaduan sudah tidak ditugaskan kepada Anda.');
            }
            $statusLama = $lockedReport['status'];
            dbStatement($conn, 'UPDATE pengaduan SET status = ? WHERE id_pengaduan = ? AND id_petugas = ?', 'sii', [$statusBaru, $id_pengaduan, $id_user]);

            if ($statusBaru !== $statusLama) {
                $defaultNotes = [
                    'Menunggu' => 'Status pengaduan diperbarui.',
                    'Diproses' => 'Pengaduan mulai ditangani oleh petugas.',
                    'Selesai' => 'Penanganan pengaduan telah selesai.'
                ];
                $keterangan = $catatan !== '' ? $catatan : $defaultNotes[$statusBaru];
                dbStatement($conn, 'INSERT INTO riwayat_status (id_pengaduan, status, keterangan) VALUES (?, ?, ?)', 'iss', [$id_pengaduan, $statusBaru, $keterangan]);
            }

            if ($catatan !== '' || $fotoPerbaikan !== null || in_array($statusBaru, ['Diproses', 'Selesai'], true)) {
                $existingHandling = dbOne($conn, 'SELECT id_penanganan, catatan, foto_perbaikan FROM penanganan WHERE id_pengaduan = ? AND id_petugas = ? ORDER BY id_penanganan DESC LIMIT 1 FOR UPDATE', 'ii', [$id_pengaduan, $id_user]);
                if ($existingHandling) {
                    $catatanSimpan = $catatan !== '' ? $catatan : $existingHandling['catatan'];
                    $fotoSimpan = $fotoPerbaikan !== null ? $fotoPerbaikan : $existingHandling['foto_perbaikan'];
                    dbStatement($conn, 'UPDATE penanganan SET catatan = ?, foto_perbaikan = ?, tanggal_penanganan = CURRENT_TIMESTAMP WHERE id_penanganan = ? AND id_pengaduan = ? AND id_petugas = ?', 'ssiii', [$catatanSimpan, $fotoSimpan, $existingHandling['id_penanganan'], $id_pengaduan, $id_user]);
                } else {
                    dbStatement($conn, 'INSERT INTO penanganan (id_pengaduan, id_petugas, catatan, foto_perbaikan) VALUES (?, ?, ?, ?)', 'iiss', [$id_pengaduan, $id_user, $catatan, $fotoPerbaikan]);
                }
            }
            mysqli_commit($conn);
            header('Location: detail-tugas.php?id=' . $id_pengaduan . '&success=1');
            exit;
        } catch (Throwable $exception) {
            mysqli_rollback($conn);
            // Remove only the file created by this failed request, never an old image.
            if ($fotoPerbaikan !== null) {
                $rollbackFile = dirname(__DIR__) . '/uploads/perbaikan/' . basename($fotoPerbaikan);
                if (is_file($rollbackFile)) {
                    unlink($rollbackFile);
                }
            }
            if ($exception instanceof InvalidArgumentException) {
                $error = $exception->getMessage();
            } else {
                safeLog('task_update_failed', ['pengaduan_id' => $id_pengaduan]);
                $error = 'Pengaduan gagal diperbarui. Silakan coba lagi.';
            }
        }
    }
}

$pengaduan = dbOne($conn, $detailSql, 'ii', [$id_pengaduan, $id_user]);
if (!$pengaduan) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke pengaduan ini.');
}
$resultHistory = dbQuery($conn, 'SELECT id_riwayat, status, keterangan, created_at FROM riwayat_status WHERE id_pengaduan = ? ORDER BY created_at ASC, id_riwayat ASC', 'i', [$id_pengaduan]);
$riwayatStatus = mysqli_fetch_all($resultHistory, MYSQLI_ASSOC);
$penanganan = dbOne($conn, '
    SELECT ph.id_penanganan, ph.catatan, ph.foto_perbaikan, ph.tanggal_penanganan, u.nama AS nama_petugas
    FROM penanganan ph INNER JOIN users u ON ph.id_petugas = u.id_user
    WHERE ph.id_pengaduan = ? ORDER BY ph.tanggal_penanganan DESC, ph.id_penanganan DESC LIMIT 1', 'i', [$id_pengaduan]);
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


    $timestamp =
        strtotime($tanggal);


    return date('d', $timestamp)
        . ' '
        . $bulan[
            (int)date(
                'm',
                $timestamp
            )
        ]
        . ' '
        . date(
            'Y',
            $timestamp
        );
}


function formatWaktu($tanggal)
{
    if (!$tanggal) {
        return '-';
    }


    return date(
        'H:i',
        strtotime($tanggal)
    ) . ' WIB';
}


function formatTanggalWaktu($tanggal)
{
    if (!$tanggal) {
        return '-';
    }


    return formatTanggal($tanggal)
        . ' · '
        . formatWaktu($tanggal);
}


/* =========================================================
   SUCCESS
========================================================= */

$showSuccess =
    isset($_GET['success'])
    && $_GET['success'] === '1';

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
        Detail Tugas | ADUIN
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


<main class="staff-detail-page">

    <div class="staff-detail-shell">


        <!-- =====================================================
             HEADER
        ===================================================== -->

        <section class="detail-header">


            <div class="detail-header-left">

                <a
                    href="tugas.php"
                    class="back-btn"
                >

                    <i data-lucide="arrow-left"></i>

                    <span>
                        Kembali ke Tugas
                    </span>

                </a>


                <div class="detail-heading">

                    <span>
                        DETAIL TUGAS PENGADUAN
                    </span>

                    <h1>
                        <?= htmlspecialchars(
                            $pengaduan['no_laporan']
                        ) ?>
                    </h1>

                    <p>
                        Detail laporan fasilitas yang
                        ditugaskan kepada Anda.
                    </p>

                </div>

            </div>


            <div
                class="
                    detail-current-status
                    <?= statusClass(
                        $pengaduan['status']
                    ) ?>
                "
            >

                <i
                    data-lucide="<?= statusIcon(
                        $pengaduan['status']
                    ) ?>"
                ></i>

                <span>
                    <?= htmlspecialchars(
                        $pengaduan['status']
                    ) ?>
                </span>

            </div>


        </section>



        <!-- =====================================================
             ALERT
        ===================================================== -->

        <?php if ($showSuccess): ?>

            <div class="detail-alert success">

                <div class="detail-alert-icon">

                    <i data-lucide="circle-check"></i>

                </div>


                <div>

                    <strong>
                        Perubahan berhasil disimpan
                    </strong>

                    <p>
                        Status dan data penanganan
                        pengaduan telah diperbarui.
                    </p>

                </div>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="detail-alert error">

                <div class="detail-alert-icon">

                    <i data-lucide="circle-alert"></i>

                </div>


                <div>

                    <strong>
                        Terjadi kesalahan
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             MAIN GRID
        ===================================================== -->

        <section class="detail-grid">


            <!-- =================================================
                 LEFT
            ================================================= -->

            <div class="detail-main">


                <!-- =============================================
                     INFORMASI LAPORAN
                ============================================== -->

                <section class="detail-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                INFORMASI LAPORAN
                            </span>

                            <h2>
                                Detail Pengaduan
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="file-text"></i>

                        </div>

                    </div>



                    <div class="report-detail-grid">


                        <div class="report-detail-item">

                            <span>
                                Nomor Laporan
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $pengaduan['no_laporan']
                                ) ?>
                            </strong>

                        </div>


                        <div class="report-detail-item">

                            <span>
                                Kategori
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $pengaduan['nama_kategori']
                                ) ?>
                            </strong>

                        </div>


                        <div class="report-detail-item full">

                            <span>
                                Lokasi
                            </span>

                            <div class="detail-location">

                                <i data-lucide="map-pin"></i>

                                <strong>
                                    <?= htmlspecialchars(
                                        $pengaduan['lokasi']
                                    ) ?>
                                </strong>

                            </div>

                        </div>


                        <div class="report-detail-item">

                            <span>
                                Tanggal Laporan
                            </span>

                            <strong>
                                <?= formatTanggal(
                                    $pengaduan['tanggal_lapor']
                                ) ?>
                            </strong>

                        </div>


                        <div class="report-detail-item">

                            <span>
                                Waktu Laporan
                            </span>

                            <strong>
                                <?= formatWaktu(
                                    $pengaduan['tanggal_lapor']
                                ) ?>
                            </strong>

                        </div>


                    </div>


                </section>



                <!-- =============================================
                     DESKRIPSI
                ============================================== -->

                <section class="detail-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                LAPORAN KERUSAKAN
                            </span>

                            <h2>
                                Deskripsi
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="align-left"></i>

                        </div>

                    </div>


                    <div class="description-box">

                        <?= nl2br(
                            htmlspecialchars(
                                $pengaduan['deskripsi']
                            )
                        ) ?>

                    </div>


                </section>



                <!-- =============================================
                     FOTO KERUSAKAN
                ============================================== -->

                <section class="detail-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                DOKUMENTASI
                            </span>

                            <h2>
                                Foto Kerusakan
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="image"></i>

                        </div>

                    </div>


                    <?php if (
                        !empty(
                            $pengaduan['foto']
                        )
                    ): ?>

                        <div class="report-image">

                            <img
                                src="<?= uploadUrl($pengaduan['foto'], 'pengaduan') ?>"
                                alt="Foto kerusakan"
                            >

                        </div>

                    <?php else: ?>

                        <div class="no-image">

                            <div class="no-image-icon">

                                <i data-lucide="image-off"></i>

                            </div>

                            <strong>
                                Tidak ada foto
                            </strong>

                            <span>
                                Pelapor tidak menyertakan
                                foto kerusakan.
                            </span>

                        </div>

                    <?php endif; ?>


                </section>



                <!-- =============================================
                     RIWAYAT STATUS
                ============================================== -->

                <section class="detail-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                AKTIVITAS
                            </span>

                            <h2>
                                Riwayat Status
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="history"></i>

                        </div>

                    </div>



                    <?php if (
                        count(
                            $riwayatStatus
                        ) > 0
                    ): ?>


                        <div class="status-history">


                            <?php foreach (
                                $riwayatStatus
                                as $index => $history
                            ): ?>


                                <div class="history-item">


                                    <div class="history-marker">

                                        <div
                                            class="
                                                history-marker-circle
                                                <?= statusClass(
                                                    $history['status']
                                                ) ?>
                                            "
                                        >

                                            <i
                                                data-lucide="<?= statusIcon(
                                                    $history['status']
                                                ) ?>"
                                            ></i>

                                        </div>


                                        <?php if (
                                            $index <
                                            count(
                                                $riwayatStatus
                                            ) - 1
                                        ): ?>

                                            <div class="history-line"></div>

                                        <?php endif; ?>

                                    </div>



                                    <div class="history-content">

                                        <div class="history-content-top">

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $history['status']
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= formatTanggalWaktu(
                                                    $history['created_at']
                                                ) ?>
                                            </span>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $history['keterangan']
                                            )
                                        ): ?>

                                            <p>
                                                <?= nl2br(
                                                    htmlspecialchars(
                                                        $history['keterangan']
                                                    )
                                                ) ?>
                                            </p>

                                        <?php endif; ?>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="history-empty">

                            <i data-lucide="history"></i>

                            <span>
                                Belum ada riwayat status.
                            </span>

                        </div>


                    <?php endif; ?>


                </section>


            </div>



            <!-- =================================================
                 RIGHT SIDEBAR
            ================================================= -->

            <aside class="detail-sidebar">


                <!-- =============================================
                     UPDATE STATUS
                ============================================== -->

                <section class="detail-card update-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                PENANGANAN
                            </span>

                            <h2>
                                Perbarui Tugas
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="wrench"></i>

                        </div>

                    </div>


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="updateForm"
                    >
                        <?= csrfField() ?>


                        <div class="form-group">

                            <label for="status">

                                Status Pengaduan

                                <span>
                                    *
                                </span>

                            </label>


                            <div class="select-wrap">

                                <select
                                    name="status"
                                    id="status"
                                    required
                                >

                                    <option
                                        value="Menunggu"
                                        <?= $pengaduan['status'] === 'Menunggu'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Menunggu
                                    </option>

                                    <option
                                        value="Diproses"
                                        <?= $pengaduan['status'] === 'Diproses'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Diproses
                                    </option>

                                    <option
                                        value="Selesai"
                                        <?= $pengaduan['status'] === 'Selesai'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Selesai
                                    </option>

                                </select>


                                <i data-lucide="chevron-down"></i>

                            </div>

                        </div>



                        <div class="status-help">

                            <div class="status-help-icon">

                                <i data-lucide="info"></i>

                            </div>


                            <p id="statusHelp">

                                <?php if (
                                    $pengaduan['status']
                                    === 'Menunggu'
                                ): ?>

                                    Pengaduan belum mulai
                                    ditangani.

                                <?php elseif (
                                    $pengaduan['status']
                                    === 'Diproses'
                                ): ?>

                                    Pengaduan sedang dalam
                                    proses penanganan.

                                <?php else: ?>

                                    Pengaduan telah selesai
                                    ditangani.

                                <?php endif; ?>

                            </p>

                        </div>



                        <div class="form-group">

                            <label for="catatan">

                                Catatan Penanganan

                            </label>


                            <textarea
                                name="catatan"
                                id="catatan"
                                rows="5"
                                placeholder="Tuliskan hasil pengecekan atau penanganan..."
                            ><?= htmlspecialchars(
                                $penanganan['catatan']
                                ?? ''
                            ) ?></textarea>


                            <small>
                                Catatan akan tercatat dalam
                                riwayat penanganan.
                            </small>

                        </div>



                        <div class="form-group">

                            <label for="foto_perbaikan">

                                Foto Perbaikan

                            </label>


                            <div
                                class="upload-box"
                                id="uploadBox"
                            >

                                <input
                                    type="file"
                                    name="foto_perbaikan"
                                    id="foto_perbaikan"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >


                                <div
                                    class="upload-content"
                                    id="uploadContent"
                                >

                                    <div class="upload-icon">

                                        <i data-lucide="upload-cloud"></i>

                                    </div>


                                    <strong>
                                        Pilih foto perbaikan
                                    </strong>


                                    <span>
                                        JPG, PNG, WEBP · Maks. 5 MB
                                    </span>

                                </div>


                                <div
                                    class="upload-preview"
                                    id="uploadPreview"
                                >

                                    <img
                                        src=""
                                        alt="Preview foto"
                                        id="previewImage"
                                    >

                                    <div>

                                        <strong
                                            id="previewName"
                                        >
                                        </strong>

                                        <span>
                                            Foto baru
                                        </span>

                                    </div>


                                    <button
                                        type="button"
                                        id="removeImage"
                                        aria-label="Hapus foto"
                                    >

                                        <i data-lucide="x"></i>

                                    </button>

                                </div>

                            </div>


                            <?php if (
                                !empty(
                                    $penanganan[
                                        'foto_perbaikan'
                                    ]
                                )
                            ): ?>

                                <div class="existing-photo">

                                    <i data-lucide="image"></i>

                                    <span>
                                        Sudah ada foto perbaikan.
                                        Upload foto baru untuk
                                        menggantinya.
                                    </span>

                                </div>

                            <?php endif; ?>


                        </div>



                        <button
                            type="submit"
                            class="save-btn"
                            id="saveButton"
                        >

                            <i data-lucide="save"></i>

                            <span>
                                Simpan Perubahan
                            </span>

                        </button>


                    </form>


                </section>



                <!-- =============================================
                     DATA PELAPOR
                ============================================== -->

                <section class="detail-card reporter-card">


                    <div class="detail-card-heading">

                        <div>

                            <span>
                                PELAPOR
                            </span>

                            <h2>
                                Informasi Pelapor
                            </h2>

                        </div>


                        <div class="detail-heading-icon">

                            <i data-lucide="user-round"></i>

                        </div>

                    </div>


                    <div class="reporter-profile">

                        <div class="reporter-avatar">

                            <i data-lucide="user-round"></i>

                        </div>


                        <div>

                            <strong>
                                <?= htmlspecialchars(
                                    $pengaduan['nama_pelapor']
                                ) ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $pengaduan[
                                        'fakultas_pelapor'
                                    ]
                                )
                            ): ?>

                                <span>
                                    <?= htmlspecialchars(
                                        $pengaduan[
                                            'fakultas_pelapor'
                                        ]
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="reporter-details">


                        <?php if (
                            !empty(
                                $pengaduan[
                                    'email_pelapor'
                                ]
                            )
                        ): ?>

                            <div>

                                <i data-lucide="mail"></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $pengaduan[
                                            'email_pelapor'
                                        ]
                                    ) ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $pengaduan[
                                    'wa_pelapor'
                                ]
                            )
                        ): ?>

                            <div>

                                <i data-lucide="phone"></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $pengaduan[
                                            'wa_pelapor'
                                        ]
                                    ) ?>
                                </span>

                            </div>

                        <?php endif; ?>


                    </div>


                </section>



                <!-- =============================================
                     PENANGANAN TERAKHIR
                ============================================== -->

                <?php if ($penanganan): ?>

                    <section class="detail-card handling-card">


                        <div class="detail-card-heading">

                            <div>

                                <span>
                                    PENANGANAN
                                </span>

                                <h2>
                                    Data Penanganan
                                </h2>

                            </div>


                            <div class="detail-heading-icon">

                                <i data-lucide="clipboard-check"></i>

                            </div>

                        </div>


                        <div class="handling-person">

                            <div class="handling-avatar">

                                <i data-lucide="user-round-check"></i>

                            </div>


                            <div>

                                <span>
                                    Ditangani oleh
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $penanganan[
                                            'nama_petugas'
                                        ]
                                    ) ?>
                                </strong>

                            </div>

                        </div>


                        <?php if (
                            !empty(
                                $penanganan['catatan']
                            )
                        ): ?>

                            <div class="handling-note">

                                <span>
                                    Catatan
                                </span>

                                <p>
                                    <?= nl2br(
                                        htmlspecialchars(
                                            $penanganan[
                                                'catatan'
                                            ]
                                        )
                                    ) ?>
                                </p>

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

                                <span>
                                    Foto Perbaikan
                                </span>


                                <img
                                    src="<?= uploadUrl($penanganan[ 'foto_perbaikan' ], 'perbaikan') ?>"
                                    alt="Foto perbaikan"
                                >

                            </div>

                        <?php endif; ?>


                        <div class="handling-date">

                            <i data-lucide="calendar-clock"></i>

                            <span>
                                <?= formatTanggalWaktu(
                                    $penanganan[
                                        'tanggal_penanganan'
                                    ]
                                ) ?>
                            </span>

                        </div>


                    </section>

                <?php endif; ?>


            </aside>


        </section>


    </div>

</main>



<style>

/* =========================================================
   ADUIN - PETUGAS DETAIL TUGAS
========================================================= */

.staff-detail-page {

    min-height: calc(100vh - 76px);

    padding: 38px 24px 70px;

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

.staff-detail-shell {

    width: min(1120px, 100%);

    margin: 0 auto;

}


/* =========================================================
   HEADER
========================================================= */

.detail-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}

.detail-header-left {

    min-width: 0;

}

.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-bottom: 14px;

    color: #64748b;

    text-decoration: none;

    font-size: 9px;

    font-weight: 700;

    transition: .2s ease;

}

.back-btn:hover {

    color: #2563eb;

    transform: translateX(-2px);

}

.back-btn svg {

    width: 14px;
    height: 14px;

}

.detail-heading > span {

    display: block;

    margin-bottom: 5px;

    color: #2563eb;

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .1em;

}

.detail-heading h1 {

    margin: 0;

    color: #0f172a;

    font-size: 26px;

    font-weight: 800;

    line-height: 1.15;

    letter-spacing: -.5px;

}

.detail-heading p {

    margin: 6px 0 0;

    color: #64748b;

    font-size: 10px;

    line-height: 1.5;

}

.detail-current-status {

    min-height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 0 11px;

    flex-shrink: 0;

    border-radius: 9px;

    font-size: 9px;

    font-weight: 800;

}

.detail-current-status svg {

    width: 14px;
    height: 14px;

}

.detail-current-status.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}

.detail-current-status.processing {

    color: #6d28d9;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}

.detail-current-status.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}


/* =========================================================
   ALERT
========================================================= */

.detail-alert {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    margin-bottom: 18px;

    padding: 13px 15px;

    border-radius: 12px;

}

.detail-alert.success {

    color: #166534;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}

.detail-alert.error {

    color: #991b1b;

    background: #fef2f2;

    border: 1px solid #fecaca;

}

.detail-alert-icon {

    width: 30px;
    height: 30px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 8px;

}

.detail-alert.success
.detail-alert-icon {

    background: #dcfce7;

}

.detail-alert.error
.detail-alert-icon {

    background: #fee2e2;

}

.detail-alert-icon svg {

    width: 16px;
    height: 16px;

}

.detail-alert strong {

    display: block;

    font-size: 10px;

}

.detail-alert p {

    margin: 3px 0 0;

    font-size: 8px;

    line-height: 1.5;

    opacity: .8;

}


/* =========================================================
   GRID
========================================================= */

.detail-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        350px;

    align-items: start;

    gap: 20px;

}

.detail-main {

    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 16px;

}

.detail-sidebar {

    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 16px;

    position: sticky;

    top: 95px;

}


/* =========================================================
   CARD
========================================================= */

.detail-card {

    min-width: 0;

    overflow: hidden;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px rgba(15, 23, 42, .035);

}

.detail-card-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 17px 18px;

    border-bottom: 1px solid #eef2f7;

}

.detail-card-heading > div:first-child {

    min-width: 0;

}

.detail-card-heading span {

    display: block;

    margin-bottom: 4px;

    color: #2563eb;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .09em;

}

.detail-card-heading h2 {

    margin: 0;

    color: #0f172a;

    font-size: 14px;

    font-weight: 800;

    line-height: 1.2;

}

.detail-heading-icon {

    width: 33px;
    height: 33px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 9px;

}

.detail-heading-icon svg {

    width: 16px;
    height: 16px;

}


/* =========================================================
   REPORT INFO
========================================================= */

.report-detail-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 0;

}

.report-detail-item {

    min-width: 0;

    padding: 15px 18px;

    border-bottom: 1px solid #eef2f7;

}

.report-detail-item:nth-last-child(-n+2) {

    border-bottom: none;

}

.report-detail-item.full {

    grid-column: 1 / -1;

}

.report-detail-item > span {

    display: block;

    margin-bottom: 5px;

    color: #94a3b8;

    font-size: 8px;

}

.report-detail-item > strong {

    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 10px;

    line-height: 1.45;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.detail-location {

    display: flex;

    align-items: center;

    gap: 6px;

}

.detail-location svg {

    width: 14px;
    height: 14px;

    flex-shrink: 0;

    color: #2563eb;

}

.detail-location strong {

    overflow: hidden;

    color: #334155;

    font-size: 10px;

    line-height: 1.45;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.description-box {

    padding: 17px 18px;

    color: #475569;

    font-size: 10px;

    line-height: 1.75;

    white-space: normal;

}


/* =========================================================
   REPORT IMAGE
========================================================= */

.report-image {

    width: 100%;

    max-height: 440px;

    overflow: hidden;

    background: #f8fafc;

}

.report-image img {

    width: 100%;

    max-height: 440px;

    display: block;

    object-fit: contain;

}


/* =========================================================
   NO IMAGE
========================================================= */

.no-image {

    min-height: 210px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    gap: 5px;

    color: #94a3b8;

    text-align: center;

}

.no-image-icon {

    width: 50px;
    height: 50px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 4px;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 13px;

}

.no-image-icon svg {

    width: 23px;
    height: 23px;

}

.no-image strong {

    color: #64748b;

    font-size: 10px;

}

.no-image span {

    font-size: 8px;

}


/* =========================================================
   HISTORY
========================================================= */

.status-history {

    padding: 18px;

}

.history-item {

    display: flex;

    gap: 11px;

    min-width: 0;

}

.history-marker {

    position: relative;

    width: 26px;

    flex-shrink: 0;

    display: flex;

    justify-content: center;

}

.history-marker-circle {

    position: relative;

    z-index: 2;

    width: 25px;
    height: 25px;

    display: flex;

    align-items: center;
    justify-content: center;

    box-sizing: border-box;

    border-radius: 50%;

}

.history-marker-circle svg {

    width: 12px;
    height: 12px;

}

.history-marker-circle.waiting {

    color: #b45309;

    background: #fffbeb;

    border: 1px solid #fde68a;

}

.history-marker-circle.processing {

    color: #6d28d9;

    background: #f5f3ff;

    border: 1px solid #ddd6fe;

}

.history-marker-circle.completed {

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

}

.history-line {

    position: absolute;

    top: 25px;

    left: 50%;

    width: 1px;

    height: calc(100% - 5px);

    background: #e2e8f0;

    transform: translateX(-50%);

}

.history-content {

    min-width: 0;

    flex: 1;

    padding-bottom: 19px;

}

.history-content-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

}

.history-content-top strong {

    color: #334155;

    font-size: 10px;

    font-weight: 800;

}

.history-content-top span {

    color: #94a3b8;

    font-size: 7px;

    white-space: nowrap;

}

.history-content p {

    margin: 5px 0 0;

    color: #64748b;

    font-size: 8px;

    line-height: 1.55;

}

.history-empty {

    min-height: 130px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    gap: 7px;

    color: #94a3b8;

}

.history-empty svg {

    width: 25px;
    height: 25px;

}

.history-empty span {

    font-size: 8px;

}


/* =========================================================
   UPDATE FORM
========================================================= */

.update-card {

    overflow: visible;

}

.update-card form {

    padding: 18px;

}

.form-group {

    margin-bottom: 17px;

}

.form-group > label {

    display: block;

    margin-bottom: 7px;

    color: #334155;

    font-size: 9px;

    font-weight: 800;

}

.form-group > label span {

    color: #ef4444;

}

.select-wrap {

    position: relative;

}

.select-wrap select {

    width: 100%;
    height: 40px;

    appearance: none;
    -webkit-appearance: none;

    box-sizing: border-box;

    padding: 0 37px 0 11px;

    color: #334155;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 9px;

    font-weight: 600;

    cursor: pointer;

}

.select-wrap select:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, .07);

}

.select-wrap > svg {

    position: absolute;

    top: 50%;
    right: 11px;

    width: 14px;
    height: 14px;

    color: #94a3b8;

    pointer-events: none;

    transform: translateY(-50%);

}

.status-help {

    display: flex;

    align-items: flex-start;

    gap: 8px;

    margin-top: -5px;

    margin-bottom: 17px;

    padding: 10px;

    background: #f8fafc;

    border: 1px solid #eef2f7;

    border-radius: 8px;

}

.status-help-icon {

    width: 22px;
    height: 22px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 6px;

}

.status-help-icon svg {

    width: 12px;
    height: 12px;

}

.status-help p {

    margin: 0;

    color: #64748b;

    font-size: 8px;

    line-height: 1.5;

}

.form-group textarea {

    width: 100%;

    min-height: 105px;

    box-sizing: border-box;

    resize: vertical;

    padding: 10px 11px;

    color: #334155;

    background: #fff;

    border: 1px solid #dbe3ec;

    border-radius: 9px;

    outline: none;

    font-family: inherit;

    font-size: 9px;

    line-height: 1.55;

}

.form-group textarea:focus {

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, .07);

}

.form-group small {

    display: block;

    margin-top: 5px;

    color: #94a3b8;

    font-size: 7px;

}


/* =========================================================
   UPLOAD
========================================================= */

.upload-box {

    position: relative;

    width: 100%;

    min-height: 130px;

    box-sizing: border-box;

    overflow: hidden;

    border: 1px dashed #bfdbfe;

    background: #f8fbff;

    border-radius: 10px;

    cursor: pointer;

    transition:
        border-color .2s ease,
        background .2s ease;

}

.upload-box:hover {

    background: #f0f7ff;

    border-color: #60a5fa;

}

.upload-box.dragging {

    background: #eff6ff;

    border-color: #2563eb;

}

.upload-box > input {

    position: absolute;

    inset: 0;

    width: 100%;
    height: 100%;

    opacity: 0;

    cursor: pointer;

    z-index: 3;

}

.upload-content {

    min-height: 130px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-direction: column;

    gap: 5px;

    padding: 15px;

    text-align: center;

}

.upload-icon {

    width: 37px;
    height: 37px;

    display: flex;

    align-items: center;
    justify-content: center;

    margin-bottom: 3px;

    color: #2563eb;

    background: #eff6ff;

    border-radius: 10px;

}

.upload-icon svg {

    width: 18px;
    height: 18px;

}

.upload-content strong {

    color: #334155;

    font-size: 9px;

}

.upload-content span {

    color: #94a3b8;

    font-size: 7px;

}

.upload-preview {

    display: none;

    align-items: center;

    gap: 9px;

    min-height: 130px;

    padding: 12px;

}

.upload-preview.show {

    display: flex;

}

.upload-preview img {

    width: 76px;
    height: 76px;

    flex-shrink: 0;

    object-fit: cover;

    border-radius: 8px;

}

.upload-preview > div {

    min-width: 0;

    flex: 1;

}

.upload-preview strong {

    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.upload-preview span {

    display: block;

    margin-top: 4px;

    color: #94a3b8;

    font-size: 7px;

}

.upload-preview button {

    position: relative;

    z-index: 5;

    width: 27px;
    height: 27px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #64748b;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 7px;

    cursor: pointer;

}

.upload-preview button:hover {

    color: #dc2626;

    background: #fef2f2;

    border-color: #fecaca;

}

.upload-preview button svg {

    width: 13px;
    height: 13px;

}

.existing-photo {

    display: flex;

    align-items: flex-start;

    gap: 6px;

    margin-top: 7px;

    color: #64748b;

}

.existing-photo svg {

    width: 12px;
    height: 12px;

    flex-shrink: 0;

    color: #2563eb;

}

.existing-photo span {

    font-size: 7px;

    line-height: 1.5;

}

.save-btn {

    width: 100%;
    min-height: 42px;

    display: flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    border-radius: 9px;

    cursor: pointer;

    font-family: inherit;

    font-size: 9px;

    font-weight: 800;

    box-shadow:
        0 7px 18px rgba(37, 99, 235, .16);

    transition: .25s ease;

}

.save-btn:hover {

    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-1px);

    box-shadow:
        0 10px 22px rgba(37, 99, 235, .22);

}

.save-btn:active {

    transform: scale(.99);

}

.save-btn.loading {

    pointer-events: none;

    opacity: .7;

}

.save-btn svg {

    width: 15px;
    height: 15px;

}


/* =========================================================
   REPORTER
========================================================= */

.reporter-card {

    overflow: hidden;

}

.reporter-profile {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 16px 18px;

}

.reporter-avatar {

    width: 39px;
    height: 39px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;

    border-radius: 11px;

}

.reporter-avatar svg {

    width: 18px;
    height: 18px;

}

.reporter-profile strong {

    display: block;

    color: #334155;

    font-size: 10px;

    font-weight: 800;

}

.reporter-profile span {

    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 7px;

}

.reporter-details {

    padding: 0 18px 16px;

}

.reporter-details > div {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-top: 8px;

    color: #64748b;

}

.reporter-details svg {

    width: 12px;
    height: 12px;

    flex-shrink: 0;

    color: #94a3b8;

}

.reporter-details span {

    overflow: hidden;

    font-size: 7px;

    text-overflow: ellipsis;

    white-space: nowrap;

}


/* =========================================================
   HANDLING
========================================================= */

.handling-person {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 15px 18px;

}

.handling-avatar {

    width: 35px;
    height: 35px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #15803d;

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

    border-radius: 9px;

}

.handling-avatar svg {

    width: 16px;
    height: 16px;

}

.handling-person span {

    display: block;

    margin-bottom: 3px;

    color: #94a3b8;

    font-size: 7px;

}

.handling-person strong {

    display: block;

    color: #334155;

    font-size: 9px;

}

.handling-note {

    margin: 0 18px 14px;

    padding: 10px;

    background: #f8fafc;

    border: 1px solid #eef2f7;

    border-radius: 8px;

}

.handling-note > span {

    display: block;

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 7px;

    font-weight: 700;

}

.handling-note p {

    margin: 0;

    color: #64748b;

    font-size: 8px;

    line-height: 1.55;

}

.repair-photo {

    padding: 0 18px 14px;

}

.repair-photo > span {

    display: block;

    margin-bottom: 6px;

    color: #94a3b8;

    font-size: 7px;

    font-weight: 700;

}

.repair-photo img {

    width: 100%;

    max-height: 190px;

    display: block;

    object-fit: cover;

    border-radius: 9px;

    border: 1px solid #e2e8f0;

}

.handling-date {

    display: flex;

    align-items: center;

    gap: 6px;

    padding: 11px 18px;

    color: #94a3b8;

    background: #fafcff;

    border-top: 1px solid #eef2f7;

}

.handling-date svg {

    width: 12px;
    height: 12px;

    flex-shrink: 0;

}

.handling-date span {

    font-size: 7px;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 950px) {

    .detail-grid {

        grid-template-columns:
            minmax(0, 1fr);

    }

    .detail-sidebar {

        position: static;

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        align-items: start;

    }

    .update-card {

        grid-column: 1 / -1;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {

    .staff-detail-page {

        padding: 28px 14px 50px;

    }

    .detail-header {

        align-items: flex-start;

        flex-direction: column;

    }

    .detail-current-status {

        align-self: flex-start;

    }

    .detail-heading h1 {

        font-size: 22px;

        word-break: break-word;

    }

    .detail-sidebar {

        display: flex;

        flex-direction: column;

    }

    .report-detail-grid {

        grid-template-columns:
            1fr;

    }

    .report-detail-item.full {

        grid-column: auto;

    }

    .report-detail-item {

        border-bottom: 1px solid #eef2f7 !important;

    }

    .report-detail-item:last-child {

        border-bottom: none !important;

    }

    .history-content-top {

        align-items: flex-start;

        flex-direction: column;

        gap: 4px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .staff-detail-page {

        padding: 24px 12px 45px;

    }

    .detail-card-heading {

        padding: 15px;

    }

    .report-detail-item {

        padding: 13px 15px;

    }

    .description-box {

        padding: 15px;

    }

    .status-history {

        padding: 15px;

    }

    .update-card form {

        padding: 15px;

    }

    .reporter-profile {

        padding: 14px 15px;

    }

    .reporter-details {

        padding: 0 15px 14px;

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

/* =========================================================
   DETAIL TUGAS - MOBILE FINAL FIX
   Hanya mempengaruhi layar <= 620px
   Desktop tetap seperti sebelumnya
========================================================= */

@media (max-width: 620px) {

    /* =====================================================
       PAGE
    ===================================================== */

    .staff-detail-page,
    .task-detail-page,
    .detail-task-page {

        width: 100% !important;
        max-width: 100% !important;

        box-sizing: border-box;

        padding:
            24px
            12px
            45px !important;

        overflow-x: hidden;

    }


    /* =====================================================
       CONTAINER
    ===================================================== */

    .staff-detail-shell,
    .task-detail-shell,
    .detail-task-shell {

        width: 100% !important;
        max-width: 100% !important;

        margin: 0 auto;

    }


    /* =====================================================
       HEADER
    ===================================================== */

    .detail-page-header,
    .task-detail-header {

        display: flex !important;

        flex-direction: column !important;

        align-items: flex-start !important;

        width: 100%;

        gap: 14px;

        margin-bottom: 18px !important;

    }


    .detail-page-header > div,
    .task-detail-header > div {

        width: 100%;

        min-width: 0;

    }


    .detail-page-header h1,
    .task-detail-header h1 {

        font-size: 23px !important;

        line-height: 1.2;

        letter-spacing: -.4px;

    }


    .detail-page-header p,
    .task-detail-header p {

        font-size: 9px !important;

        line-height: 1.6;

        margin-top: 5px;

    }


    .detail-back-btn,
    .task-detail-back-btn {

        width: 100% !important;

        min-height: 40px;

        box-sizing: border-box;

    }


    /* =====================================================
       MAIN GRID
    ===================================================== */

    .detail-main-grid,
    .task-detail-grid,
    .detail-task-grid {

        display: flex !important;

        flex-direction: column !important;

        width: 100% !important;

        max-width: 100% !important;

        gap: 14px !important;

    }


    /* =====================================================
       LEFT / MAIN
    ===================================================== */

    .detail-main,
    .task-detail-main,
    .detail-task-main {

        width: 100% !important;

        min-width: 0 !important;

        max-width: 100% !important;

    }


    /* =====================================================
       RIGHT SIDEBAR
    ===================================================== */

    .detail-sidebar,
    .task-detail-sidebar,
    .detail-task-sidebar {

        width: 100% !important;

        min-width: 0 !important;

        max-width: 100% !important;

        display: flex !important;

        flex-direction: column !important;

        gap: 14px !important;

        position: static !important;

        top: auto !important;

    }


    /* =====================================================
       SEMUA CARD
    ===================================================== */

    .detail-card,
    .task-detail-card,
    .detail-task-card,
    .report-card,
    .handling-card,
    .reporter-card {

        width: 100% !important;

        max-width: 100% !important;

        min-width: 0 !important;

        box-sizing: border-box;

        overflow: hidden;

        border-radius: 13px !important;

    }


    /* =====================================================
       CARD HEADER
    ===================================================== */

    .detail-card-header,
    .task-detail-card-header,
    .card-heading {

        width: 100%;

        box-sizing: border-box;

        padding:
            14px
            15px !important;

    }


    .detail-card-header h2,
    .task-detail-card-header h2,
    .card-heading h2 {

        font-size: 13px !important;

    }


    /* =====================================================
       CARD BODY
    ===================================================== */

    .detail-card-body,
    .task-detail-card-body,
    .card-body {

        width: 100%;

        box-sizing: border-box;

        padding:
            14px
            15px !important;

    }


    /* =====================================================
       REPORT INFO
    ===================================================== */

    .report-info-grid,
    .detail-info-grid,
    .task-info-grid {

        display: grid !important;

        grid-template-columns:
            minmax(0, 1fr) !important;

        width: 100% !important;

        gap: 11px !important;

    }


    .report-info-item,
    .detail-info-item,
    .task-info-item {

        width: 100% !important;

        min-width: 0 !important;

    }


    .report-info-item strong,
    .detail-info-item strong,
    .task-info-item strong {

        max-width: 100%;

        overflow-wrap: anywhere;

        word-break: break-word;

    }


    /* =====================================================
       DESCRIPTION
    ===================================================== */

    .description-box,
    .detail-description,
    .report-description {

        width: 100% !important;

        max-width: 100% !important;

        box-sizing: border-box;

        padding: 12px !important;

        font-size: 8px !important;

        line-height: 1.65 !important;

        overflow-wrap: anywhere;

        word-break: break-word;

    }


    /* =====================================================
       FOTO PENGADUAN
    ===================================================== */

    .complaint-photo,
    .report-photo,
    .detail-photo {

        width: 100% !important;

        max-width: 100% !important;

        height: auto !important;

        max-height: 270px !important;

        object-fit: contain !important;

        display: block;

        border-radius: 10px;

    }


    .photo-wrapper,
    .complaint-photo-wrapper,
    .report-photo-wrapper {

        width: 100% !important;

        max-width: 100% !important;

        box-sizing: border-box;

        overflow: hidden;

        border-radius: 10px;

    }


    /* =====================================================
       STATUS FORM
    ===================================================== */

    .status-form,
    .update-status-form,
    .handling-form {

        width: 100% !important;

        max-width: 100% !important;

        box-sizing: border-box;

    }


    .status-form-grid,
    .handling-form-grid {

        display: grid !important;

        grid-template-columns:
            minmax(0, 1fr) !important;

        width: 100% !important;

        gap: 12px !important;

    }


    .status-field,
    .handling-field,
    .form-field {

        width: 100% !important;

        min-width: 0 !important;

    }


    .status-field select,
    .handling-field select,
    .form-field select,
    .status-select {

        width: 100% !important;

        max-width: 100% !important;

        min-height: 42px;

        box-sizing: border-box;

    }


    /* =====================================================
       TEXTAREA
    ===================================================== */

    textarea {

        width: 100% !important;

        max-width: 100% !important;

        min-height: 105px;

        box-sizing: border-box;

        resize: vertical;

    }


    /* =====================================================
       UPLOAD FOTO PERBAIKAN
    ===================================================== */

    .upload-area,
    .photo-upload,
    .repair-upload,
    .upload-box {

        width: 100% !important;

        max-width: 100% !important;

        min-height: 125px !important;

        box-sizing: border-box;

        padding: 20px 14px !important;

        border-radius: 11px !important;

    }


    .upload-area svg,
    .photo-upload svg,
    .repair-upload svg,
    .upload-box svg {

        width: 23px !important;

        height: 23px !important;

    }


    .upload-area strong,
    .photo-upload strong,
    .repair-upload strong,
    .upload-box strong {

        font-size: 9px !important;

    }


    .upload-area span,
    .photo-upload span,
    .repair-upload span,
    .upload-box span {

        font-size: 7px !important;

        line-height: 1.5;

    }


    /* =====================================================
       BUTTON SIMPAN
    ===================================================== */

    .save-btn,
    .save-change-btn,
    .update-btn,
    .submit-btn {

        width: 100% !important;

        min-height: 43px !important;

        box-sizing: border-box;

        justify-content: center;

    }


    /* =====================================================
       FOTO PREVIEW
    ===================================================== */

    .photo-preview,
    .upload-preview {

        width: 100% !important;

        max-width: 100% !important;

        box-sizing: border-box;

    }


    .photo-preview img,
    .upload-preview img {

        width: 100% !important;

        max-width: 100% !important;

        max-height: 230px;

        object-fit: contain;

        border-radius: 9px;

    }


    /* =====================================================
       INFORMASI PELAPOR
    ===================================================== */

    .reporter-card {

        width: 100% !important;

    }


    .reporter-profile,
    .reporter-info {

        width: 100%;

        min-width: 0;

    }


    .reporter-name {

        max-width: 100%;

        overflow: hidden;

        text-overflow: ellipsis;

        white-space: nowrap;

    }


    .reporter-email,
    .reporter-phone {

        max-width: 100%;

        overflow-wrap: anywhere;

        word-break: break-word;

    }


    /* =====================================================
       DATA PENANGANAN
    ===================================================== */

    .handling-info,
    .handling-data {

        width: 100% !important;

        display: flex !important;

        flex-direction: column !important;

        gap: 10px !important;

    }


    .handling-item {

        width: 100% !important;

        min-width: 0 !important;

        box-sizing: border-box;

    }


    .handling-item strong {

        overflow-wrap: anywhere;

        word-break: break-word;

    }


    /* =====================================================
       STATUS HISTORY
    ===================================================== */

    .status-history {

        width: 100% !important;

        max-width: 100% !important;

    }


    .status-history-item {

        width: 100%;

        min-width: 0;

    }


    .status-history-content {

        min-width: 0;

        max-width: calc(100% - 38px);

    }


    .status-history-content strong {

        overflow-wrap: anywhere;

        word-break: break-word;

    }


    .status-history-content p {

        overflow-wrap: anywhere;

        word-break: break-word;

        line-height: 1.5;

    }


    /* =====================================================
       SIDEBAR ICON
    ===================================================== */

    .card-icon,
    .section-icon {

        flex-shrink: 0;

    }


    /* =====================================================
       ANTI OVERFLOW
    ===================================================== */

    .staff-detail-page *,
    .task-detail-page *,
    .detail-task-page * {

        max-width: 100%;

    }

}


/* =========================================================
   SMALL PHONE
========================================================= */

@media (max-width: 420px) {

    .staff-detail-page,
    .task-detail-page,
    .detail-task-page {

        padding:
            20px
            10px
            40px !important;

    }


    .detail-page-header h1,
    .task-detail-header h1 {

        font-size: 21px !important;

    }


    .detail-page-header p,
    .task-detail-header p {

        font-size: 8px !important;

    }


    .detail-card-header,
    .task-detail-card-header,
    .card-heading {

        padding:
            13px
            13px !important;

    }


    .detail-card-body,
    .task-detail-card-body,
    .card-body {

        padding:
            13px !important;

    }


    .upload-area,
    .photo-upload,
    .repair-upload,
    .upload-box {

        min-height: 115px !important;

        padding: 17px 10px !important;

    }


    .save-btn,
    .save-change-btn,
    .update-btn,
    .submit-btn {

        min-height: 42px !important;

    }

}


/* =========================================================
   VERY SMALL PHONE
========================================================= */

@media (max-width: 360px) {

    .staff-detail-page,
    .task-detail-page,
    .detail-task-page {

        padding-left: 8px !important;

        padding-right: 8px !important;

    }


    .detail-page-header h1,
    .task-detail-header h1 {

        font-size: 20px !important;

    }


    .detail-card-header,
    .task-detail-card-header,
    .card-heading {

        padding:
            12px !important;

    }


    .detail-card-body,
    .task-detail-card-body,
    .card-body {

        padding:
            12px !important;

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

                lenis.raf(
                    time * 1000
                );

                requestAnimationFrame(
                    raf
                );

            }


            requestAnimationFrame(
                raf
            );

        }



        /* =====================================================
           GSAP
        ===================================================== */

        if (window.gsap) {


            gsap.from(
                ".detail-header",
                {
                    y: 20,
                    opacity: 0,
                    duration: .55,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".detail-alert",
                {
                    y: -10,
                    opacity: 0,
                    duration: .45,
                    delay: .05,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".detail-main .detail-card",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .08,
                    delay: .1,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".detail-sidebar .detail-card",
                {
                    y: 18,
                    opacity: 0,
                    duration: .5,
                    stagger: .08,
                    delay: .18,
                    ease: "power3.out"
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



        /* =====================================================
           STATUS HELP
        ===================================================== */

        const statusSelect =
            document.getElementById(
                "status"
            );


        const statusHelp =
            document.getElementById(
                "statusHelp"
            );


        if (
            statusSelect &&
            statusHelp
        ) {


            statusSelect.addEventListener(
                "change",
                function () {


                    const value =
                        this.value;


                    if (
                        value ===
                        "Menunggu"
                    ) {

                        statusHelp.textContent =
                            "Pengaduan belum mulai ditangani.";

                    } else if (
                        value ===
                        "Diproses"
                    ) {

                        statusHelp.textContent =
                            "Pengaduan sedang dalam proses penanganan.";

                    } else {

                        statusHelp.textContent =
                            "Pengaduan telah selesai ditangani.";

                    }

                }
            );

        }



        /* =====================================================
           UPLOAD PREVIEW
        ===================================================== */

        const fileInput =
            document.getElementById(
                "foto_perbaikan"
            );


        const uploadBox =
            document.getElementById(
                "uploadBox"
            );


        const uploadContent =
            document.getElementById(
                "uploadContent"
            );


        const uploadPreview =
            document.getElementById(
                "uploadPreview"
            );


        const previewImage =
            document.getElementById(
                "previewImage"
            );


        const previewName =
            document.getElementById(
                "previewName"
            );


        const removeImage =
            document.getElementById(
                "removeImage"
            );


        function showPreview(
            file
        ) {


            if (
                !file ||
                !file.type.startsWith(
                    "image/"
                )
            ) {

                return;

            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    previewImage.src =
                        event.target.result;

                    previewName.textContent =
                        file.name;

                    uploadContent.style.display =
                        "none";

                    uploadPreview.classList.add(
                        "show"
                    );

                    if (window.lucide) {
                        lucide.createIcons();
                    }

                };


            reader.readAsDataURL(
                file
            );

        }


        if (fileInput) {


            fileInput.addEventListener(
                "change",
                function () {

                    if (
                        this.files &&
                        this.files[0]
                    ) {

                        showPreview(
                            this.files[0]
                        );

                    }

                }
            );

        }


        if (removeImage) {


            removeImage.addEventListener(
                "click",
                function (event) {

                    event.preventDefault();

                    event.stopPropagation();


                    fileInput.value = "";

                    previewImage.src = "";

                    previewName.textContent = "";

                    uploadPreview.classList.remove(
                        "show"
                    );

                    uploadContent.style.display =
                        "flex";

                }
            );

        }



        /* =====================================================
           DRAG & DROP
        ===================================================== */

        if (uploadBox) {


            [
                "dragenter",
                "dragover"
            ].forEach(
                function (eventName) {

                    uploadBox.addEventListener(
                        eventName,
                        function (event) {

                            event.preventDefault();

                            uploadBox.classList.add(
                                "dragging"
                            );

                        }
                    );

                }
            );


            [
                "dragleave",
                "drop"
            ].forEach(
                function (eventName) {

                    uploadBox.addEventListener(
                        eventName,
                        function (event) {

                            event.preventDefault();

                            uploadBox.classList.remove(
                                "dragging"
                            );

                        }
                    );

                }
            );


            uploadBox.addEventListener(
                "drop",
                function (event) {


                    const files =
                        event.dataTransfer.files;


                    if (
                        files &&
                        files.length > 0
                    ) {


                        const file =
                            files[0];


                        /*
                         * DataTransfer digunakan supaya
                         * file tetap masuk ke input.
                         */

                        try {

                            const dataTransfer =
                                new DataTransfer();

                            dataTransfer.items.add(
                                file
                            );

                            fileInput.files =
                                dataTransfer.files;

                        } catch (error) {

                            /* fallback preview */

                        }


                        showPreview(
                            file
                        );

                    }

                }
            );

        }



        /* =====================================================
           FORM SUBMIT
        ===================================================== */

        const updateForm =
            document.getElementById(
                "updateForm"
            );


        const saveButton =
            document.getElementById(
                "saveButton"
            );


        if (
            updateForm &&
            saveButton
        ) {


            updateForm.addEventListener(
                "submit",
                function () {


                    saveButton.classList.add(
                        "loading"
                    );


                    saveButton.innerHTML = `

                        <i
                            data-lucide="loader-circle"
                            class="loading-icon"
                        ></i>

                        <span>
                            Menyimpan...
                        </span>

                    `;


                    if (window.lucide) {

                        lucide.createIcons();

                    }

                }
            );

        }



        /* =====================================================
           LOADING ICON
        ===================================================== */

        const style =
            document.createElement(
                "style"
            );


        style.textContent = `

            .loading-icon {
                animation:
                    detailSpin .8s linear infinite;
            }

            @keyframes detailSpin {
                to {
                    transform: rotate(360deg);
                }
            }

        `;


        document.head.appendChild(
            style
        );

    }
);

</script>


</body>

</html>
<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/WhatsAppOutbox.php';
$user = requireRole('pengguna');
$id_user = (int)$user['id_user'];
$error = '';
$kategoriResult = dbQuery($conn, 'SELECT id_kategori,nama_kategori FROM kategori ORDER BY nama_kategori ASC');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $requestToken = postString('creation_token');
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestToken) || !array_key_exists($requestToken, $_SESSION['complaint_requests'] ?? [])) {
        forbid();
    }
    if (is_string($_SESSION['complaint_requests'][$requestToken])) {
        header('Location: riwayat.php?success=1&laporan=' . rawurlencode($_SESSION['complaint_requests'][$requestToken]), true, 303);
        exit;
    }
    $id_kategori = validId($_POST['id_kategori'] ?? 0);
    $lokasi = trim(postString('lokasi'));
    $deskripsi = trim(postString('deskripsi'));
    if (!rateLimit('complaint_user', (string)$id_user, 20, 3600)) {
        http_response_code(429);
        $error = 'Terlalu banyak pengaduan. Silakan coba lagi nanti.';
    } elseif (!$id_kategori) {
        $error = 'Silakan pilih kategori fasilitas.';
    } elseif (mb_strlen($lokasi) < 3 || mb_strlen($lokasi) > min(255, dbTextLimit($conn, 'pengaduan', 'lokasi', 255)) || strlen($lokasi) > 1020) {
        $error = 'Lokasi fasilitas harus 3 hingga 255 karakter sesuai kapasitas kolom database.';
    } elseif (mb_strlen($deskripsi) < 10 || mb_strlen($deskripsi) > 1000 || strlen($deskripsi) > 4000) {
        $error = 'Deskripsi kerusakan harus 10 hingga 1000 karakter.';
    }
    $namaFoto = null;
    if ($error === '') {
        try {
            if (isset($_FILES['foto']) && !is_array($_FILES['foto'])) {
                throw new InvalidArgumentException('Foto tidak valid.');
            }
            $category = dbOne($conn, 'SELECT id_kategori,nama_kategori FROM kategori WHERE id_kategori = ? LIMIT 1', 'i', [$id_kategori]);
            if (!$category) {
                throw new InvalidArgumentException('Kategori fasilitas tidak valid.');
            }
            $namaFoto = storeImageUpload($_FILES['foto'] ?? null, 'pengaduan');
            $conn->begin_transaction();
            // Persist idempotency in the same transaction, including across a crash
            // after COMMIT but before PHP has persisted the session/redirect.
            $requestHash = hash('sha256', $requestToken);
            dbStatement($conn, 'INSERT INTO complaint_submission_requests (user_id,request_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE request_hash = request_hash', 'is', [$id_user,$requestHash]);
            $submission = dbOne($conn, 'SELECT pengaduan_id FROM complaint_submission_requests WHERE user_id = ? AND request_hash = ? FOR UPDATE', 'is', [$id_user,$requestHash]);
            if ($submission['pengaduan_id'] !== null) {
                $existing = dbOne($conn, 'SELECT no_laporan FROM pengaduan WHERE id_pengaduan = ? AND id_user = ?', 'ii', [(int)$submission['pengaduan_id'],$id_user]);
                if (!$existing) { throw new RuntimeException('submission_reference_invalid'); }
                $conn->commit();
                if ($namaFoto !== null) { unlink(dirname(__DIR__) . '/uploads/pengaduan/' . $namaFoto); }
                $_SESSION['complaint_requests'][$requestToken] = $existing['no_laporan'];
                header('Location: riwayat.php?success=1&laporan=' . rawurlencode($existing['no_laporan']), true, 303);
                exit;
            }
            // Random report suffix makes anonymous enumeration impractical.
            do {
                $no_laporan = 'LAP-' . date('Ymd') . strtoupper(bin2hex(random_bytes(8)));
            } while (dbOne($conn, 'SELECT id_pengaduan FROM pengaduan WHERE no_laporan = ? LIMIT 1', 's', [$no_laporan]));
            dbStatement($conn, "INSERT INTO pengaduan (no_laporan,id_user,id_kategori,lokasi,deskripsi,foto,status) VALUES (?,?,?,?,?,?,'Menunggu')", 'siisss', [$no_laporan,$id_user,$id_kategori,$lokasi,$deskripsi,$namaFoto]);
            $id_pengaduan = (int)$conn->insert_id;
            dbStatement($conn, 'UPDATE complaint_submission_requests SET pengaduan_id = ? WHERE user_id = ? AND request_hash = ?', 'iis', [$id_pengaduan,$id_user,$requestHash]);
            dbStatement($conn, "INSERT INTO riwayat_status (id_pengaduan,status,keterangan) VALUES (?,'Menunggu',?)", 'is', [$id_pengaduan,'Pengaduan berhasil dibuat dan menunggu verifikasi.']);
            enqueueWhatsAppNotification($conn, $id_pengaduan, ['no_laporan'=>$no_laporan,'nama_kategori'=>$category['nama_kategori'],'lokasi'=>$lokasi,'status'=>'Menunggu']);
            $conn->commit();
            $_SESSION['complaint_requests'][$requestToken] = $no_laporan;
            header('Location: riwayat.php?success=1&laporan=' . rawurlencode($no_laporan), true, 303);
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            if ($namaFoto !== null && is_file(dirname(__DIR__) . '/uploads/pengaduan/' . $namaFoto)) {
                unlink(dirname(__DIR__) . '/uploads/pengaduan/' . $namaFoto);
            }
            safeLog('complaint.failed', ['code'=>$exception->getCode()]);
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Pengaduan gagal disimpan. Silakan coba lagi.';
        }
    }
} else {
    $_SESSION['complaint_requests'] = array_slice($_SESSION['complaint_requests'] ?? [], -9, null, true);
    $requestToken = bin2hex(random_bytes(32));
    $_SESSION['complaint_requests'][$requestToken] = null;
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

    <title>Buat Pengaduan | ADUIN</title>

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

$activePage = 'buat-pengaduan';

include '../includes/navbar.php';

?>


<main class="complaint-page">

    <section class="complaint-shell">


        <!-- =================================================
             HEADER
        ================================================= -->

        <div class="complaint-header">

            <div class="complaint-heading">

                <div class="heading-icon">

                    <i data-lucide="file-plus-2"></i>

                </div>


                <div>

                    <span class="eyebrow">
                        ADUIN
                    </span>

                    <h1>
                        Buat Pengaduan
                    </h1>

                    <p>
                        Laporkan fasilitas kampus yang mengalami
                        kerusakan atau membutuhkan penanganan.
                    </p>

                </div>

            </div>


            <a
                href="riwayat.php"
                class="history-btn"
            >

                <i data-lucide="history"></i>

                <span>
                    Riwayat Pengaduan
                </span>

            </a>

        </div>



        <!-- =================================================
             ERROR
        ================================================= -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-error">

                <div class="alert-icon">

                    <i data-lucide="circle-alert"></i>

                </div>


                <div>

                    <strong>
                        Pengaduan belum dapat dikirim
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            </div>

        <?php endif; ?>



        <!-- =================================================
             FORM
        ================================================= -->

        <form
            method="POST"
            enctype="multipart/form-data"
            id="complaintForm"
            class="complaint-form"
        >
                <?= csrfField() ?>
                <input type="hidden" name="creation_token" value="<?= e($requestToken) ?>">

            <div class="form-grid">


                <!-- =================================================
                     KOLOM KIRI
                ================================================= -->

                <div class="form-main">


                    <!-- INFORMASI PENGADUAN -->

                    <div class="form-card">

                        <div class="card-title">

                            <div class="card-title-icon">

                                <i data-lucide="clipboard-list"></i>

                            </div>


                            <div>

                                <h2>
                                    Informasi Pengaduan
                                </h2>

                                <p>
                                    Isi informasi fasilitas yang ingin dilaporkan.
                                </p>

                            </div>

                        </div>



                        <!-- KATEGORI -->

                        <div class="form-group">

                            <label for="id_kategori">

                                Kategori Fasilitas

                                <span>*</span>

                            </label>


                            <div class="select-wrap">

                                <i data-lucide="layers"></i>


                                <select
                                    name="id_kategori"
                                    id="id_kategori"
                                    required
                                >

                                    <option value="">
                                        Pilih kategori fasilitas
                                    </option>


                                    <?php
                                    if ($kategoriResult):
                                    ?>

                                        <?php
                                        while (
                                            $kategori =
                                            mysqli_fetch_assoc(
                                                $kategoriResult
                                            )
                                        ):
                                        ?>

                                            <option
                                                value="<?= $kategori['id_kategori'] ?>"
                                                <?= (
                                                    isset($_POST['id_kategori']) &&
                                                    $_POST['id_kategori'] ==
                                                    $kategori['id_kategori']
                                                )
                                                ? 'selected'
                                                : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $kategori['nama_kategori']
                                                ) ?>

                                            </option>

                                        <?php endwhile; ?>

                                    <?php endif; ?>

                                </select>


                                <i
                                    data-lucide="chevron-down"
                                    class="select-arrow"
                                ></i>

                            </div>

                        </div>



                        <!-- LOKASI -->

                        <div class="form-group">

                            <label for="lokasi">

                                Lokasi Fasilitas

                                <span>*</span>

                            </label>


                            <div class="input-wrap">

                                <i data-lucide="map-pin"></i>


                                <input
                                    type="text"
                                    name="lokasi"
                                    id="lokasi"
                                    maxlength="255"
                                    placeholder="Contoh: Ruang T02 Gedung Fakultas Teknik"
                                    value="<?= htmlspecialchars(
                                        $_POST['lokasi'] ?? ''
                                    ) ?>"
                                    required
                                >

                            </div>


                            <small>
                                Tuliskan lokasi secara spesifik agar petugas
                                mudah menemukan fasilitas.
                            </small>

                        </div>



                        <!-- DESKRIPSI -->

                        <div class="form-group">

                            <div class="label-row">

                                <label for="deskripsi">

                                    Deskripsi Kerusakan

                                    <span>*</span>

                                </label>


                                <span id="charCount">
                                    0 / 1000
                                </span>

                            </div>


                            <div class="textarea-wrap">

                                <i data-lucide="align-left"></i>


                                <textarea
                                    name="deskripsi"
                                    id="deskripsi"
                                    maxlength="1000"
                                    placeholder="Jelaskan kondisi fasilitas secara detail..."
                                    required
                                ><?= htmlspecialchars(
                                    $_POST['deskripsi'] ?? ''
                                ) ?></textarea>

                            </div>


                            <small>
                                Jelaskan kerusakan, kondisi fasilitas,
                                atau masalah yang ditemukan.
                            </small>

                        </div>

                    </div>



                    <!-- =================================================
                         FOTO
                    ================================================= -->

                    <div class="form-card">

                        <div class="card-title">

                            <div class="card-title-icon">

                                <i data-lucide="image"></i>

                            </div>


                            <div>

                                <h2>
                                    Foto Pendukung
                                </h2>

                                <p>
                                    Tambahkan foto untuk membantu proses pemeriksaan.
                                </p>

                            </div>

                        </div>


                        <div
                            class="upload-area"
                            id="uploadArea"
                        >

                            <input
                                type="file"
                                name="foto"
                                id="foto"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                hidden
                            >


                            <!-- UPLOAD DEFAULT -->

                            <div
                                class="upload-content"
                                id="uploadContent"
                            >

                                <div class="upload-icon">

                                    <i data-lucide="cloud-upload"></i>

                                </div>


                                <h3>
                                    Pilih foto kerusakan
                                </h3>


                                <p>
                                    Klik untuk memilih foto atau tarik
                                    file ke area ini
                                </p>


                                <span>
                                    JPG, JPEG, PNG, WEBP • Maks. 5 MB
                                </span>

                            </div>



                            <!-- PREVIEW -->

                            <div
                                class="preview-area"
                                id="previewArea"
                                hidden
                            >

                                <img
                                    id="imagePreview"
                                    src=""
                                    alt="Preview foto"
                                >


                                <div class="preview-info">

                                    <strong id="fileName">
                                        Foto
                                    </strong>

                                    <span id="fileSize">
                                        0 KB
                                    </span>

                                </div>


                                <button
                                    type="button"
                                    id="removePhoto"
                                    class="remove-photo"
                                    aria-label="Hapus foto"
                                >

                                    <i data-lucide="x"></i>

                                </button>

                            </div>

                        </div>

                    </div>

                </div>



                <!-- =================================================
                     SIDEBAR KANAN
                ================================================= -->

                <aside class="form-side">


                    <!-- PELAPOR -->

                    <div class="side-card">

                        <div class="side-card-header">

                            <div class="side-icon">

                                <i data-lucide="user-round"></i>

                            </div>


                            <div>

                                <h3>
                                    Pelapor
                                </h3>

                                <span>
                                    Data akun Anda
                                </span>

                            </div>

                        </div>


                        <div class="user-profile">

                            <div class="avatar">

                                <?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?>

                            </div>


                            <div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $user['nama']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars(
                                        $user['email']
                                    ) ?>
                                </span>

                            </div>

                        </div>


                        <?php if (!empty($user['fakultas'])): ?>

                            <div class="profile-meta">

                                <i data-lucide="graduation-cap"></i>

                                <span>
                                    <?= htmlspecialchars(
                                        $user['fakultas']
                                    ) ?>
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>



                    <!-- =================================================
                         ALUR
                    ================================================= -->

                    <div class="side-card">

                        <div class="side-card-header">

                            <div class="side-icon">

                                <i data-lucide="info"></i>

                            </div>


                            <div>

                                <h3>
                                    Informasi
                                </h3>

                                <span>
                                    Alur pengaduan
                                </span>

                            </div>

                        </div>


                        <div class="steps">


                            <div class="step">

                                <div class="step-number">
                                    1
                                </div>


                                <div>

                                    <strong>
                                        Pengaduan dibuat
                                    </strong>

                                    <span>
                                        Laporan masuk dengan status Menunggu.
                                    </span>

                                </div>

                            </div>



                            <div class="step">

                                <div class="step-number">
                                    2
                                </div>


                                <div>

                                    <strong>
                                        Diverifikasi
                                    </strong>

                                    <span>
                                        Admin memeriksa laporan yang masuk.
                                    </span>

                                </div>

                            </div>



                            <div class="step">

                                <div class="step-number">
                                    3
                                </div>


                                <div>

                                    <strong>
                                        Ditangani
                                    </strong>

                                    <span>
                                        Petugas melakukan penanganan fasilitas.
                                    </span>

                                </div>

                            </div>



                            <div class="step">

                                <div class="step-number">
                                    4
                                </div>


                                <div>

                                    <strong>
                                        Selesai
                                    </strong>

                                    <span>
                                        Status diperbarui setelah penanganan.
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>



                    <!-- =================================================
                         TIPS
                    ================================================= -->

                    <div class="side-card tips-card">

                        <div class="tips-title">

                            <i data-lucide="lightbulb"></i>

                            <strong>
                                Tips Pelaporan
                            </strong>

                        </div>


                        <ul>

                            <li>
                                Gunakan lokasi yang jelas.
                            </li>

                            <li>
                                Jelaskan kerusakan secara detail.
                            </li>

                            <li>
                                Sertakan foto jika memungkinkan.
                            </li>

                        </ul>

                    </div>

                </aside>

            </div>



            <!-- =================================================
                 ACTION
            ================================================= -->

            <div class="form-actions">

                <a
                    href="dashboard.php"
                    class="cancel-btn"
                >
                    Batal
                </a>


                <button
                    type="submit"
                    class="submit-btn"
                    id="submitBtn"
                >

                    <i data-lucide="send"></i>

                    <span>
                        Kirim Pengaduan
                    </span>

                </button>

            </div>

        </form>

    </section>

</main>



<style>

/* =========================================================
   PAGE
========================================================= */

.complaint-page {
    min-height: calc(100vh - 76px);

    padding: 42px 24px 70px;

    background:
        radial-gradient(
            circle at 10% 0%,
            rgba(37, 99, 235, .07),
            transparent 28%
        ),
        radial-gradient(
            circle at 95% 15%,
            rgba(59, 130, 246, .05),
            transparent 25%
        ),
        #f8fafc;
}

.complaint-shell {
    width: min(1120px, 100%);
    margin: 0 auto;
}


/* =========================================================
   HEADER
========================================================= */

.complaint-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 30px;
    margin-bottom: 30px;
}

.complaint-heading {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.heading-icon {
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

.heading-icon svg {
    width: 25px;
    height: 25px;
}

.eyebrow {
    display: block;

    margin: 1px 0 4px;

    color: #2563eb;

    font-size: 11px;
    font-weight: 800;

    letter-spacing: .08em;
}

.complaint-heading h1 {
    margin: 0;

    color: #0f172a;

    font-size: 32px;
    line-height: 1.15;
    font-weight: 800;

    letter-spacing: -.8px;
}

.complaint-heading p {
    max-width: 620px;

    margin: 8px 0 0;

    color: #64748b;

    font-size: 13px;
    line-height: 1.6;
}

.history-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    min-height: 43px;

    padding: 0 16px;

    color: #334155;
    background: #fff;

    border: 1px solid #e2e8f0;
    border-radius: 11px;

    text-decoration: none;

    font-size: 12px;
    font-weight: 700;

    box-shadow:
        0 5px 18px rgba(15, 23, 42, .04);

    transition:
        color .25s ease,
        background .25s ease,
        border-color .25s ease,
        transform .25s ease;
}

.history-btn:hover {
    color: #2563eb;
    background: #eff6ff;
    border-color: #bfdbfe;

    transform: translateY(-2px);
}

.history-btn svg {
    width: 17px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    display: flex;
    align-items: flex-start;

    gap: 12px;

    margin-bottom: 22px;
    padding: 14px 16px;

    border-radius: 13px;
}

.alert-error {
    color: #991b1b;
    background: #fef2f2;

    border: 1px solid #fecaca;
}

.alert-icon {
    width: 34px;
    height: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    background: #fee2e2;
    border-radius: 9px;
}

.alert-icon svg {
    width: 18px;
}

.alert strong {
    display: block;
    font-size: 13px;
}

.alert p {
    margin: 3px 0 0;

    font-size: 12px;
    line-height: 1.5;
}


/* =========================================================
   GRID
========================================================= */

.form-grid {
    width: 100%;

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        330px;

    gap: 24px;

    align-items: start;
}

.form-main {
    width: 100%;
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 20px;
}

.form-side {
    width: 100%;
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 18px;

    position: sticky;
    top: 94px;
}


/* =========================================================
   CARD
========================================================= */

.form-card,
.side-card {
    width: 100%;
    box-sizing: border-box;

    background: #fff;

    border: 1px solid #e2e8f0;
    border-radius: 17px;

    box-shadow:
        0 8px 30px rgba(15, 23, 42, .045);
}

.form-card {
    padding: 24px;
}

.side-card {
    padding: 20px;
}


/* =========================================================
   CARD TITLE
========================================================= */

.card-title,
.side-card-header {
    display: flex;
    align-items: center;

    gap: 12px;
}

.card-title {
    margin-bottom: 25px;
}

.card-title-icon,
.side-icon {
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

.card-title-icon svg,
.side-icon svg {
    width: 19px;
    height: 19px;
}

.card-title h2,
.side-card-header h3 {
    margin: 0;

    color: #0f172a;

    font-size: 15px;
    font-weight: 800;
}

.card-title p,
.side-card-header span {
    margin: 4px 0 0;

    color: #94a3b8;

    font-size: 11px;
    line-height: 1.4;
}


/* =========================================================
   FORM
========================================================= */

.form-group {
    margin-bottom: 21px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;

    margin-bottom: 8px;

    color: #334155;

    font-size: 12px;
    font-weight: 700;
}

.form-group label span {
    color: #ef4444;
}

.label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

#charCount {
    margin-bottom: 8px;

    color: #94a3b8;

    font-size: 10px;
    font-weight: 600;
}


/* =========================================================
   INPUT
========================================================= */

.input-wrap,
.select-wrap,
.textarea-wrap {
    width: 100%;
    position: relative;
}

.input-wrap > svg,
.select-wrap > svg:first-child,
.textarea-wrap > svg {
    position: absolute;

    left: 14px;

    width: 17px;
    height: 17px;

    color: #94a3b8;

    pointer-events: none;
}

.input-wrap > svg,
.select-wrap > svg:first-child {
    top: 50%;

    transform: translateY(-50%);
}

.textarea-wrap > svg {
    top: 16px;
}

.input-wrap input,
.select-wrap select,
.textarea-wrap textarea {
    width: 100%;

    box-sizing: border-box;

    color: #0f172a;
    background: #fff;

    border: 1px solid #dbe3ec;
    border-radius: 11px;

    outline: none;

    font-family: inherit;
    font-size: 12px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}

.input-wrap input,
.select-wrap select {
    height: 46px;

    padding: 0 42px;
}

.textarea-wrap textarea {
    min-height: 145px;

    padding:
        14px
        15px
        14px
        43px;

    resize: vertical;

    line-height: 1.6;
}

.input-wrap input::placeholder,
.textarea-wrap textarea::placeholder {
    color: #a8b3c2;
}

.input-wrap input:focus,
.select-wrap select:focus,
.textarea-wrap textarea:focus {
    border-color: #60a5fa;

    box-shadow:
        0 0 0 4px rgba(37, 99, 235, .08);
}

.select-wrap select {
    appearance: none;
    cursor: pointer;
}

.select-wrap .select-arrow {
    left: auto;
    right: 14px;

    color: #64748b;
}

.form-group small {
    display: block;

    margin-top: 7px;

    color: #94a3b8;

    font-size: 10px;
    line-height: 1.5;
}


/* =========================================================
   UPLOAD
========================================================= */

.upload-area {
    width: 100%;
    min-height: 205px;

    box-sizing: border-box;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;

    border: 1.5px dashed #cbd5e1;
    border-radius: 14px;

    background: #f8fafc;

    cursor: pointer;

    transition:
        border-color .25s ease,
        background .25s ease;
}

.upload-area:hover,
.upload-area.dragover {
    border-color: #60a5fa;
    background: #eff6ff;
}

.upload-content {
    width: 100%;

    text-align: center;
}

.upload-icon {
    width: 48px;
    height: 48px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto 12px;

    color: #2563eb;
    background: #dbeafe;

    border-radius: 13px;
}

.upload-icon svg {
    width: 23px;
}

.upload-content h3 {
    margin: 0;

    color: #334155;

    font-size: 13px;
    font-weight: 800;
}

.upload-content p {
    margin: 5px 0;

    color: #64748b;

    font-size: 11px;
}

.upload-content span {
    color: #94a3b8;

    font-size: 10px;
}


/* =========================================================
   PREVIEW
========================================================= */

.preview-area {
    width: 100%;

    display: flex;
    align-items: center;

    gap: 14px;

    box-sizing: border-box;
}

.preview-area[hidden] {
    display: none !important;
}

.preview-area img {
    width: 110px;
    height: 90px;

    display: block;

    object-fit: cover;

    background: #f1f5f9;

    border: 1px solid #dbe3ec;
    border-radius: 10px;
}

.preview-info {
    min-width: 0;
    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 5px;
}

.preview-info strong {
    overflow: hidden;

    color: #334155;

    font-size: 12px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.preview-info span {
    color: #94a3b8;

    font-size: 10px;
}

.remove-photo {
    width: 35px;
    height: 35px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #dc2626;
    background: #fef2f2;

    border: 1px solid #fecaca;
    border-radius: 9px;

    cursor: pointer;

    transition:
        color .2s ease,
        background .2s ease;
}

.remove-photo:hover {
    color: #fff;
    background: #dc2626;
}

.remove-photo svg {
    width: 16px;
}


/* =========================================================
   SIDEBAR USER
========================================================= */

.side-card-header {
    margin-bottom: 18px;
}

.user-profile {
    display: flex;
    align-items: center;

    gap: 11px;

    padding: 12px;

    background: #f8fafc;

    border: 1px solid #eef2f7;
    border-radius: 11px;
}

.avatar {
    width: 39px;
    height: 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    color: #2563eb;
    background: #dbeafe;

    border-radius: 50%;

    font-size: 14px;
    font-weight: 800;
}

.user-profile > div:last-child {
    min-width: 0;
}

.user-profile strong {
    display: block;

    overflow: hidden;

    color: #334155;

    font-size: 12px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-profile span {
    display: block;

    overflow: hidden;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 10px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-meta {
    display: flex;
    align-items: center;

    gap: 8px;

    margin-top: 11px;

    color: #64748b;

    font-size: 10px;
}

.profile-meta svg {
    width: 15px;

    color: #2563eb;
}


/* =========================================================
   STEPS
========================================================= */

.steps {
    display: flex;
    flex-direction: column;

    gap: 15px;
}

.step {
    display: grid;

    grid-template-columns: 27px 1fr;

    gap: 10px;

    position: relative;
}

.step:not(:last-child)::after {
    content: "";

    position: absolute;

    left: 13px;
    top: 28px;

    width: 1px;

    height: calc(100% + 3px);

    background: #dbeafe;
}

.step-number {
    width: 27px;
    height: 27px;

    display: flex;
    align-items: center;
    justify-content: center;

    position: relative;
    z-index: 1;

    color: #2563eb;

    background: #eff6ff;

    border: 1px solid #dbeafe;
    border-radius: 50%;

    font-size: 10px;
    font-weight: 800;
}

.step strong {
    display: block;

    color: #334155;

    font-size: 11px;
}

.step span {
    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 9px;
    line-height: 1.5;
}


/* =========================================================
   TIPS
========================================================= */

.tips-card {
    background: #eff6ff;

    border-color: #dbeafe;
}

.tips-title {
    display: flex;
    align-items: center;

    gap: 8px;

    color: #1d4ed8;

    font-size: 12px;
}

.tips-title svg {
    width: 17px;
}

.tips-card ul {
    margin: 12px 0 0;

    padding-left: 17px;
}

.tips-card li {
    margin: 6px 0;

    color: #475569;

    font-size: 10px;
    line-height: 1.5;
}


/* =========================================================
   BUTTON
========================================================= */

.form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 10px;

    margin-top: 22px;
}

.cancel-btn,
.submit-btn {
    min-height: 45px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 0 18px;

    border-radius: 11px;

    font-family: inherit;

    font-size: 12px;
    font-weight: 700;

    text-decoration: none;

    cursor: pointer;

    transition:
        transform .25s ease,
        background .25s ease,
        box-shadow .25s ease;
}

.cancel-btn {
    color: #475569;

    background: #fff;

    border: 1px solid #dbe3ec;
}

.cancel-btn:hover {
    background: #f8fafc;

    transform: translateY(-1px);
}

.submit-btn {
    min-width: 170px;

    color: #fff;

    background: #2563eb;

    border: 1px solid #2563eb;

    box-shadow:
        0 8px 20px rgba(37, 99, 235, .18);
}

.submit-btn:hover {
    background: #1d4ed8;

    border-color: #1d4ed8;

    transform: translateY(-2px);

    box-shadow:
        0 12px 25px rgba(37, 99, 235, .24);
}

.submit-btn:active {
    transform: translateY(0) scale(.98);
}

.submit-btn svg,
.cancel-btn svg {
    width: 17px;
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 950px) {

    .complaint-page {
        padding: 34px 18px 60px;
    }

    .form-grid {
        grid-template-columns:
            minmax(0, 1fr)
            300px;

        gap: 18px;
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 760px) {

    .complaint-page {
        padding: 28px 14px 50px;
    }

    .complaint-header {
        align-items: stretch;

        flex-direction: column;

        gap: 16px;
    }

    .complaint-heading h1 {
        font-size: 27px;
    }

    .complaint-heading p {
        max-width: none;
    }

    .history-btn {
        width: 100%;

        box-sizing: border-box;
    }

    .form-grid {
        grid-template-columns: 1fr;

        gap: 18px;
    }

    .form-side {
        position: static;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .cancel-btn,
    .submit-btn {
        width: 100%;

        box-sizing: border-box;
    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 430px) {

    .complaint-page {
        padding: 24px 12px 45px;
    }

    .complaint-heading {
        gap: 11px;
    }

    .heading-icon {
        width: 46px;
        height: 46px;

        border-radius: 12px;
    }

    .heading-icon svg {
        width: 21px;
    }

    .complaint-heading h1 {
        font-size: 23px;
    }

    .complaint-heading p {
        margin-top: 6px;

        font-size: 11px;
    }

    .form-card,
    .side-card {
        border-radius: 14px;
    }

    .form-card {
        padding: 17px;
    }

    .side-card {
        padding: 17px;
    }

    .upload-area {
        min-height: 180px;
    }

    .preview-area img {
        width: 82px;
        height: 68px;
    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {
        transition: none !important;
        animation: none !important;
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

        if (window.Lenis) {

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
           GSAP
        ===================================================== */

        if (window.gsap) {

            gsap.from(
                ".complaint-heading",
                {
                    y: 20,
                    opacity: 0,
                    duration: .65,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".history-btn",
                {
                    y: 15,
                    opacity: 0,
                    duration: .55,
                    delay: .05,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".form-card",
                {
                    y: 20,
                    opacity: 0,
                    duration: .6,
                    stagger: .1,
                    delay: .08,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".side-card",
                {
                    y: 20,
                    opacity: 0,
                    duration: .6,
                    stagger: .08,
                    delay: .15,
                    ease: "power3.out"
                }
            );


            gsap.from(
                ".form-actions",
                {
                    y: 12,
                    opacity: 0,
                    duration: .5,
                    delay: .25,
                    ease: "power2.out"
                }
            );

        }



        /* =====================================================
           CHARACTER COUNT
        ===================================================== */

        const description =
            document.getElementById(
                "deskripsi"
            );

        const charCount =
            document.getElementById(
                "charCount"
            );


        function updateCharCount() {

            if (
                !description ||
                !charCount
            ) {
                return;
            }

            charCount.textContent =
                description.value.length +
                " / 1000";

        }


        if (description) {

            description.addEventListener(
                "input",
                updateCharCount
            );

            updateCharCount();

        }



        /* =====================================================
           UPLOAD
        ===================================================== */

        const uploadArea =
            document.getElementById(
                "uploadArea"
            );

        const uploadContent =
            document.getElementById(
                "uploadContent"
            );

        const previewArea =
            document.getElementById(
                "previewArea"
            );

        const imagePreview =
            document.getElementById(
                "imagePreview"
            );

        const fileName =
            document.getElementById(
                "fileName"
            );

        const fileSize =
            document.getElementById(
                "fileSize"
            );

        const fileInput =
            document.getElementById(
                "foto"
            );

        const removePhoto =
            document.getElementById(
                "removePhoto"
            );



        /* FORMAT SIZE */

        function formatFileSize(bytes) {

            if (bytes < 1024) {

                return bytes + " B";

            }

            if (bytes < 1024 * 1024) {

                return (
                    bytes / 1024
                ).toFixed(1) +
                " KB";

            }

            return (
                bytes /
                (1024 * 1024)
            ).toFixed(1) +
            " MB";

        }



        /* SHOW PREVIEW */

        function showPreview(file) {

            if (!file) {
                return;
            }


            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                alert(
                    "Format foto harus JPG, JPEG, atau PNG."
                );

                fileInput.value = "";

                return;

            }


            if (
                file.size >
                5 * 1024 * 1024
            ) {

                alert(
                    "Ukuran foto maksimal 5 MB."
                );

                fileInput.value = "";

                return;

            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {


                    imagePreview.onload =
                        function () {

                            uploadContent.style.display =
                                "none";

                            previewArea.hidden =
                                false;

                        };


                    imagePreview.onerror =
                        function () {

                            alert(
                                "Foto tidak dapat ditampilkan. Silakan pilih foto lain."
                            );

                            fileInput.value =
                                "";

                            previewArea.hidden =
                                true;

                            uploadContent.style.display =
                                "";

                        };


                    imagePreview.src =
                        event.target.result;


                    fileName.textContent =
                        file.name;


                    fileSize.textContent =
                        formatFileSize(
                            file.size
                        );

                };


            reader.readAsDataURL(file);

        }



        /* CLICK UPLOAD */

        if (
            uploadArea &&
            fileInput
        ) {

            uploadArea.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target.closest(
                            "#removePhoto"
                        )
                    ) {
                        return;
                    }

                    fileInput.click();

                }
            );


            fileInput.addEventListener(
                "change",
                function () {

                    const file =
                        this.files[0];

                    if (file) {

                        showPreview(file);

                    }

                }
            );


            /* DRAG OVER */

            uploadArea.addEventListener(
                "dragover",
                function (event) {

                    event.preventDefault();

                    uploadArea.classList.add(
                        "dragover"
                    );

                }
            );


            /* DRAG LEAVE */

            uploadArea.addEventListener(
                "dragleave",
                function () {

                    uploadArea.classList.remove(
                        "dragover"
                    );

                }
            );


            /* DROP */

            uploadArea.addEventListener(
                "drop",
                function (event) {

                    event.preventDefault();

                    uploadArea.classList.remove(
                        "dragover"
                    );


                    const file =
                        event.dataTransfer
                            .files[0];


                    if (!file) {
                        return;
                    }


                    try {

                        const dataTransfer =
                            new DataTransfer();

                        dataTransfer.items.add(
                            file
                        );

                        fileInput.files =
                            dataTransfer.files;

                    } catch (error) {

                        console.warn(
                            error
                        );

                    }


                    showPreview(file);

                }
            );

        }



        /* =====================================================
           REMOVE FOTO
        ===================================================== */

        if (removePhoto) {

            removePhoto.addEventListener(
                "click",
                function (event) {

                    event.preventDefault();

                    event.stopPropagation();


                    fileInput.value = "";


                    imagePreview.removeAttribute(
                        "src"
                    );


                    previewArea.hidden =
                        true;


                    uploadContent.style.display =
                        "";

                }
            );

        }



        /* =====================================================
           FORM SUBMIT
        ===================================================== */

        const form =
            document.getElementById(
                "complaintForm"
            );

        const submitBtn =
            document.getElementById(
                "submitBtn"
            );


        if (
            form &&
            submitBtn
        ) {

            form.addEventListener(
                "submit",
                function () {

                    submitBtn.disabled =
                        true;

                    submitBtn.innerHTML = `
                        <i data-lucide="loader-circle"></i>
                        <span>Mengirim...</span>
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

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
requireRole('admin');
require_once dirname(__DIR__) . '/includes/monthly_report.php';

monthlyReportValidateRequest(['bulan', 'tahun', 'halaman']);
try {
    $period = monthlyReportPeriod($_GET);
} catch (InvalidArgumentException $exception) {
    monthlyReportBadRequest();
}
$pageInput = $_GET['halaman'] ?? '1';
if (!preg_match('/\A[1-9][0-9]{0,8}\z/', $pageInput)) {
    monthlyReportBadRequest();
}
$report = monthlyReportLoad($conn, $period);
$perPage = 50;
$lastPage = max(1, (int) ceil($report['summary']['total'] / $perPage));
$page = min((int) $pageInput, $lastPage);
$offset = ($page - 1) * $perPage;
$visibleRows = array_slice($report['rows'], $offset, $perPage);
$periodQuery = http_build_query(['bulan' => $period['month'], 'tahun' => $period['year']]);
$basePath = '../';
$activePage = 'laporan-bulanan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="../assets/img/logo-aduin.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bulanan - Admin ADUIN</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/monthly-report.css">
</head>
<body class="monthly-report-page">
<?php require dirname(__DIR__) . '/includes/navbar.php'; ?>
<main class="report-container">
    <header class="report-heading">
        <div>
            <p class="report-eyebrow">ADMIN ADUIN</p>
            <h1>Laporan Bulanan</h1>
            <p>Ringkasan pengaduan fasilitas kampus berdasarkan bulan pelaporan.</p>
        </div>
        <a class="report-back" href="dashboard.php">← Dashboard</a>
    </header>

    <section class="report-panel report-filters" aria-label="Filter periode laporan">
        <form method="get" action="laporan-bulanan.php" class="report-filter-form">
            <div>
                <label for="bulan">Bulan</label>
                <select id="bulan" name="bulan">
                    <?php foreach (monthlyReportMonths() as $number => $name): ?>
                        <option value="<?= $number ?>" <?= $number === $period['month'] ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tahun">Tahun</label>
                <input id="tahun" name="tahun" type="number" min="1000" max="9998" step="1" value="<?= $period['year'] ?>" required>
            </div>
            <button class="report-button report-button-primary" type="submit">Tampilkan laporan</button>
        </form>
        <div class="report-downloads" aria-label="Unduh seluruh laporan periode terpilih">
            <a class="report-button report-button-primary" href="unduh-laporan-bulanan.php?<?= e($periodQuery) ?>&amp;format=pdf">Unduh PDF</a>
            <a class="report-button report-button-secondary" href="unduh-laporan-bulanan.php?<?= e($periodQuery) ?>&amp;format=csv">Unduh CSV</a>
        </div>
    </section>

    <div class="report-period">
        <h2><?= e($period['label']) ?></h2>
        <p>Periode mengikuti tanggal pengaduan dibuat (WIB). Status dan petugas adalah data terkini saat laporan dibuka atau diunduh.</p>
    </div>
    <section class="report-stats" aria-label="Ringkasan pengaduan">
        <?php foreach (['total' => ['Total pengaduan', 'total'], 'Menunggu' => ['Menunggu', 'menunggu'], 'Diproses' => ['Diproses', 'diproses'], 'Selesai' => ['Selesai', 'selesai']] as $key => [$label, $class]): ?>
            <article class="report-stat report-stat-<?= $class ?>">
                <span><?= e($label) ?></span>
                <strong data-summary-<?= $class ?>="<?= $report['summary'][$key] ?>"><?= e(number_format($report['summary'][$key], 0, ',', '.')) ?></strong>
                <small>pengaduan</small>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="report-panel report-categories" aria-labelledby="category-title">
        <div class="report-section-title"><h2 id="category-title">Rekap berdasarkan kategori</h2><span><?= count($report['categories']) ?> kategori</span></div>
        <?php if ($report['categories']): ?>
            <ul class="report-category-list">
                <?php foreach ($report['categories'] as $category): ?>
                    <li><span><?= e($category['name']) ?></span><strong><?= e(number_format($category['total'], 0, ',', '.')) ?></strong></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="report-muted">Belum ada rekap kategori untuk periode ini.</p>
        <?php endif; ?>
    </section>

    <section class="report-panel report-details" aria-labelledby="detail-title">
        <div class="report-section-title"><h2 id="detail-title">Rincian pengaduan</h2><span><?= e(number_format($report['summary']['total'], 0, ',', '.')) ?> laporan</span></div>
        <?php if (!$visibleRows): ?>
            <div class="report-empty" role="status">
                <div class="report-empty-symbol" aria-hidden="true">∅</div>
                <h3>Tidak ada pengaduan pada periode ini</h3>
                <p>Belum ada laporan yang dibuat pada <?= e($period['label']) ?>. Anda tetap dapat mengunduh PDF atau CSV periode ini.</p>
            </div>
        <?php else: ?>
            <div class="report-table-scroll" role="region" aria-label="Tabel rincian laporan, geser untuk melihat semua kolom" tabindex="0">
                <table class="report-table">
                    <caption class="report-sr-only">Pengaduan dibuat pada <?= e($period['label']) ?>, status terkini</caption>
                    <thead><tr><th scope="col">Nomor laporan</th><th scope="col">Tanggal pengaduan</th><th scope="col">Kategori</th><th scope="col">Lokasi</th><th scope="col">Status terkini</th><th scope="col">Petugas</th></tr></thead>
                    <tbody>
                    <?php foreach ($visibleRows as $row): ?>
                        <tr>
                            <td class="report-number"><?= e($row['no_laporan']) ?></td>
                            <td><?= e(monthlyReportDate($row['tanggal_lapor'])) ?></td>
                            <td><?= e($row['nama_kategori']) ?></td>
                            <td><?= e($row['lokasi']) ?></td>
                            <td><span class="report-status report-status-<?= e(in_array($row['status'], ['Menunggu', 'Diproses', 'Selesai'], true) ? strtolower($row['status']) : 'other') ?>"><?= e($row['status']) ?></span></td>
                            <td><?= e($row['nama_petugas'] ?? 'Belum ditugaskan') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav class="report-pagination" aria-label="Halaman rincian laporan">
                <p>Menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $report['summary']['total']) ?> dari <?= $report['summary']['total'] ?> laporan. Unduhan mencakup seluruh periode.</p>
                <div>
                    <?php if ($page > 1): ?><a class="report-button report-button-secondary" href="?<?= e($periodQuery) ?>&amp;halaman=<?= $page - 1 ?>" rel="prev">Sebelumnya</a><?php endif; ?>
                    <span>Halaman <?= $page ?> / <?= $lastPage ?></span>
                    <?php if ($page < $lastPage): ?><a class="report-button report-button-secondary" href="?<?= e($periodQuery) ?>&amp;halaman=<?= $page + 1 ?>" rel="next">Berikutnya</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
    <p class="report-footnote">ADUIN · Sistem Informasi Pengaduan Fasilitas Kampus · Laporan khusus admin</p>
</main>
<script nonce="<?= e(cspNonce()) ?>" src="../assets/js/script.js"></script>
</body>
</html>

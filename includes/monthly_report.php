<?php
declare(strict_types=1);

// Shared by the admin page and both exports. No connection or authentication bypass.
function monthlyReportBadRequest(): void
{
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Parameter laporan tidak valid. Gunakan bulan 1–12 dan tahun 1000–9998.');
}

function monthlyReportValidateRequest(array $allowed): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit('Metode tidak diizinkan.');
    }
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $allowed, true) || !is_string($value) || str_contains($value, "\0")) {
            monthlyReportBadRequest();
        }
    }
}

function monthlyReportMonths(): array
{
    return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
}

function monthlyReportPeriod(array $input): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $month = $input['bulan'] ?? $now->format('n');
    $year = $input['tahun'] ?? $now->format('Y');
    if (!is_string($month) || !is_string($year)
        || !preg_match('/\A(?:0?[1-9]|1[0-2])\z/', $month)
        || !preg_match('/\A[1-9][0-9]{3}\z/', $year) || (int) $year > 9998) {
        throw new InvalidArgumentException('Invalid monthly report period.');
    }
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', (int) $year, (int) $month), new DateTimeZone('Asia/Jakarta'));
    return [
        'month' => (int) $month, 'year' => (int) $year,
        'label' => monthlyReportMonths()[(int) $month] . ' ' . $year,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $start->modify('+1 month')->format('Y-m-d H:i:s'),
    ];
}

function monthlyReportLoad(mysqli $conn, array $period): array
{
    // One consistent SELECT result supplies every count and detail. The half-open
    // range uses the creation timestamp, without wrapping the indexed column.
    $result = dbQuery($conn,
        'SELECT p.no_laporan, p.tanggal_lapor, p.lokasi, p.status,
                p.id_kategori, k.nama_kategori, u.nama AS nama_petugas
         FROM pengaduan p
         LEFT JOIN kategori k ON k.id_kategori = p.id_kategori
         LEFT JOIN users u ON u.id_user = p.id_petugas
         WHERE p.tanggal_lapor >= ? AND p.tanggal_lapor < ?
         ORDER BY p.tanggal_lapor ASC, p.id_pengaduan ASC',
        'ss', [$period['start'], $period['end']]);
    $rows = [];
    $summary = ['total' => 0, 'Menunggu' => 0, 'Diproses' => 0, 'Selesai' => 0];
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $row['nama_kategori'] = $row['nama_kategori'] ?? 'Kategori tidak tersedia';
        if (trim((string) ($row['nama_petugas'] ?? '')) === '') {
            $row['nama_petugas'] = null;
        }
        $rows[] = $row;
        ++$summary['total'];
        if (in_array($row['status'], ['Menunggu', 'Diproses', 'Selesai'], true)) {
            ++$summary[$row['status']];
        }
        $key = (string) $row['id_kategori'];
        if (!isset($categories[$key])) {
            $categories[$key] = ['name' => $row['nama_kategori'], 'total' => 0];
        }
        ++$categories[$key]['total'];
    }
    $result->free();
    $categories = array_values($categories);
    usort($categories, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['name'], $b['name']));
    return ['period' => $period, 'summary' => $summary, 'categories' => $categories, 'rows' => $rows];
}

function monthlyReportDate(string $value): string
{
    // MySQL connection and bootstrap use Asia/Jakarta; no timezone reinterpretation.
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Jakarta'));
    return $date ? $date->format('d/m/Y H:i') . ' WIB' : $value;
}

function monthlyReportCsvCell($value): string
{
    $value = (string) ($value ?? '');
    // Quote dangerous spreadsheet prefixes even after Unicode/control whitespace.
    // fputcsv handles separators, quotes and embedded newlines separately.
    if (preg_match('/\A[\p{Z}\p{C}\s]*[=+@-]/u', $value)
        || preg_match('/\A[\p{Z}\p{C}\s]*[\t\r\n]/u', $value)) {
        return "'" . $value;
    }
    return $value;
}

function monthlyReportCsv(array $report): string
{
    $stream = fopen('php://memory', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('Report buffer unavailable.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    $write = static function (array $cells) use ($stream): void {
        if (fputcsv($stream, array_map('monthlyReportCsvCell', $cells), ';', '"', '', "\r\n") === false) {
            throw new RuntimeException('Report encoding failed.');
        }
    };
    $write(['ADUIN — Sistem Informasi Pengaduan Fasilitas Kampus']);
    $write(['Laporan Pengaduan Bulanan', $report['period']['label']]);
    $write(['Dasar periode', 'Tanggal pengaduan dibuat (WIB)']);
    $write(['Status', 'Status terkini saat laporan diunduh']);
    $write([]);
    $write(['Ringkasan', 'Jumlah']);
    foreach (['total' => 'Total pengaduan', 'Menunggu' => 'Menunggu', 'Diproses' => 'Diproses', 'Selesai' => 'Selesai'] as $key => $label) {
        $write([$label, $report['summary'][$key]]);
    }
    $write([]);
    $write(['Kategori', 'Jumlah']);
    foreach ($report['categories'] as $category) {
        $write([$category['name'], $category['total']]);
    }
    if (!$report['rows']) {
        $write(['Tidak ada pengaduan pada periode ini.']);
    }
    $write([]);
    $write(['Nomor laporan', 'Tanggal pengaduan (WIB)', 'Kategori', 'Lokasi', 'Status terkini', 'Petugas']);
    foreach ($report['rows'] as $row) {
        $write([$row['no_laporan'], monthlyReportDate($row['tanggal_lapor']), $row['nama_kategori'],
            $row['lokasi'], $row['status'], $row['nama_petugas'] ?? 'Belum ditugaskan']);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    if ($csv === false) {
        throw new RuntimeException('Report buffer unavailable.');
    }
    return $csv;
}

function monthlyReportDownload(string $bytes, string $format, array $period): void
{
    // Called only after admin authorization and successful complete generation.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $filename = sprintf('ADUIN-Laporan-Bulanan-%04d-%02d.%s', $period['year'], $period['month'], $format);
    header('Content-Type: ' . ($format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($bytes));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $bytes;
    }
}

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
requireRole('admin');
require_once dirname(__DIR__) . '/includes/monthly_report.php';

monthlyReportValidateRequest(['bulan', 'tahun', 'format']);
$format = $_GET['format'] ?? 'pdf';
if (!in_array($format, ['pdf', 'csv'], true)) {
    monthlyReportBadRequest();
}
try {
    $period = monthlyReportPeriod($_GET);
} catch (InvalidArgumentException $exception) {
    monthlyReportBadRequest();
}
$report = monthlyReportLoad($conn, $period);
// Release the session lock while building potentially lengthy exports.
session_write_close();
if ($format === 'pdf') {
    require_once dirname(__DIR__) . '/services/monthly_report_pdf.php';
    $bytes = monthlyReportPdf($report);
} else {
    $bytes = monthlyReportCsv($report);
}
monthlyReportDownload($bytes, $format, $period);

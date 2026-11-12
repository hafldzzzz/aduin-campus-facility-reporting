<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/monthly_report.php';
require_once dirname(__DIR__) . '/services/monthly_report_pdf.php';
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    ++$checks;
}
$period = monthlyReportPeriod(['bulan' => '12', 'tahun' => '2026']);
check($period['end'] === '2027-01-01 00:00:00', 'Batas akhir Desember');
$leap = monthlyReportPeriod(['bulan' => '2', 'tahun' => '2028']);
check($leap['start'] === '2028-02-01 00:00:00' && $leap['end'] === '2028-03-01 00:00:00', 'Bulan kabisat');
foreach ([['bulan'=>'13','tahun'=>'2026'], ['bulan'=>['1'],'tahun'=>'2026'], ['bulan'=>'1','tahun'=>'9999'], ['bulan'=>'1','tahun'=>'2026x']] as $invalid) {
    try { monthlyReportPeriod($invalid); check(false, 'Input invalid diterima'); }
    catch (InvalidArgumentException $expected) { ++$checks; }
}
foreach (['=SUM(1;2)', '+1+2', '-1+2', '@SUM(A1)', "\t=1", "\u{2003}=1"] as $value) {
    check(str_starts_with(monthlyReportCsvCell($value), "'"), 'Perlindungan formula CSV');
}
$report = ['period' => $period, 'summary' => ['total'=>1,'Menunggu'=>1,'Diproses'=>0,'Selesai'=>0],
    'categories' => [['name'=>'Kategori Simulasi','total'=>1]],
    'rows' => [['no_laporan'=>'DEMO-CSV-001','tanggal_lapor'=>'2026-12-01 09:00:00',
        'nama_kategori'=>'Kategori Simulasi','lokasi'=>'Ruang "Demo"; aman','status'=>'Menunggu','nama_petugas'=>null]]];
$csv = monthlyReportCsv($report);
check(str_starts_with($csv, "\xEF\xBB\xBF"), 'BOM UTF-8 CSV');
check(str_contains($csv, 'DEMO-CSV-001') && str_contains($csv, 'Belum ditugaskan'), 'Isi CSV');
$stream = fopen('php://memory', 'w+'); fwrite($stream, $csv); rewind($stream);
$last = null;
while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) { $last = $row; }
fclose($stream);
check(count($last) === 6 && $last[3] === 'Ruang "Demo"; aman', 'CSV round-trip separator dan kutip');
$pdf = monthlyReportPdf($report);
check(str_starts_with($pdf, '%PDF-') && str_contains($pdf, '%%EOF'), 'PDF valid');
check(str_contains($pdf, '/FontFile2'), 'Font Unicode tertanam');
$report['rows'] = []; $report['categories'] = [];
$report['summary'] = ['total'=>0,'Menunggu'=>0,'Diproses'=>0,'Selesai'=>0];
check(str_contains(monthlyReportCsv($report), 'Tidak ada pengaduan'), 'CSV periode kosong');
check(str_starts_with(monthlyReportPdf($report), '%PDF-'), 'PDF periode kosong');
echo "PASS: {$checks} pemeriksaan laporan; tanpa koneksi database atau berkas keluaran.\n";

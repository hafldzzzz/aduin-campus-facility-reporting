<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/pdf/tfpdf.php';

/**
 * Builds an admin report in memory. Authentication and response headers belong
 * to the download controller; this helper never reads a database or writes files.
 * All text is literal PDF text; no HTML, remote images, or URL fetching is used.
 */
function monthlyReportPdf(array $report): string
{
    $pdf = new AduinMonthlyReportPdf($report);
    $pdf->renderReport();
    return $pdf->Output('S');
}

final class AduinMonthlyReportPdf extends tFPDF
{
    private array $report;
    private string $generatedAt;
    private string $section = '';
    private array $widths = [];
    private array $labels = [];
    private array $characterWidths = [];
    private const LEFT = 12.0;
    private const WIDTH = 273.0;
    private const BOTTOM = 192.0;
    private const LINE = 4.4;
    private const PADDING = 2.0;

    public function __construct(array $report)
    {
        parent::__construct('L', 'mm', 'A4');
        // Ignore any application-wide core-font path. Only these vendored fonts
        // are used, and their prebuilt caches are portable across installations.
        $this->fontpath = __DIR__ . '/../includes/pdf/font/';
        $this->report = $report;
        $this->generatedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
            ->format('d/m/Y H:i') . ' WIB';
        $this->SetMargins(self::LEFT, 12, self::LEFT);
        $this->SetAutoPageBreak(false);
        $this->AliasNbPages();
        $this->AddFont('ADUIN', '', 'DejaVuSansCondensed.ttf', true);
        $this->AddFont('ADUIN', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
        $this->SetTitle('ADUIN - Laporan Pengaduan Bulanan', true);
        $this->SetAuthor('ADUIN', true);
        $this->SetCreator('ADUIN - tFPDF 1.34', true);
    }

    public function Header(): void
    {
        $this->SetFillColor(37, 99, 235);
        $this->Rect(0, 0, $this->GetPageWidth(), 2.5, 'F');
        $this->SetTextColor(37, 99, 235);
        $this->SetFont('ADUIN', 'B', 21);
        $this->SetXY(self::LEFT, 9);
        $this->Cell(42, 10, 'ADUIN');
        $this->SetFont('ADUIN', '', 9);
        $this->SetTextColor(71, 85, 105);
        $this->SetXY(58, 10);
        $this->Cell(0, 5, 'Sistem Informasi Pengaduan Fasilitas Kampus');
        $this->SetXY(58, 15);
        $this->Cell(0, 5, 'Laporan Pengaduan Bulanan | Khusus Admin');
        $this->SetDrawColor(203, 213, 225);
        $this->Line(self::LEFT, 23, self::LEFT + self::WIDTH, 23);
        $this->SetXY(self::LEFT, 26);
        $this->SetFont('ADUIN', 'B', 12);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 7, 'Periode: ' . $this->cleanText((string) ($this->report['period']['label'] ?? '')));
        $this->SetXY(self::LEFT, 34);
        $this->SetFont('ADUIN', '', 8);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(0, 5, 'Berdasarkan tanggal pengaduan dibuat. Status dan petugas sesuai data saat laporan diunduh.');
        $this->SetY(43);
        if ($this->section !== '') {
            $this->sectionHeading($this->section . ' (lanjutan)');
            $this->tableHeader();
        }
    }

    public function Footer(): void
    {
        $this->SetDrawColor(203, 213, 225);
        $this->Line(self::LEFT, 197, self::LEFT + self::WIDTH, 197);
        $this->SetY(199);
        $this->SetTextColor(100, 116, 139);
        $this->SetFont('ADUIN', '', 7);
        $this->Cell(140, 4, 'ADUIN | Dokumen internal admin | Diunduh: ' . $this->generatedAt);
        $this->Cell(133, 4, 'Halaman ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
    }

    public function renderReport(): void
    {
        $this->AddPage();
        $this->sectionHeading('Ringkasan Pengaduan');
        $summary = $this->report['summary'] ?? [];
        $cards = [
            ['Total pengaduan', (int) ($summary['total'] ?? 0)],
            ['Menunggu', (int) ($summary['Menunggu'] ?? 0)],
            ['Diproses', (int) ($summary['Diproses'] ?? 0)],
            ['Selesai', (int) ($summary['Selesai'] ?? 0)],
        ];
        $y = $this->GetY();
        foreach ($cards as $index => [$label, $total]) {
            $x = self::LEFT + $index * 69.25;
            $this->SetFillColor(239, 246, 255);
            $this->SetDrawColor(219, 234, 254);
            $this->Rect($x, $y, 65.25, 20, 'DF');
            $this->SetXY($x + 3, $y + 2);
            $this->SetTextColor(71, 85, 105);
            $this->SetFont('ADUIN', '', 8.5);
            $this->Cell(59, 5, $label);
            $this->SetXY($x + 3, $y + 8);
            $this->SetFont('ADUIN', 'B', 16);
            $this->SetTextColor(15, 23, 42);
            $this->Cell(59, 9, number_format($total, 0, ',', '.'));
        }
        $this->SetY($y + 26);
        $this->startTable('Rekap Berdasarkan Kategori', [228, 45], ['Kategori', 'Jumlah pengaduan']);
        $categories = $this->report['categories'] ?? [];
        if ($categories === []) {
            $this->drawRow(['Tidak ada pengaduan pada periode ini.', '0']);
        } else {
            foreach ($categories as $index => $category) {
                $this->drawRow([
                    (string) ($category['name'] ?? '-'),
                    number_format((int) ($category['total'] ?? 0), 0, ',', '.'),
                ], (int) $index);
            }
        }
        $this->section = '';
        $this->SetY($this->GetY() + 7);
        $this->startTable(
            'Rincian Pengaduan',
            [33, 31, 43, 73, 28, 65],
            ['Nomor laporan', 'Tanggal pengaduan', 'Kategori', 'Lokasi', 'Status terkini', 'Petugas']
        );
        $rows = $this->report['rows'] ?? [];
        if ($rows === []) {
            $this->widths = [self::WIDTH];
            $this->drawRow(['Tidak ada laporan pengaduan pada periode ' . (string) ($this->report['period']['label'] ?? '') . '.']);
        } else {
            foreach ($rows as $index => $row) {
                $this->drawRow([
                    (string) ($row['no_laporan'] ?? '-'),
                    $this->formatDate((string) ($row['tanggal_lapor'] ?? '')),
                    (string) ($row['nama_kategori'] ?? '-'),
                    (string) ($row['lokasi'] ?? '-'),
                    (string) ($row['status'] ?? '-'),
                    trim((string) ($row['nama_petugas'] ?? '')) !== '' ? (string) $row['nama_petugas'] : 'Belum ditugaskan',
                ], (int) $index);
            }
        }
        $this->section = '';
    }

    private function sectionHeading(string $title): void
    {
        $this->SetX(self::LEFT);
        $this->SetFont('ADUIN', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(self::WIDTH, 7, $this->cleanText($title), 0, 1);
    }

    private function startTable(string $section, array $widths, array $labels): void
    {
        $this->section = '';
        if ($this->GetY() > self::BOTTOM - 24) {
            $this->AddPage();
        }
        $this->section = $section;
        $this->widths = $widths;
        $this->labels = $labels;
        $this->sectionHeading($section);
        $this->tableHeader();
    }

    private function tableHeader(): void
    {
        $this->SetX(self::LEFT);
        $this->SetFont('ADUIN', 'B', 8);
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(37, 99, 235);
        foreach ($this->widths as $index => $width) {
            $this->Cell($width, 8, $this->labels[$index], 1, 0, 'L', true);
        }
        $this->Ln(8);
    }

    /** Split even a single giant database cell across pages without truncation. */
    private function drawRow(array $cells, int $rowIndex = 0): void
    {
        $this->SetFont('ADUIN', '', 8.5);
        $lines = [];
        foreach ($cells as $index => $cell) {
            $lines[] = $this->wrapText($this->cleanText($cell), $this->widths[$index] - 2 * self::PADDING);
        }
        $totalLines = max(array_map('count', $lines));
        $offset = 0;
        while ($offset < $totalLines) {
            $availableLines = (int) floor((self::BOTTOM - $this->GetY() - 2 * self::PADDING) / self::LINE);
            // Keep ordinary rows together. Split only rows taller than a page.
            $fullPageLines = (int) floor((self::BOTTOM - 58 - 2 * self::PADDING) / self::LINE);
            if ($availableLines < 1 || ($offset === 0 && $totalLines <= $fullPageLines && $totalLines > $availableLines)) {
                $this->AddPage();
                $this->SetFont('ADUIN', '', 8.5);
                $availableLines = (int) floor((self::BOTTOM - $this->GetY() - 2 * self::PADDING) / self::LINE);
            }
            $count = min($availableLines, $totalLines - $offset);
            $height = $count * self::LINE + 2 * self::PADDING;
            $x = self::LEFT;
            $y = $this->GetY();
            $this->SetDrawColor(226, 232, 240);
            $this->SetFillColor(...($rowIndex % 2 === 0 ? [255, 255, 255] : [248, 250, 252]));
            $this->SetTextColor(30, 41, 59);
            foreach ($this->widths as $index => $width) {
                $this->Rect($x, $y, $width, $height, 'DF');
                foreach (array_slice($lines[$index], $offset, $count) as $lineIndex => $line) {
                    $this->SetXY($x + self::PADDING, $y + self::PADDING + $lineIndex * self::LINE);
                    $this->Cell($width - 2 * self::PADDING, self::LINE, $line);
                }
                $x += $width;
            }
            $offset += $count;
            $this->SetXY(self::LEFT, $y + $height);
            if ($offset < $totalLines) {
                $this->AddPage();
                $this->SetFont('ADUIN', '', 8.5);
            }
        }
    }

    /** Width-aware UTF-8 wrapping, including long words and literal newlines. */
    private function wrapText(string $text, float $maxWidth): array
    {
        $result = [];
        foreach (explode("\n", $text) as $paragraph) {
            $line = [];
            $lineWidth = 0.0;
            $lastSpace = null;
            foreach (mb_str_split($paragraph, 1, 'UTF-8') as $character) {
                $width = $this->characterWidths[$character] ??= $this->GetStringWidth($character);
                while ($line !== [] && $lineWidth + $width > $maxWidth) {
                    if ($lastSpace !== null && $lastSpace > 0) {
                        $result[] = rtrim(implode('', array_slice($line, 0, $lastSpace)));
                        $line = array_slice($line, $lastSpace + 1);
                        $lineWidth = 0.0;
                        foreach ($line as $leftover) {
                            $lineWidth += $this->characterWidths[$leftover];
                        }
                    } else {
                        $result[] = implode('', $line);
                        $line = [];
                        $lineWidth = 0.0;
                    }
                    $lastSpace = null;
                }
                $line[] = $character;
                $lineWidth += $width;
                if ($character === ' ') {
                    $lastSpace = count($line) - 1;
                }
            }
            $result[] = implode('', $line);
        }
        return $result === [] ? [''] : $result;
    }

    /** Unsupported glyphs are printed as Unicode code points, never silently lost. */
    private function cleanText(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $normalFont = $this->fonts['aduin']['cw'];
        return preg_replace_callback('/[^\x20-\x7E\n]/u', static function (array $match) use ($normalFont): string {
            $codepoint = mb_ord($match[0], 'UTF-8');
            if ($codepoint <= 65535 && isset($normalFont[$codepoint * 2 + 1])
                && ($normalFont[$codepoint * 2] !== "\0" || $normalFont[$codepoint * 2 + 1] !== "\0")) {
                return $match[0];
            }
            return '[U+' . strtoupper(str_pad(dechex($codepoint), 4, '0', STR_PAD_LEFT)) . ']';
        }, $text) ?? '';
    }

    private function formatDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Jakarta'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return $value !== '' ? $value : '-';
        }
        return $date->format('d/m/Y H:i') . ' WIB';
    }
}

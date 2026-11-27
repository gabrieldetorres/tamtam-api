<?php

namespace App\Services;

/**
 * Pure-PHP PDF 1.4 generator for TamTam 30-day sales summary reports.
 *
 * Uses built-in PDF Helvetica / Helvetica-Bold fonts so PDFs can be generated
 * without external binaries or Composer packages.
 */
class SalesReportPdfGenerator
{
    private const PAGE_WIDTH = 595.28;  // A4 width in points
    private const PAGE_HEIGHT = 841.89; // A4 height in points
    private const MARGIN_X = 40.0;
    private const MARGIN_TOP = 42.0;
    private const MARGIN_BOTTOM = 48.0;

    /** @var array<int, string> */
    private array $pages = [];
    private string $stream = '';
    private float $y = 0.0;

    /**
     * Render a ReportService::summary() payload (and optional itemized CSV rows) into a valid PDF binary string.
     *
     * @param  array{
     *     from: string,
     *     to: string,
     *     timezone: string,
     *     sales: string,
     *     orders: int,
     *     avg_order: string,
     *     voided: int,
     *     top_sellers: array<int, array{label?: string, name?: string, size?: ?string, qty: int, revenue: string}>,
     *     daily: array<int, array{date: string, orders: int, sales: string}>
     * }  $summary
     * @param  array<int, array<int, string|int|float>>  $csvRows
     */
    public function generate(array $summary, string $currency = 'PHP', ?string $generatedAt = null, array $csvRows = []): string
    {
        $this->pages = [];
        $this->startPage();

        $appName = (string) config('app.name', 'TamTam POS');
        $generated = $generatedAt ?? now((string) ($summary['timezone'] ?? 'UTC'))->format('Y-m-d H:i');
        $contentWidth = self::PAGE_WIDTH - (self::MARGIN_X * 2);

        // Header banner
        $this->drawRect(self::MARGIN_X, $this->y, $contentWidth, 56, [0.11, 0.16, 0.24], true);
        $this->drawText(self::MARGIN_X + 14, $this->y + 22, $appName.' - 30-Day Sales Report', 15, true, [1.0, 1.0, 1.0]);
        $this->drawText(
            self::MARGIN_X + 14,
            $this->y + 40,
            sprintf('Period: %s to %s (%s)   |   Generated: %s', $summary['from'], $summary['to'], $summary['timezone'], $generated),
            8.5,
            false,
            [0.82, 0.87, 0.94]
        );
        $this->y += 72;

        // KPI Summary Cards
        $this->drawSectionTitle('Summary Metrics');
        $cards = [
            ['Total Sales', $this->money($summary['sales'], $currency)],
            ['Paid Orders', number_format((int) $summary['orders'])],
            ['Average Order', $this->money($summary['avg_order'], $currency)],
            ['Voided Orders', number_format((int) $summary['voided'])],
        ];
        $gap = 10.0;
        $cardW = ($contentWidth - ($gap * 3)) / 4;
        $cardH = 48.0;

        foreach ($cards as $index => [$label, $value]) {
            $x = self::MARGIN_X + ($index * ($cardW + $gap));
            $this->drawRect($x, $this->y, $cardW, $cardH, [0.96, 0.97, 0.98], true);
            $this->drawRect($x, $this->y, $cardW, $cardH, [0.85, 0.88, 0.92], false);
            $this->drawText($x + 10, $this->y + 16, $label, 8.5, false, [0.39, 0.45, 0.54]);
            $this->drawText($x + 10, $this->y + 35, $value, 12.5, true, [0.09, 0.13, 0.20]);
        }
        $this->y += $cardH + 22;

        // Top Sellers
        $this->drawSectionTitle('Top Selling Items');
        $topSellers = $summary['top_sellers'] ?? [];
        if ($topSellers === []) {
            $this->drawText(self::MARGIN_X, $this->y + 12, 'No paid sales recorded in this period.', 9.5, false, [0.42, 0.47, 0.55]);
            $this->y += 26;
        } else {
            $this->drawTopSellersHeader();
            foreach ($topSellers as $i => $row) {
                $this->ensureSpace(20, fn () => $this->drawTopSellersHeader());
                $this->drawTopSellersRow($i, $row, $currency);
            }
            $this->y += 16;
        }

        // Daily Breakdown
        $this->ensureSpace(50);
        $this->drawSectionTitle('Daily Sales Breakdown');
        $daily = $summary['daily'] ?? [];
        if ($daily === []) {
            $this->drawText(self::MARGIN_X, $this->y + 12, 'No daily breakdown available.', 9.5, false, [0.42, 0.47, 0.55]);
            $this->y += 26;
        } else {
            $this->drawDailyHeader();
            foreach ($daily as $i => $day) {
                $this->ensureSpace(19, fn () => $this->drawDailyHeader());
                $this->drawDailyRow($i, $day, $currency);
            }
        }

        // Itemized CSV Sales Rows
        if ($csvRows !== []) {
            $this->y += 16;
            $this->ensureSpace(55);
            $this->drawSectionTitle('Itemized Sales Report (CSV)');
            $this->drawCsvHeader();
            foreach ($csvRows as $i => $row) {
                $this->ensureSpace(18, fn () => $this->drawCsvHeader());
                $this->drawCsvRow($i, $row);
            }
        }

        $this->pages[] = $this->stream;

        return $this->compilePdf($appName);
    }

    private function drawSectionTitle(string $title): void
    {
        $this->ensureSpace(34);
        $this->drawText(self::MARGIN_X, $this->y + 11, $title, 11.5, true, [0.11, 0.16, 0.24]);
        $this->drawLine(self::MARGIN_X, $this->y + 16, self::PAGE_WIDTH - self::MARGIN_X, $this->y + 16, [0.82, 0.85, 0.90], 0.75);
        $this->y += 24;
    }

    private function drawTopSellersHeader(): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        $this->drawRect(self::MARGIN_X, $this->y, $w, 20, [0.91, 0.93, 0.96], true);
        $this->drawText(self::MARGIN_X + 8, $this->y + 13, 'Item', 9, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 110, $this->y + 13, 'Qty Sold', 9, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 8, $this->y + 13, 'Revenue', 9, true, [0.15, 0.20, 0.28]);
        $this->y += 20;
    }

    private function drawTopSellersRow(int $index, array $row, string $currency): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        if ($index % 2 === 1) {
            $this->drawRect(self::MARGIN_X, $this->y, $w, 18, [0.98, 0.98, 0.99], true);
        }

        $label = (string) ($row['label'] ?? $row['name'] ?? 'Item');
        $label = $this->truncate($label, 52);

        $this->drawText(self::MARGIN_X + 8, $this->y + 12, $label, 9, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 110, $this->y + 12, number_format((int) ($row['qty'] ?? 0)), 9, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 8, $this->y + 12, $this->money((string) ($row['revenue'] ?? '0.00'), $currency), 9, false, [0.15, 0.20, 0.28]);
        $this->drawLine(self::MARGIN_X, $this->y + 18, self::MARGIN_X + $w, $this->y + 18, [0.92, 0.94, 0.96], 0.4);
        $this->y += 18;
    }

    private function drawDailyHeader(): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        $this->drawRect(self::MARGIN_X, $this->y, $w, 20, [0.91, 0.93, 0.96], true);
        $this->drawText(self::MARGIN_X + 8, $this->y + 13, 'Date', 9, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 125, $this->y + 13, 'Orders', 9, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 8, $this->y + 13, 'Sales', 9, true, [0.15, 0.20, 0.28]);
        $this->y += 20;
    }

    private function drawDailyRow(int $index, array $day, string $currency): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        if ($index % 2 === 1) {
            $this->drawRect(self::MARGIN_X, $this->y, $w, 17, [0.98, 0.98, 0.99], true);
        }

        $this->drawText(self::MARGIN_X + 8, $this->y + 12, (string) ($day['date'] ?? ''), 8.8, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 125, $this->y + 12, number_format((int) ($day['orders'] ?? 0)), 8.8, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 8, $this->y + 12, $this->money((string) ($day['sales'] ?? '0.00'), $currency), 8.8, false, [0.15, 0.20, 0.28]);
        $this->drawLine(self::MARGIN_X, $this->y + 17, self::MARGIN_X + $w, $this->y + 17, [0.92, 0.94, 0.96], 0.4);
        $this->y += 17;
    }

    private function drawCsvHeader(): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        $this->drawRect(self::MARGIN_X, $this->y, $w, 20, [0.91, 0.93, 0.96], true);
        $this->drawText(self::MARGIN_X + 6, $this->y + 13, 'Date', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + 104, $this->y + 13, 'Order #', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawText(self::MARGIN_X + 114, $this->y + 13, 'Item', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawText(self::MARGIN_X + 300, $this->y + 13, 'Size', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + 378, $this->y + 13, 'Qty', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + 444, $this->y + 13, 'Price', 8.2, true, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 6, $this->y + 13, 'Total', 8.2, true, [0.15, 0.20, 0.28]);
        $this->y += 20;
    }

    /** @param array<int|string, string|int|float|null> $row */
    private function drawCsvRow(int $index, array $row): void
    {
        $w = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        if ($index % 2 === 1) {
            $this->drawRect(self::MARGIN_X, $this->y, $w, 16, [0.98, 0.98, 0.99], true);
        }

        $values = array_values($row);
        $date = $this->truncate((string) ($values[0] ?? ''), 10);
        $orderNo = (string) ($values[1] ?? '');
        $item = $this->truncate((string) ($values[2] ?? ''), 32);
        $sizeRaw = trim((string) ($values[3] ?? ''));
        $size = $this->truncate($sizeRaw !== '' ? $sizeRaw : '-', 9);
        $qty = number_format((int) ($values[4] ?? 0));
        $price = number_format((float) ($values[5] ?? 0), 2, '.', ',');
        $lineTotal = number_format((float) ($values[6] ?? 0), 2, '.', ',');

        $this->drawText(self::MARGIN_X + 6, $this->y + 11.5, $date, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + 104, $this->y + 11.5, $orderNo, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawText(self::MARGIN_X + 114, $this->y + 11.5, $item, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawText(self::MARGIN_X + 300, $this->y + 11.5, $size, 8.0, false, [0.35, 0.41, 0.50]);
        $this->drawRightText(self::MARGIN_X + 378, $this->y + 11.5, $qty, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + 444, $this->y + 11.5, $price, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawRightText(self::MARGIN_X + $w - 6, $this->y + 11.5, $lineTotal, 8.0, false, [0.15, 0.20, 0.28]);
        $this->drawLine(self::MARGIN_X, $this->y + 16, self::MARGIN_X + $w, $this->y + 16, [0.92, 0.94, 0.96], 0.4);
        $this->y += 16;
    }

    private function ensureSpace(float $needed, ?callable $onNewPage = null): void
    {
        if (($this->y + $needed) <= (self::PAGE_HEIGHT - self::MARGIN_BOTTOM)) {
            return;
        }

        $this->pages[] = $this->stream;
        $this->startPage();

        if ($onNewPage !== null) {
            $onNewPage();
        }
    }

    private function startPage(): void
    {
        $this->stream = '';
        $this->y = self::MARGIN_TOP;
    }

    private function drawText(float $x, float $topY, string $text, float $size, bool $bold, array $rgb): void
    {
        $font = $bold ? '/F2' : '/F1';
        $pdfY = self::PAGE_HEIGHT - $topY;
        $escaped = $this->escapePdfText($text);
        $this->stream .= sprintf(
            "BT %s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n",
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $pdfY,
            $escaped
        );
    }

    private function drawRightText(float $rightX, float $topY, string $text, float $size, bool $bold, array $rgb): void
    {
        $width = $this->estimateTextWidth($text, $size, $bold);
        $this->drawText($rightX - $width, $topY, $text, $size, $bold, $rgb);
    }

    private function drawRect(float $x, float $topY, float $w, float $h, array $rgb, bool $fill): void
    {
        $pdfY = self::PAGE_HEIGHT - $topY - $h;
        if ($fill) {
            $this->stream .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $pdfY, $w, $h);
        } else {
            $this->stream .= sprintf("0.6 w %.3F %.3F %.3F RG %.2F %.2F %.2F %.2F re S\n", $rgb[0], $rgb[1], $rgb[2], $x, $pdfY, $w, $h);
        }
    }

    private function drawLine(float $x1, float $topY1, float $x2, float $topY2, array $rgb, float $width): void
    {
        $y1 = self::PAGE_HEIGHT - $topY1;
        $y2 = self::PAGE_HEIGHT - $topY2;
        $this->stream .= sprintf("%.2F w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S\n", $width, $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2);
    }

    private function compilePdf(string $appName): string
    {
        $pageCount = count($this->pages);

        // Append consistent footer to every page now that total page count is known.
        foreach ($this->pages as $i => $content) {
            $this->stream = $content;
            $footerY = self::PAGE_HEIGHT - 24;
            $this->drawLine(self::MARGIN_X, $footerY - 8, self::PAGE_WIDTH - self::MARGIN_X, $footerY - 8, [0.85, 0.88, 0.92], 0.5);
            $this->drawText(self::MARGIN_X, $footerY, $appName.' Automated Sales Report', 8, false, [0.47, 0.53, 0.60]);
            $this->drawRightText(self::PAGE_WIDTH - self::MARGIN_X, $footerY, sprintf('Page %d of %d', $i + 1, $pageCount), 8, false, [0.47, 0.53, 0.60]);
            $this->pages[$i] = $this->stream;
        }

        // Build numbered PDF objects: 1=Catalog, 2=Pages, 3=F1, 4=F2, then pairs of (Page, Content).
        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            3 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];

        $kids = [];
        $nextId = 5;
        foreach ($this->pages as $pageContent) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $kids[] = "{$pageId} 0 R";

            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId
            );
            $objects[$contentId] = sprintf(
                "<< /Length %d >>\nstream\n%sendstream",
                strlen($pageContent),
                $pageContent
            );
        }

        $objects[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), $pageCount);
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($objects as $id => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", $totalObjects, $xrefOffset);

        return $pdf;
    }

    private function money(string $amount, string $currency): string
    {
        $prefix = trim($currency) !== '' ? trim($currency).' ' : '';

        return $prefix.number_format((float) $amount, 2, '.', ',');
    }

    private function truncate(string $text, int $maxLength): string
    {
        $clean = $this->toAscii($text);
        if (strlen($clean) <= $maxLength) {
            return $clean;
        }

        return substr($clean, 0, $maxLength - 3).'...';
    }

    private function escapePdfText(string $text): string
    {
        $ascii = $this->toAscii($text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    private function toAscii(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    private function estimateTextWidth(string $text, float $size, bool $bold): float
    {
        $text = $this->toAscii($text);
        $width = 0.0;
        $len = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === ' ' || $ch === '.' || $ch === ',' || $ch === ':' || $ch === 'i' || $ch === 'l') {
                $factor = 0.28;
            } elseif ($ch >= '0' && $ch <= '9') {
                $factor = 0.56;
            } elseif ($ch === 'W' || $ch === 'M' || $ch === 'm' || $ch === 'w') {
                $factor = 0.78;
            } elseif ($ch >= 'A' && $ch <= 'Z') {
                $factor = 0.67;
            } else {
                $factor = 0.52;
            }
            $width += $factor * $size;
        }

        return $bold ? $width * 1.04 : $width;
    }
}

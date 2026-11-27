<?php

namespace Tests\Unit;

use App\Services\SalesReportPdfGenerator;
use Tests\TestCase;

class SalesReportPdfGeneratorTest extends TestCase
{
    public function test_generates_a_valid_multi_page_pdf_for_a_30_day_summary(): void
    {
        $daily = [];
        for ($i = 0; $i < 30; $i++) {
            $daily[] = [
                'date' => date('Y-m-d', strtotime("2026-10-27 +{$i} days")),
                'orders' => $i + 1,
                'sales' => number_format(($i + 1) * 125.50, 2, '.', ''),
            ];
        }

        $summary = [
            'from' => '2026-10-27',
            'to' => '2026-11-25',
            'timezone' => 'Asia/Manila',
            'sales' => '58357.50',
            'orders' => 465,
            'avg_order' => '125.50',
            'voided' => 4,
            'top_sellers' => [
                ['label' => 'Brown Sugar Boba (L)', 'qty' => 210, 'revenue' => '26250.00'],
                ['label' => 'Matcha Latte (M)', 'qty' => 140, 'revenue' => '16800.00'],
            ],
            'daily' => $daily,
        ];

        $pdf = (new SalesReportPdfGenerator())->generate($summary, 'PHP', '2026-11-26 08:00');

        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString("xref\n", $pdf);
        $this->assertStringContainsString("startxref\n", $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('Period: 2026-10-27 to 2026-11-25', $pdf);
        $this->assertStringContainsString('PHP 58,357.50', $pdf);
        $this->assertStringContainsString('Brown Sugar Boba \\(L\\)', $pdf);
        // 30 daily rows + top sellers overflows one A4 page and paginates to at least 2 pages
        $this->assertStringContainsString('Page 2 of 2', $pdf);
    }

    public function test_handles_empty_sales_and_escapes_special_characters(): void
    {
        $summary = [
            'from' => '2026-10-27',
            'to' => '2026-11-25',
            'timezone' => 'Asia/Manila',
            'sales' => '0.00',
            'orders' => 0,
            'avg_order' => '0.00',
            'voided' => 0,
            'top_sellers' => [],
            'daily' => [],
        ];

        $pdf = (new SalesReportPdfGenerator())->generate($summary, 'PHP', '2026-11-26 08:00');

        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString('No paid sales recorded in this period.', $pdf);
        $this->assertStringContainsString('Page 1 of 1', $pdf);
    }

    public function test_renders_itemized_csv_sales_rows_inside_pdf(): void
    {
        $summary = [
            'from' => '2026-10-27',
            'to' => '2026-11-25',
            'timezone' => 'Asia/Manila',
            'sales' => '640.00',
            'orders' => 2,
            'avg_order' => '320.00',
            'voided' => 0,
            'top_sellers' => [
                ['label' => 'Brown Sugar Boba (L)', 'qty' => 3, 'revenue' => '450.00'],
            ],
            'daily' => [
                ['date' => '2026-11-24', 'orders' => 2, 'sales' => '640.00'],
            ],
        ];

        $csvRows = [
            ['2026-11-24', 1001, 'Brown Sugar Boba', 'L', 3, '150.00', '450.00'],
            ['2026-11-24', 1002, 'Classic Fries', '', 2, '95.00', '190.00'],
        ];

        $pdf = (new SalesReportPdfGenerator())->generate($summary, 'PHP', '2026-11-26 08:00', $csvRows);

        $this->assertStringStartsWith("%PDF-1.4\n", $pdf);
        $this->assertStringContainsString('Itemized Sales Report \\(CSV\\)', $pdf);
        $this->assertStringContainsString('Brown Sugar Boba', $pdf);
        $this->assertStringContainsString('1001', $pdf);
        $this->assertStringContainsString('450.00', $pdf);
        $this->assertStringContainsString('Classic Fries', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
    }
}

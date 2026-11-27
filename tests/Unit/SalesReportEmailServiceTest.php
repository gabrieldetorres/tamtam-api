<?php

namespace Tests\Unit;

use App\Mail\SalesReportMail;
use App\Repositories\AuditLogRepository;
use App\Repositories\ReportRepository;
use App\Services\ReportService;
use App\Services\SalesReportEmailService;
use App\Services\SalesReportPdfGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SalesReportEmailServiceTest extends TestCase
{
    private MockInterface $reportsRepo;
    private MockInterface $auditRepo;
    private SalesReportEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tamtam.report_timezone' => 'Asia/Manila',
            'tamtam.sales_report_email' => [
                'enabled' => true,
                'recipients' => ['owner@example.com'],
                'interval_days' => 30,
                'send_time' => '08:00',
                'currency' => 'PHP',
            ],
        ]);

        $this->reportsRepo = Mockery::mock(ReportRepository::class);
        $this->auditRepo = Mockery::mock(AuditLogRepository::class);

        $this->reportsRepo->shouldReceive('totals')->andReturn(['orders' => 10, 'sales' => '1500', 'voided' => 1])->byDefault();
        $this->reportsRepo->shouldReceive('topSellers')->andReturn([
            ['name' => 'Milk Tea', 'size' => 'L', 'qty' => 10, 'revenue' => '1500.00'],
        ])->byDefault();
        $this->reportsRepo->shouldReceive('daily')->andReturn([])->byDefault();
        $this->reportsRepo->shouldReceive('exportLines')->andReturn(new LazyCollection([
            (object) [
                'created_at' => '2026-11-24 10:00:00+08:00',
                'order_no' => 101,
                'name' => 'Milk Tea',
                'size' => 'L',
                'qty' => 10,
                'price' => '150.00',
            ],
        ]))->byDefault();

        $this->service = new SalesReportEmailService(
            new ReportService($this->reportsRepo),
            new SalesReportPdfGenerator(),
            $this->auditRepo,
        );
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_default_window_covers_the_last_30_completed_days_in_stall_timezone(): void
    {
        // 2026-11-25 00:15 Manila is still 2026-11-24 16:15 UTC
        $this->travelTo(Carbon::parse('2026-11-24 16:15:00', 'UTC'));

        [$from, $to] = $this->service->defaultWindow(30);

        $this->assertSame('2026-10-26', $from);
        $this->assertSame('2026-11-24', $to);
    }

    public function test_is_due_respects_30_calendar_day_interval(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        $this->auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->times(3)
            ->andReturn(
                null,
                CarbonImmutable::parse('2026-10-27 08:05:00', 'Asia/Manila'), // 29 days ago
                CarbonImmutable::parse('2026-10-26 08:05:00', 'Asia/Manila'), // 30 days ago
            );

        $this->assertTrue($this->service->isDue(30));
        $this->assertFalse($this->service->isDue(30));
        $this->assertTrue($this->service->isDue(30));
    }

    public function test_send_attaches_pdf_and_records_audit_entry(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        $this->auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->once()
            ->andReturn(null);

        $this->auditRepo->shouldReceive('record')
            ->once()
            ->with(
                SalesReportEmailService::AUDIT_KIND,
                'Sent tamtam-sales-2026-10-26-to-2026-11-24.pdf to owner@example.com',
                null
            );

        $result = $this->service->send(dueOnly: true);

        $this->assertTrue($result['sent']);
        $this->assertSame('tamtam-sales-2026-10-26-to-2026-11-24.pdf', $result['filename']);
        $this->assertSame('tamtam-sales-2026-10-26-to-2026-11-24.csv', $result['csv_filename']);

        Mail::assertSent(SalesReportMail::class, function (SalesReportMail $mail) {
            return $mail->hasTo('owner@example.com')
                && $mail->pdfFilename === 'tamtam-sales-2026-10-26-to-2026-11-24.pdf'
                && $mail->csvFilename === 'tamtam-sales-2026-10-26-to-2026-11-24.csv'
                && str_starts_with($mail->pdfBinary, '%PDF-1.4')
                && str_contains($mail->pdfBinary, 'Itemized Sales Report \\(CSV\\)')
                && str_contains((string) $mail->csvBinary, 'date,order_no,item,size,qty,price,line_total')
                && str_contains((string) $mail->csvBinary, '2026-11-24,101,"Milk Tea",L,10,150.00,1500.00')
                && count($mail->attachments()) === 2;
        });
    }

    public function test_send_skips_when_due_only_and_recently_sent(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        $this->auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->once()
            ->andReturn(CarbonImmutable::parse('2026-11-10 08:00:00', 'Asia/Manila'));

        $this->auditRepo->shouldNotReceive('record');

        $result = $this->service->send(dueOnly: true);

        $this->assertFalse($result['sent']);
        $this->assertSame('not_due', $result['reason']);
        Mail::assertNothingSent();
    }

    public function test_send_requires_at_least_one_valid_recipient(): void
    {
        config(['tamtam.sales_report_email.recipients' => []]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->send(from: '2026-10-26', to: '2026-11-24');
    }
}

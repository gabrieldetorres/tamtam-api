<?php

namespace Tests\Feature;

use App\Mail\SalesReportMail;
use App\Repositories\AuditLogRepository;
use App\Repositories\ReportRepository;
use App\Services\SalesReportEmailService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\LazyCollection;
use Mockery;
use Tests\TestCase;

class SendSalesReportEmailCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_command_sends_30_day_pdf_sales_report(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        config([
            'tamtam.report_timezone' => 'Asia/Manila',
            'tamtam.sales_report_email.enabled' => true,
            'tamtam.sales_report_email.recipients' => ['boss@gmail.com'],
            'tamtam.sales_report_email.interval_days' => 30,
            'tamtam.sales_report_email.currency' => 'PHP',
        ]);

        $reportsRepo = Mockery::mock(ReportRepository::class);
        $reportsRepo->shouldReceive('totals')->once()->andReturn(['orders' => 5, 'sales' => '750', 'voided' => 0]);
        $reportsRepo->shouldReceive('topSellers')->once()->andReturn([]);
        $reportsRepo->shouldReceive('daily')->once()->andReturn([]);
        $reportsRepo->shouldReceive('exportLines')->once()->andReturn(new LazyCollection([
            (object) [
                'created_at' => '2026-11-24 09:30:00+08:00',
                'order_no' => 55,
                'name' => 'Iced Coffee',
                'size' => 'M',
                'qty' => 2,
                'price' => '120.00',
            ],
        ]));
        $this->app->instance(ReportRepository::class, $reportsRepo);

        $auditRepo = Mockery::mock(AuditLogRepository::class);
        $auditRepo->shouldReceive('record')->once();
        $this->app->instance(AuditLogRepository::class, $auditRepo);

        $this->artisan('reports:send-sales-email')
            ->expectsOutput('Sent tamtam-sales-2026-10-26-to-2026-11-24.pdf to boss@gmail.com.')
            ->assertExitCode(0);

        Mail::assertSent(SalesReportMail::class, fn (SalesReportMail $mail) => $mail->hasTo('boss@gmail.com')
            && $mail->csvFilename === 'tamtam-sales-2026-10-26-to-2026-11-24.csv'
            && count($mail->attachments()) === 2);
    }

    public function test_command_with_due_only_skips_when_already_sent_within_30_days(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        $auditRepo = Mockery::mock(AuditLogRepository::class);
        $auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->once()
            ->andReturn(CarbonImmutable::parse('2026-11-15 08:00:00', 'Asia/Manila'));
        $this->app->instance(AuditLogRepository::class, $auditRepo);

        $this->artisan('reports:send-sales-email --due-only')
            ->expectsOutput('Skipped sending sales report (not_due).')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_command_rejects_partial_date_range(): void
    {
        $this->artisan('reports:send-sales-email --from=2026-11-01')
            ->expectsOutput('Both --from and --to must be provided together.')
            ->assertExitCode(1);
    }

    public function test_scheduler_registers_sales_report_command(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $matched = array_filter(
            $events,
            fn ($event) => str_contains((string) $event->command, 'reports:send-sales-email --due-only')
        );

        $this->assertNotEmpty($matched);
    }
}

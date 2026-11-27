<?php

namespace Tests\Feature;

use App\Mail\SalesReportMail;
use App\Repositories\AuditLogRepository;
use App\Repositories\ReportRepository;
use App\Services\SalesReportEmailService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\LazyCollection;
use Mockery;
use Tests\TestCase;

class CronSalesReportTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_cron_endpoint_fails_closed_when_secret_is_not_configured(): void
    {
        config(['tamtam.sales_report_email.cron_secret' => '']);

        $this->postJson('/api/v1/cron/sales-report')
            ->assertStatus(503)
            ->assertJson(['message' => 'Scheduled report secret is not configured.']);
    }

    public function test_cron_endpoint_rejects_missing_or_wrong_secret(): void
    {
        config(['tamtam.sales_report_email.cron_secret' => 'super-secret-token']);

        $this->postJson('/api/v1/cron/sales-report')
            ->assertStatus(401);

        $this->withHeader('X-Cron-Secret', 'wrong-token')
            ->postJson('/api/v1/cron/sales-report')
            ->assertStatus(401);
    }

    public function test_cron_endpoint_skips_when_already_sent_within_30_days(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));
        config(['tamtam.sales_report_email.cron_secret' => 'super-secret-token']);

        $auditRepo = Mockery::mock(AuditLogRepository::class);
        $auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->once()
            ->andReturn(CarbonImmutable::parse('2026-11-10 08:00:00', 'Asia/Manila'));
        $this->app->instance(AuditLogRepository::class, $auditRepo);

        $this->withHeader('X-Cron-Secret', 'super-secret-token')
            ->postJson('/api/v1/cron/sales-report')
            ->assertOk()
            ->assertJson([
                'sent' => false,
                'reason' => 'not_due',
            ]);

        Mail::assertNothingSent();
    }

    public function test_cron_endpoint_sends_report_when_30_days_are_due(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        config([
            'tamtam.report_timezone' => 'Asia/Manila',
            'tamtam.sales_report_email.enabled' => true,
            'tamtam.sales_report_email.recipients' => ['chesteredizon@gmail.com'],
            'tamtam.sales_report_email.interval_days' => 30,
            'tamtam.sales_report_email.currency' => 'PHP',
            'tamtam.sales_report_email.cron_secret' => 'super-secret-token',
        ]);

        $reportsRepo = Mockery::mock(ReportRepository::class);
        $reportsRepo->shouldReceive('totals')->once()->andReturn(['orders' => 2, 'sales' => '300', 'voided' => 0]);
        $reportsRepo->shouldReceive('topSellers')->once()->andReturn([]);
        $reportsRepo->shouldReceive('daily')->once()->andReturn([]);
        $reportsRepo->shouldReceive('exportLines')->once()->andReturn(new LazyCollection([
            (object) [
                'created_at' => '2026-11-24 09:30:00+08:00',
                'order_no' => 12,
                'name' => 'Oat Latte',
                'size' => 'M',
                'qty' => 2,
                'price' => '150.00',
            ],
        ]));
        $this->app->instance(ReportRepository::class, $reportsRepo);

        $auditRepo = Mockery::mock(AuditLogRepository::class);
        $auditRepo->shouldReceive('lastTimestampForKind')
            ->with(SalesReportEmailService::AUDIT_KIND)
            ->once()
            ->andReturn(null);
        $auditRepo->shouldReceive('record')->once();
        $this->app->instance(AuditLogRepository::class, $auditRepo);

        $this->withHeader('X-Cron-Secret', 'super-secret-token')
            ->postJson('/api/v1/cron/sales-report')
            ->assertOk()
            ->assertJson([
                'sent' => true,
                'filename' => 'tamtam-sales-2026-10-26-to-2026-11-24.pdf',
                'csv_filename' => 'tamtam-sales-2026-10-26-to-2026-11-24.csv',
                'recipients' => ['chesteredizon@gmail.com'],
            ]);

        Mail::assertSent(SalesReportMail::class, fn (SalesReportMail $mail) => $mail->hasTo('chesteredizon@gmail.com')
            && count($mail->attachments()) === 2);
    }
}

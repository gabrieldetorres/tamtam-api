<?php

namespace Tests\Unit;

use App\Repositories\ReportRepository;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    private MockInterface $repo;
    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tamtam.report_timezone' => 'Asia/Manila']);

        $this->repo = Mockery::mock(ReportRepository::class);
        $this->service = new ReportService($this->repo);

        $this->repo->shouldReceive('totals')->andReturn(['orders' => 0, 'sales' => '0', 'voided' => 0])->byDefault();
        $this->repo->shouldReceive('topSellers')->andReturn([])->byDefault();
        $this->repo->shouldReceive('daily')->andReturn([])->byDefault();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_default_range_is_today_on_the_stalls_clock(): void
    {
        // 18:30 UTC on the 16th is already 02:30 on the 17th in Manila
        $this->travelTo(Carbon::parse('2026-11-16 18:30:00', 'UTC'));

        $captured = [];
        $this->repo->shouldReceive('totals')->once()->andReturnUsing(function ($start, $end) use (&$captured) {
            $captured = [$start->toIso8601String(), $end->toIso8601String()];

            return ['orders' => 0, 'sales' => '0', 'voided' => 0];
        });

        $result = $this->service->summary(null, null);

        $this->assertSame(['2026-11-17T00:00:00+08:00', '2026-11-18T00:00:00+08:00'], $captured);
        $this->assertSame('2026-11-17', $result['from']);
        $this->assertSame('2026-11-17', $result['to']);
        $this->assertSame('Asia/Manila', $result['timezone']);
    }

    public function test_the_end_date_is_included_in_the_range(): void
    {
        $captured = [];
        $this->repo->shouldReceive('totals')->once()->andReturnUsing(function ($start, $end) use (&$captured) {
            $captured = [$start->toIso8601String(), $end->toIso8601String()];

            return ['orders' => 0, 'sales' => '0', 'voided' => 0];
        });

        $this->service->summary('2026-11-10', '2026-11-16');

        // 10th 00:00 up to (not including) the 17th 00:00, so the whole 16th counts
        $this->assertSame(['2026-11-10T00:00:00+08:00', '2026-11-17T00:00:00+08:00'], $captured);
    }

    public function test_average_order_is_sales_divided_by_orders(): void
    {
        $this->repo->shouldReceive('totals')->andReturn(['orders' => 4, 'sales' => '200', 'voided' => 1]);

        $result = $this->service->summary('2026-11-16', '2026-11-16');

        $this->assertSame('200.00', $result['sales']);
        $this->assertSame(4, $result['orders']);
        $this->assertSame('50.00', $result['avg_order']);
        $this->assertSame(1, $result['voided']);
    }

    public function test_no_sales_gives_zero_not_a_division_error(): void
    {
        $result = $this->service->summary('2026-11-16', '2026-11-16');

        $this->assertSame('0.00', $result['sales']);
        $this->assertSame('0.00', $result['avg_order']);
        $this->assertSame([], $result['top_sellers']);
    }

    public function test_top_sellers_get_a_readable_label(): void
    {
        $this->repo->shouldReceive('topSellers')->andReturn([
            ['name' => 'Milk Tea', 'size' => 'M', 'qty' => 12, 'revenue' => '540.00'],
            ['name' => 'Coffee', 'size' => '', 'qty' => 5, 'revenue' => '150.00'],
        ]);

        $top = $this->service->summary('2026-11-16', '2026-11-16')['top_sellers'];

        $this->assertSame('Milk Tea (M)', $top[0]['label']);
        $this->assertSame('Coffee', $top[1]['label']);
    }

    public function test_days_without_sales_are_filled_in_as_zero(): void
    {
        $this->repo->shouldReceive('daily')->andReturn([['day' => '2026-11-16', 'orders' => 2, 'sales' => '90']]);

        $daily = $this->service->summary('2026-11-15', '2026-11-17')['daily'];

        $this->assertSame([
            ['date' => '2026-11-15', 'orders' => 0, 'sales' => '0.00'],
            ['date' => '2026-11-16', 'orders' => 2, 'sales' => '90.00'],
            ['date' => '2026-11-17', 'orders' => 0, 'sales' => '0.00'],
        ], $daily);
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->summary('2026-11-16', '2026-11-10');
    }

    public function test_a_range_longer_than_a_year_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->summary('2024-01-01', '2026-11-16');
    }

    public function test_csv_rows_use_local_dates_and_neutralise_formulas(): void
    {
        $this->repo->shouldReceive('exportLines')->andReturn(LazyCollection::make([
            (object) ['created_at' => '2026-11-16 18:30:00+00', 'order_no' => 7,
                'name' => '=HYPERLINK("http://evil")', 'size' => 'M', 'qty' => 2, 'price' => '45.00'],
            (object) ['created_at' => '2026-11-16 02:00:00+00', 'order_no' => 8,
                'name' => 'Milk Tea', 'size' => null, 'qty' => 3, 'price' => '45.00'],
        ]));

        $export = $this->service->export('2026-11-16', '2026-11-17');
        $rows = $export['rows']->all();

        $this->assertSame('tamtam-sales-2026-11-16-to-2026-11-17.csv', $export['filename']);
        // 18:30 UTC is already the 17th in Manila
        $this->assertSame(['2026-11-17', 7, "'=HYPERLINK(\"http://evil\")", 'M', 2, '45.00', '90.00'], $rows[0]);
        $this->assertSame(['2026-11-16', 8, 'Milk Tea', '', 3, '45.00', '135.00'], $rows[1]);
    }
}

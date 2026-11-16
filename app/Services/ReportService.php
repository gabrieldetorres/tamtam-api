<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

/** Sales reports for a range of days, in the stall's timezone (default Asia/Manila). */
class ReportService
{
    public const CSV_HEADER = ['date', 'order_no', 'item', 'size', 'qty', 'price', 'line_total'];

    private const MAX_DAYS = 366;
    private const TOP_SELLERS = 8;

    public function __construct(private ReportRepository $reports) {}

    /** $from / $to are "YYYY-MM-DD", both inclusive. Missing dates default to today. */
    public function summary(?string $from, ?string $to): array
    {
        [$fromDay, $toDay, $start, $end, $tz] = $this->window($from, $to);

        $totals = $this->reports->totals($start, $end);
        $orders = (int) $totals['orders'];
        $sales = (float) $totals['sales'];

        return [
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
            'timezone' => $tz,
            'sales' => number_format($sales, 2, '.', ''),
            'orders' => $orders,
            'avg_order' => $orders > 0 ? number_format($sales / $orders, 2, '.', '') : '0.00',
            'voided' => (int) $totals['voided'],
            'top_sellers' => $this->topSellers($start, $end),
            'daily' => $this->daily($fromDay, $toDay, $start, $end, $tz),
        ];
    }

    /** @return array{filename: string, rows: LazyCollection} */
    public function export(?string $from, ?string $to): array
    {
        [$fromDay, $toDay, $start, $end, $tz] = $this->window($from, $to);

        $rows = $this->reports->exportLines($start, $end)->map(function ($r) use ($tz) {
            $price = (int) round(((float) $r->price) * 100);

            return [
                Carbon::parse($r->created_at)->setTimezone($tz)->format('Y-m-d'),
                (int) $r->order_no,
                $this->safeText($r->name),
                $this->safeText($r->size),
                (int) $r->qty,
                number_format($price / 100, 2, '.', ''),
                number_format($price * (int) $r->qty / 100, 2, '.', ''),
            ];
        });

        return [
            'filename' => 'tamtam-sales-'.$fromDay->toDateString().'-to-'.$toDay->toDateString().'.csv',
            'rows' => $rows,
        ];
    }

    private function topSellers(Carbon|CarbonImmutable $start, Carbon|CarbonImmutable $end): array
    {
        return array_map(function ($row) {
            $row['label'] = $row['name'].(($row['size'] ?? '') !== '' ? ' ('.$row['size'].')' : '');

            return $row;
        }, $this->reports->topSellers($start, $end, self::TOP_SELLERS));
    }

    /** One entry per day in the range; days without sales are filled in as zero. */
    private function daily($fromDay, $toDay, $start, $end, string $tz): array
    {
        $found = [];
        foreach ($this->reports->daily($start, $end, $tz) as $row) {
            $found[$row['day']] = $row;
        }

        $days = [];
        for ($d = $fromDay; $d <= $toDay; $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'orders' => $found[$key]['orders'] ?? 0,
                'sales' => number_format((float) ($found[$key]['sales'] ?? 0), 2, '.', ''),
            ];
        }

        return $days;
    }

    /**
     * Turns "2026-11-16".."2026-11-16" into the exact moments [start, end) of those
     * local days, e.g. 00:00 +08:00 on the 16th up to 00:00 +08:00 on the 17th.
     */
    private function window(?string $from, ?string $to): array
    {
        $tz = (string) config('tamtam.report_timezone', 'Asia/Manila');
        $today = now($tz)->startOfDay()->toImmutable();

        $toDay = $to ? Carbon::parse($to, $tz)->startOfDay()->toImmutable() : $today;
        $fromDay = $from ? Carbon::parse($from, $tz)->startOfDay()->toImmutable() : $toDay;

        if ($toDay < $fromDay) {
            throw ValidationException::withMessages(['to' => 'The end date cannot be before the start date.']);
        }

        if ($fromDay->diffInDays($toDay) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Please choose a range of one year or less.']);
        }

        return [$fromDay, $toDay, $fromDay, $toDay->addDay(), $tz];
    }

    /**
     * Item names are typed by a person. A name starting with = + - or @ would run as a
     * formula when the CSV is opened in Excel, so it is made harmless with a leading '.
     */
    private function safeText(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}

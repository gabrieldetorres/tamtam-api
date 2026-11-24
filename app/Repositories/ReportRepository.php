<?php

namespace App\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;


class ReportRepository
{
    /** @return array{orders: int, sales: string, voided: int} */
    public function totals(CarbonInterface $start, CarbonInterface $end): array
    {
        $row = DB::selectOne("
            select
                count(*) filter (where status = 'paid') as orders,
                coalesce(sum(total) filter (where status = 'paid'), 0) as sales,
                count(*) filter (where status = 'void') as voided
            from orders
            where created_at >= ? and created_at < ?
        ", [$start->toIso8601String(), $end->toIso8601String()]);

        return [
            'orders' => (int) $row->orders,
            'sales' => (string) $row->sales,
            'voided' => (int) $row->voided,
        ];
    }

    /** @return array<int, array{name: string, size: ?string, qty: int, revenue: string}> */
    public function topSellers(CarbonInterface $start, CarbonInterface $end, int $limit): array
    {
        $rows = DB::select("
            select oi.name, oi.size, sum(oi.qty) as units, sum(oi.qty * oi.price) as revenue
            from order_items oi
            join orders o on o.id = oi.order_id
            where o.status = 'paid' and o.created_at >= ? and o.created_at < ?
            group by oi.name, oi.size
            order by units desc, oi.name
            limit ?
        ", [$start->toIso8601String(), $end->toIso8601String(), $limit]);

        return array_map(fn ($r) => [
            'name' => $r->name,
            'size' => $r->size,
            'qty' => (int) $r->units,
            'revenue' => number_format((float) $r->revenue, 2, '.', ''),
        ], $rows);
    }

    /**
     * Paid orders and sales per local calendar day (days with no sales are absent).
     *
     * @return array<int, array{day: string, orders: int, sales: string}>
     */
    public function daily(CarbonInterface $start, CarbonInterface $end, string $timezone): array
    {
        $rows = DB::select("
            select (created_at at time zone cast(? as text))::date as day,
                   count(*) as orders,
                   coalesce(sum(total), 0) as sales
            from orders
            where status = 'paid' and created_at >= ? and created_at < ?
            group by 1
            order by 1
        ", [$timezone, $start->toIso8601String(), $end->toIso8601String()]);

        return array_map(fn ($r) => [
            'day' => (string) $r->day,
            'orders' => (int) $r->orders,
            'sales' => (string) $r->sales,
        ], $rows);
    }

    
    public function exportLines(CarbonInterface $start, CarbonInterface $end): LazyCollection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.status', 'paid')
            ->where('o.created_at', '>=', $start->toIso8601String())
            ->where('o.created_at', '<', $end->toIso8601String())
            ->orderBy('o.created_at')
            ->orderBy('o.order_no')
            ->orderBy('oi.name')
            ->select('o.created_at', 'o.order_no', 'oi.name', 'oi.size', 'oi.qty', 'oi.price')
            ->cursor();
    }
}

<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as BaseCollection;

class OrderRepository
{
    public function create(array $order, array $items): Order
    {
        $created = Order::create($order);
        $created->items()->createMany($items);

       
        return $created->fresh(['items', 'customer']);
    }

    public function findByClientRef(string $ref): ?Order
    {
        return Order::with(['items', 'customer'])->where('client_ref', $ref)->first();
    }

    public function find(string $id): Order
    {
        return Order::with(['items', 'customer'])->findOrFail($id);
    }

    public function latest(?string $status, int $limit): Collection
    {
        return Order::with(['items', 'customer'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('order_no')
            ->limit($limit)
            ->get();
    }

    
    public function customerStats(string $customerId): array
    {
        $row = Order::query()
            ->where('customer_id', $customerId)
            ->where('status', 'paid')
            ->selectRaw('count(*) as order_count, coalesce(sum(total), 0) as total_spend, max(created_at) as last_order_at')
            ->first();

        return [
            'orders' => (int) $row->order_count,
            'total_spend' => number_format((float) $row->total_spend, 2, '.', ''),
            'last_order_at' => $row->last_order_at,
        ];
    }

    public function customerTopProducts(string $customerId, int $limit = 3): BaseCollection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.customer_id', $customerId)
            ->where('o.status', 'paid')
            ->groupBy('oi.name')
            ->selectRaw('oi.name, sum(oi.qty) as qty')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty]);
    }

    public function customerHistory(string $customerId, int $limit = 50): Collection
    {
        return Order::with('items')
            ->where('customer_id', $customerId)
            ->where('status', 'paid')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}

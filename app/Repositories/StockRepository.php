<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Support\Collection;

/** Stock changes caused by sales. Only call these inside a database transaction. */
class StockRepository
{
    /**
     * Locks the ingredient rows until the transaction ends, so two cashiers
     * selling the last cup at the same time cannot both succeed.
     * Rows are locked in id order so two orders can never deadlock each other.
     */
    public function lockForUpdate(array $ids): Collection
    {
        return Item::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /** $amount is a plain decimal string such as "1.200". */
    public function deduct(string $id, string $amount): void
    {
        Item::query()->whereKey($id)->decrement('qty', $amount, ['updated_at' => now()]);
    }
}

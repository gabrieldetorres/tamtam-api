<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Support\Collection;


class StockRepository
{
   
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

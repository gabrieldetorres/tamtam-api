<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class IngredientRepository
{
    public function allActive(): Collection
    {
        return Item::query()->where('archived', false)->orderBy('item')->get();
    }

    /** Active ingredient or null. */
    public function find(string $id): ?Item
    {
        return Item::query()->where('archived', false)->find($id);
    }

    /** The active ingredients among the given ids (used to validate a recipe). */
        /** The active ingredients among the given ids (used to validate a recipe). */
    public function activeByIds(array $ids): BaseCollection
    {
        return Item::query()
            ->where('archived', false)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** Locks the row until the transaction ends, so two edits cannot overwrite each other. */
    public function findActiveForUpdate(string $id): Item
    {
        return Item::query()->where('archived', false)->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Item
    {
        return Item::create($data)->fresh();
    }

    public function update(Item $item, array $data): Item
    {
        $item->update($data);

        return $item->fresh();
    }

    public function archive(Item $item): void
    {
        $item->update(['archived' => true]);
    }
}

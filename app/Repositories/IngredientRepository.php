<?php

namespace App\Repositories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class IngredientRepository
{
    public function allActive(): Collection
    {
        return Item::query()->where('archived', false)->orderBy('item')->get();
    }

    /** Active ingredient or null (used to validate a drink's ingredient). */
    public function find(string $id): ?Item
    {
        return Item::query()->where('archived', false)->find($id);
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

<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function allActive(): Collection
    {
        return Product::with('ingredient')->where('archived', false)->orderBy('name')->get();
    }

    /** Throws ModelNotFoundException (-> 404) if missing or archived. */
    public function findActive(string $id): Product
    {
        return Product::with('ingredient')->where('archived', false)->findOrFail($id);
    }

    public function nameSizeTaken(string $name, string $size, ?string $exceptId = null): bool
    {
        return Product::query()
            ->where('archived', false)
            ->where('name', $name)
            ->where('size', $size)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /** Names of active drinks that use this ingredient. */
    public function namesUsingIngredient(string $itemId): array
    {
        return Product::query()
            ->where('archived', false)
            ->where('item_id', $itemId)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function create(array $data): Product
    {
        return Product::create($data)->fresh('ingredient');
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh('ingredient');
    }

    public function archive(Product $product): void
    {
        $product->update(['archived' => true]);
    }
}

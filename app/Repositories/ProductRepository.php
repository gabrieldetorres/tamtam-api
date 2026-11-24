<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class ProductRepository
{
    public function allActive(): Collection
    {
        return Product::with('ingredients.item')->where('archived', false)->orderBy('name')->get();
    }

  
    public function findActive(string $id): Product
    {
        return Product::with('ingredients.item')->where('archived', false)->findOrFail($id);
    }

    
    public function activeByIds(array $ids): BaseCollection
    {
        return Product::query()
            ->with('ingredients')
            ->where('archived', false)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
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

   
    public function namesUsingIngredient(string $itemId): array
    {
        return Product::query()
            ->where('archived', false)
            ->whereHas('ingredients', fn ($q) => $q->where('item_id', $itemId))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @param array<int, array{item_id: string, qty: mixed}> $recipe */
    public function create(array $data, array $recipe): Product
    {
        $product = Product::create($data);
        $product->ingredients()->createMany($recipe);

        return $product->fresh('ingredients.item');
    }

   
    public function update(Product $product, array $data, ?array $recipe = null): Product
    {
        if ($data) {
            $product->update($data);
        }

        if ($recipe !== null) {
            $product->ingredients()->delete();
            $product->ingredients()->createMany($recipe);
        }

        return $product->fresh('ingredients.item');
    }

    public function archive(Product $product): void
    {
        $product->update(['archived' => true]);
    }
}

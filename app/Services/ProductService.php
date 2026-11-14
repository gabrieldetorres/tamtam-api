<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\AuditLogRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Business rules for the menu. */
class ProductService
{
    public function __construct(
        private ProductRepository $products,
        private IngredientRepository $ingredients,
        private AuditLogRepository $audit,
    ) {}

    public function list(): Collection
    {
        return $this->products->allActive();
    }

    public function create(array $data): Product
    {
        $data['size'] ??= '';
        $data = $this->normalizeIngredient($this->clean($data));
        $this->assertNameSizeFree($data['name'], $data['size']);

        return $this->products->create($data);
    }

    public function update(string $id, array $data, string $userId): Product
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $product = $this->products->findActive($id);
            $data = $this->normalizeIngredient($this->clean($data), $product);

            $this->assertNameSizeFree(
                $data['name'] ?? $product->name,
                $data['size'] ?? $product->size,
                $product->id
            );

            $label = $product->name.' '.$product->size;
            $oldPrice = $product->price;

            $updated = $this->products->update($product, $data);

            // Replaces the old log_price() database trigger.
            if (array_key_exists('price', $data) && $updated->price !== $oldPrice) {
                $this->audit->record('price change', $label.': '.$oldPrice.' to '.$updated->price, $userId);
            }

            return $updated;
        });
    }

    public function archive(string $id, string $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $product = $this->products->findActive($id);
            $this->products->archive($product);
            $this->audit->record('product archived', $product->name.' '.$product->size, $userId);
        });
    }

    private function clean(array $data): array
    {
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('size', $data)) {
            $data['size'] = trim((string) $data['size']);
        }

        return $data;
    }

    /**
     * A drink either has no ingredient (qty is forced to 0) or an active
     * ingredient with an amount greater than 0.
     */
    private function normalizeIngredient(array $data, ?Product $existing = null): array
    {
        if (! array_key_exists('item_id', $data) && ! array_key_exists('qty', $data)) {
            return $data;
        }

        $itemId = array_key_exists('item_id', $data) ? $data['item_id'] : $existing?->item_id;
        $qty = array_key_exists('qty', $data) ? $data['qty'] : $existing?->qty;

        if ($itemId === null) {
            $data['item_id'] = null;
            $data['qty'] = 0;

            return $data;
        }

        if (! $this->ingredients->find($itemId)) {
            throw ValidationException::withMessages(['item_id' => 'That ingredient does not exist or is archived.']);
        }

        if ((float) $qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Enter how much of the ingredient one cup uses.']);
        }

        $data['item_id'] = $itemId;
        $data['qty'] = $qty;

        return $data;
    }

    private function assertNameSizeFree(string $name, string $size, ?string $exceptId = null): void
    {
        if ($this->products->nameSizeTaken($name, $size, $exceptId)) {
            throw ValidationException::withMessages(['name' => 'A product with this name and size already exists.']);
        }
    }
}

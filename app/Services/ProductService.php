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
        $recipe = $this->checkRecipe($data['ingredients'] ?? []);
        unset($data['ingredients']);

        $data['size'] ??= '';
        $data = $this->clean($data);
        $this->assertNameSizeFree($data['name'], $data['size']);

        return DB::transaction(fn () => $this->products->create($data, $recipe));
    }

    
    public function update(string $id, array $data, string $userId): Product
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $product = $this->products->findActive($id);

            $recipe = array_key_exists('ingredients', $data) ? $this->checkRecipe($data['ingredients'] ?? []) : null;
            unset($data['ingredients']);
            $data = $this->clean($data);

            $this->assertNameSizeFree(
                $data['name'] ?? $product->name,
                $data['size'] ?? $product->size,
                $product->id
            );

            $label = $product->name.' '.$product->size;
            $oldPrice = $product->price;
            $oldRecipe = $recipe !== null ? $this->recipeText($product) : null;

            $updated = $this->products->update($product, $data, $recipe);

            
            if (array_key_exists('price', $data) && $updated->price !== $oldPrice) {
                $this->audit->record('price change', $label.': '.$oldPrice.' to '.$updated->price, $userId);
            }

            
            if ($recipe !== null) {
                $newRecipe = $this->recipeText($updated);

                if ($newRecipe !== $oldRecipe) {
                    $this->audit->record(
                        'recipe change',
                        $label.' recipe: '.($oldRecipe ?: 'none').' to '.($newRecipe ?: 'none'),
                        $userId
                    );
                }
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
     * A recipe lists each ingredient once, every ingredient must exist and be active,
     * and every amount must be greater than 0.
     *
     * @return array<int, array{item_id: string, qty: mixed}>
     */
    private function checkRecipe(array $recipe): array
    {
        $ids = array_column($recipe, 'item_id');

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['ingredients' => 'Each ingredient can be listed only once.']);
        }

        if ($ids && $this->ingredients->activeByIds($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['ingredients' => 'An ingredient in the recipe does not exist or is archived.']);
        }

        foreach ($recipe as $line) {
            if ((float) $line['qty'] <= 0) {
                throw ValidationException::withMessages(['ingredients' => 'Enter how much of each ingredient one serving uses.']);
            }
        }

        return array_map(fn ($line) => ['item_id' => $line['item_id'], 'qty' => $line['qty']], $recipe);
    }

    private function recipeText(Product $product): string
    {
        return $product->ingredients
            ->sortBy(fn ($line) => $line->item->item)
            ->map(fn ($line) => $line->item->item.' '.(float) $line->qty)
            ->implode(', ');
    }

    private function assertNameSizeFree(string $name, string $size, ?string $exceptId = null): void
    {
        if ($this->products->nameSizeTaken($name, $size, $exceptId)) {
            throw ValidationException::withMessages(['name' => 'A product with this name and size already exists.']);
        }
    }
}

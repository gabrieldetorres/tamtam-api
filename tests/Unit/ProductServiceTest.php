<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Repositories\AuditLogRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private MockInterface $products;
    private MockInterface $ingredients;
    private MockInterface $audit;
    private ProductService $service;

    private const NAMES = ['i-bread' => 'Bread', 'i-patty' => 'Patty'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = Mockery::mock(ProductRepository::class);
        $this->ingredients = Mockery::mock(IngredientRepository::class);
        $this->audit = Mockery::mock(AuditLogRepository::class);
        $this->service = new ProductService($this->products, $this->ingredients, $this->audit);
    }

    private function line(string $itemId, float $qty): ProductIngredient
    {
        $line = (new ProductIngredient)->forceFill(['item_id' => $itemId, 'qty' => $qty]);
        $line->setRelation('item', (new Item)->forceFill([
            'id' => $itemId, 'item' => self::NAMES[$itemId] ?? $itemId, 'qty' => 10, 'unit' => 'pcs',
        ]));

        return $line;
    }

    /** @param array<int, array{0: string, 1: float}> $recipe */
    private function burger(array $recipe = []): Product
    {
        $p = (new Product)->forceFill(['id' => 'p-1', 'name' => 'Burger', 'size' => 'M', 'price' => 45]);
        $p->setRelation('ingredients', collect($recipe)->map(fn ($r) => $this->line($r[0], $r[1])));

        return $p;
    }

    /** A fake repository update: applies the changes to the product, like the real one does. */
    private function applyUpdate(): \Closure
    {
        return function ($p, $d, $r = null) {
            $p->forceFill($d);

            if ($r !== null) {
                $p->setRelation('ingredients', collect($r)->map(fn ($row) => $this->line($row['item_id'], (float) $row['qty'])));
            }

            return $p;
        };
    }

    private function found(string ...$ids): void
    {
        $this->ingredients->shouldReceive('activeByIds')
            ->andReturn(collect($ids)->map(fn ($id) => (new Item)->forceFill(['id' => $id])));
    }

    public function test_price_change_is_written_to_the_audit_log(): void
    {
        $this->products->shouldReceive('findActive')->andReturn($this->burger());
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('update')->andReturnUsing($this->applyUpdate());
        $this->audit->shouldReceive('record')->once()->with('price change', 'Burger M: 45.00 to 50.00', 'user-1');

        $result = $this->service->update('p-1', ['price' => 50], 'user-1');

        $this->assertSame('50.00', $result->price);
    }

    public function test_same_price_is_not_logged(): void
    {
        $this->products->shouldReceive('findActive')->andReturn($this->burger());
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('update')->andReturnUsing($this->applyUpdate());
        $this->audit->shouldReceive('record')->never();

        $this->service->update('p-1', ['price' => 45], 'user-1');
        $this->addToAssertionCount(1);
    }

    public function test_duplicate_name_and_size_is_rejected(): void
    {
        $this->products->shouldReceive('nameSizeTaken')->andReturn(true);
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Burger', 'size' => 'M', 'price' => 45]);
    }

    public function test_a_product_can_be_created_with_several_ingredients(): void
    {
        $this->found('i-bread', 'i-patty');
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);

        $saved = null;
        $this->products->shouldReceive('create')->once()->andReturnUsing(function ($data, $recipe) use (&$saved) {
            $saved = $recipe;

            return $this->burger();
        });

        $this->service->create([
            'name' => 'Burger', 'size' => 'M', 'price' => 45,
            'ingredients' => [['item_id' => 'i-bread', 'qty' => 1], ['item_id' => 'i-patty', 'qty' => 1]],
        ]);

        $this->assertSame([['item_id' => 'i-bread', 'qty' => 1], ['item_id' => 'i-patty', 'qty' => 1]], $saved);
    }

    public function test_a_missing_or_archived_ingredient_is_rejected(): void
    {
        $this->found(); // none of the ids is an active ingredient
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'name' => 'Burger', 'size' => 'M', 'price' => 45,
            'ingredients' => [['item_id' => 'gone', 'qty' => 1]],
        ]);
    }

    public function test_every_ingredient_needs_an_amount_above_zero(): void
    {
        $this->found('i-bread');
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'name' => 'Burger', 'size' => 'M', 'price' => 45,
            'ingredients' => [['item_id' => 'i-bread', 'qty' => 0]],
        ]);
    }

    public function test_the_same_ingredient_cannot_be_listed_twice(): void
    {
        $this->ingredients->shouldReceive('activeByIds')->never();
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create([
            'name' => 'Burger', 'size' => 'M', 'price' => 45,
            'ingredients' => [['item_id' => 'i-bread', 'qty' => 1], ['item_id' => 'i-bread', 'qty' => 2]],
        ]);
    }

    public function test_changing_the_recipe_replaces_it_and_is_logged(): void
    {
        $this->found('i-bread', 'i-patty');
        $this->products->shouldReceive('findActive')->andReturn($this->burger()); // no recipe yet
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);

        $sent = 'not called';
        $this->products->shouldReceive('update')->once()->andReturnUsing(function ($p, $d, $r = null) use (&$sent) {
            $sent = $r;

            return ($this->applyUpdate())($p, $d, $r);
        });
        $this->audit->shouldReceive('record')->once()
            ->with('recipe change', 'Burger M recipe: none to Bread 1, Patty 1', 'user-1');

        $this->service->update('p-1', [
            'ingredients' => [['item_id' => 'i-bread', 'qty' => 1], ['item_id' => 'i-patty', 'qty' => 1]],
        ], 'user-1');

        $this->assertSame([['item_id' => 'i-bread', 'qty' => 1], ['item_id' => 'i-patty', 'qty' => 1]], $sent);
    }

    public function test_an_unchanged_recipe_is_not_logged(): void
    {
        $this->found('i-bread');
        $this->products->shouldReceive('findActive')->andReturn($this->burger([['i-bread', 1.0]]));
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('update')->andReturnUsing($this->applyUpdate());
        $this->audit->shouldReceive('record')->never();

        $this->service->update('p-1', ['ingredients' => [['item_id' => 'i-bread', 'qty' => 1]]], 'user-1');
        $this->addToAssertionCount(1);
    }

    public function test_sending_no_ingredients_key_leaves_the_recipe_alone(): void
    {
        $this->products->shouldReceive('findActive')->andReturn($this->burger([['i-bread', 1.0]]));
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);

        $sent = 'not called';
        $this->products->shouldReceive('update')->once()->andReturnUsing(function ($p, $d, $r = null) use (&$sent) {
            $sent = $r;

            return $p->forceFill($d);
        });

        $this->service->update('p-1', ['name' => 'Big Burger'], 'user-1');

        $this->assertNull($sent);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Product;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = Mockery::mock(ProductRepository::class);
        $this->ingredients = Mockery::mock(IngredientRepository::class);
        $this->audit = Mockery::mock(AuditLogRepository::class);
        $this->service = new ProductService($this->products, $this->ingredients, $this->audit);
    }

    private function milkTea(): Product
    {
        return (new Product)->forceFill([
            'id' => 'p-1', 'name' => 'Milk Tea', 'size' => 'M', 'price' => 45, 'item_id' => null, 'qty' => 0,
        ]);
    }

    public function test_price_change_is_written_to_the_audit_log(): void
    {
        $this->products->shouldReceive('findActive')->andReturn($this->milkTea());
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('update')->andReturnUsing(fn ($p, $d) => $p->forceFill($d));
        $this->audit->shouldReceive('record')->once()->with('price change', 'Milk Tea M: 45.00 to 50.00', 'user-1');

        $result = $this->service->update('p-1', ['price' => 50], 'user-1');

        $this->assertSame('50.00', $result->price);
    }

    public function test_same_price_is_not_logged(): void
    {
        $this->products->shouldReceive('findActive')->andReturn($this->milkTea());
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('update')->andReturnUsing(fn ($p, $d) => $p->forceFill($d));
        $this->audit->shouldReceive('record')->never();

        $this->service->update('p-1', ['price' => 45], 'user-1');
        $this->addToAssertionCount(1);
    }

    public function test_duplicate_name_and_size_is_rejected(): void
    {
        $this->products->shouldReceive('nameSizeTaken')->andReturn(true);
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Milk Tea', 'size' => 'M', 'price' => 45]);
    }

    public function test_drink_with_ingredient_needs_an_amount(): void
    {
        $this->ingredients->shouldReceive('find')->andReturn((new Item)->forceFill(['id' => 'i-1']));
        $this->products->shouldReceive('nameSizeTaken')->andReturn(false);
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Milk Tea', 'size' => 'M', 'price' => 45, 'item_id' => 'i-1', 'qty' => 0]);
    }

    public function test_archived_or_missing_ingredient_is_rejected(): void
    {
        $this->ingredients->shouldReceive('find')->andReturn(null);
        $this->products->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->create(['name' => 'Milk Tea', 'size' => 'M', 'price' => 45, 'item_id' => 'gone', 'qty' => 20]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Repositories\AuditLogRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\ProductRepository;
use App\Services\IngredientService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class IngredientServiceTest extends TestCase
{
    private MockInterface $ingredients;
    private MockInterface $products;
    private MockInterface $audit;
    private IngredientService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ingredients = Mockery::mock(IngredientRepository::class);
        $this->products = Mockery::mock(ProductRepository::class);
        $this->audit = Mockery::mock(AuditLogRepository::class);
        $this->service = new IngredientService($this->ingredients, $this->products, $this->audit);
    }

    private function milk(): Item
    {
        return (new Item)->forceFill(['id' => 'i-1', 'item' => 'Milk', 'qty' => 10, 'unit' => 'L']);
    }

    public function test_cannot_archive_an_ingredient_that_a_drink_uses(): void
    {
        $this->ingredients->shouldReceive('findActiveForUpdate')->andReturn($this->milk());
        $this->products->shouldReceive('namesUsingIngredient')->andReturn(['Milk Tea']);
        $this->ingredients->shouldReceive('archive')->never();

        $this->expectException(ValidationException::class);

        $this->service->archive('i-1', 'user-1');
    }

    public function test_unused_ingredient_is_archived_and_logged(): void
    {
        $this->ingredients->shouldReceive('findActiveForUpdate')->andReturn($this->milk());
        $this->products->shouldReceive('namesUsingIngredient')->andReturn([]);
        $this->ingredients->shouldReceive('archive')->once();
        $this->audit->shouldReceive('record')->once()->with('ingredient archived', 'Milk', 'user-1');

        $this->service->archive('i-1', 'user-1');
        $this->addToAssertionCount(1);
    }

    public function test_manual_stock_change_is_logged(): void
    {
        $this->ingredients->shouldReceive('findActiveForUpdate')->andReturn($this->milk());
        $this->ingredients->shouldReceive('update')->andReturnUsing(fn ($i, $d) => $i->forceFill($d));
        $this->audit->shouldReceive('record')->once()->with('stock adjust', 'Milk: 10.000 to 12.000 L', 'user-1');

        $this->service->update('i-1', ['qty' => 12], 'user-1');
        $this->addToAssertionCount(1);
    }
}

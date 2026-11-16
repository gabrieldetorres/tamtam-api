<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\AuditLogRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    private MockInterface $orders;
    private MockInterface $products;
    private MockInterface $stock;
    private MockInterface $customers;
    private MockInterface $audit;
    private OrderService $service;

    /** What the service passed to OrderRepository::create(), captured for assertions. */
    private ?array $saved = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = Mockery::mock(OrderRepository::class);
        $this->products = Mockery::mock(ProductRepository::class);
        $this->stock = Mockery::mock(StockRepository::class);
        $this->customers = Mockery::mock(CustomerRepository::class);
        $this->audit = Mockery::mock(AuditLogRepository::class);

        $this->service = new OrderService(
            $this->orders, $this->products, $this->stock, $this->customers, $this->audit
        );

        $this->orders->shouldReceive('findByClientRef')->andReturn(null)->byDefault();
        $this->orders->shouldReceive('create')->andReturnUsing(function ($order, $rows) {
            $this->saved = ['order' => $order, 'rows' => $rows];

            return (new Order)->forceFill($order + ['order_no' => 7]);
        })->byDefault();
    }

    private function drink(string $id, string $name, float $price, ?string $itemId = null, float $qty = 0): Product
    {
        return (new Product)->forceFill([
            'id' => $id, 'name' => $name, 'size' => 'M', 'price' => $price, 'item_id' => $itemId, 'qty' => $qty,
        ]);
    }

    private function menu(Product ...$drinks): void
    {
        $this->products->shouldReceive('activeByIds')->andReturn(collect($drinks)->keyBy('id'));
    }

    private function stockOf(string $id, string $name, float $qty): void
    {
        $item = (new Item)->forceFill(['id' => $id, 'item' => $name, 'qty' => $qty, 'unit' => 'L']);
        $this->stock->shouldReceive('lockForUpdate')->andReturn(collect([$id => $item]));
    }

    public function test_total_and_change_are_calculated_on_the_server(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45), $this->drink('p2', 'Coffee', 30));

        [$order, $created] = $this->service->place([
            'items' => [['product_id' => 'p1', 'qty' => 2], ['product_id' => 'p2', 'qty' => 1]],
            'tendered' => 200,
        ], 'user-1');

        $this->assertTrue($created);
        $this->assertSame('paid', $this->saved['order']['status']);
        $this->assertSame('120.00', $this->saved['order']['total']);
        $this->assertSame('200.00', $this->saved['order']['tendered']);
        $this->assertSame('80.00', $this->saved['order']['change_due']);
        $this->assertCount(2, $this->saved['rows']);
        $this->assertSame('Milk Tea', $this->saved['rows'][0]['name']);   // snapshot of the name
        $this->assertSame('45.00', $this->saved['rows'][0]['price']);     // snapshot of the price
    }

    public function test_cash_below_the_total_is_rejected(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45));
        $this->orders->shouldReceive('create')->never();
        $this->stock->shouldReceive('deduct')->never();

        $this->expectException(ValidationException::class);

        $this->service->place(['items' => [['product_id' => 'p1', 'qty' => 2]], 'tendered' => 89.99], 'user-1');
    }

    public function test_the_same_drink_listed_twice_becomes_one_line(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45));

        $this->service->place([
            'items' => [['product_id' => 'p1', 'qty' => 1], ['product_id' => 'p1', 'qty' => 2]],
            'tendered' => 500,
        ], 'user-1');

        $this->assertCount(1, $this->saved['rows']);
        $this->assertSame(3, $this->saved['rows'][0]['qty']);
        $this->assertSame('135.00', $this->saved['order']['total']);
    }

    public function test_a_drink_that_is_not_on_the_menu_is_rejected(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45));
        $this->orders->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->place(['items' => [['product_id' => 'gone', 'qty' => 1]], 'tendered' => 100], 'user-1');
    }

    public function test_stock_is_deducted_once_for_all_drinks_that_share_an_ingredient(): void
    {
        // 3 x 0.2 L + 2 x 0.3 L = 1.2 L of milk
        $this->menu(
            $this->drink('p1', 'Milk Tea', 45, 'i-1', 0.2),
            $this->drink('p2', 'Latte', 60, 'i-1', 0.3),
        );
        $this->stockOf('i-1', 'Milk', 10);
        $this->stock->shouldReceive('deduct')->once()->with('i-1', '1.200');

        $this->service->place([
            'items' => [['product_id' => 'p1', 'qty' => 3], ['product_id' => 'p2', 'qty' => 2]],
            'tendered' => 1000,
        ], 'user-1');

        $this->addToAssertionCount(1);
    }

    public function test_not_enough_stock_stops_the_whole_sale(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45, 'i-1', 0.6));
        $this->stockOf('i-1', 'Milk', 1.0); // 2 cups need 1.2 L
        $this->stock->shouldReceive('deduct')->never();
        $this->orders->shouldReceive('create')->never();

        try {
            $this->service->place(['items' => [['product_id' => 'p1', 'qty' => 2]], 'tendered' => 100], 'user-1');
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Not enough Milk', $e->errors()['items'][0]);
        }
    }

    public function test_voiding_a_cart_uses_no_stock_and_is_logged(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45, 'i-1', 0.2));
        $this->stock->shouldReceive('lockForUpdate')->never();
        $this->stock->shouldReceive('deduct')->never();
        $this->audit->shouldReceive('record')->once()->with('void order', 'Order #7 total 90.00', 'user-1');

        [$order] = $this->service->void(['items' => [['product_id' => 'p1', 'qty' => 2]]], 'user-1');

        $this->assertSame('void', $this->saved['order']['status']);
        $this->assertArrayNotHasKey('tendered', $this->saved['order']);
    }

    public function test_sending_the_same_checkout_twice_returns_the_first_order(): void
    {
        $first = (new Order)->forceFill(['id' => 'o-1', 'order_no' => 3, 'total' => 45]);
        $this->orders->shouldReceive('findByClientRef')->with('abc-123')->andReturn($first);
        $this->orders->shouldReceive('create')->never();
        $this->stock->shouldReceive('deduct')->never();

        [$order, $created] = $this->service->place([
            'client_ref' => 'abc-123',
            'items' => [['product_id' => 'p1', 'qty' => 1]],
            'tendered' => 100,
        ], 'user-1');

        $this->assertFalse($created);
        $this->assertSame(3, $order->order_no);
    }

    public function test_an_archived_customer_is_rejected(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45));
        $this->customers->shouldReceive('findActive')
            ->andThrow(new \Illuminate\Database\Eloquent\ModelNotFoundException);
        $this->orders->shouldReceive('create')->never();

        $this->expectException(ValidationException::class);

        $this->service->place([
            'customer_id' => 'c-1',
            'items' => [['product_id' => 'p1', 'qty' => 1]],
            'tendered' => 100,
        ], 'user-1');
    }

    public function test_an_active_customer_is_attached_to_the_order(): void
    {
        $this->menu($this->drink('p1', 'Milk Tea', 45));
        $this->customers->shouldReceive('findActive')->andReturn((new Customer)->forceFill(['id' => 'c-1']));

        $this->service->place([
            'customer_id' => 'c-1',
            'items' => [['product_id' => 'p1', 'qty' => 1]],
            'tendered' => 100,
        ], 'user-1');

        $this->assertSame('c-1', $this->saved['order']['customer_id']);
    }
}

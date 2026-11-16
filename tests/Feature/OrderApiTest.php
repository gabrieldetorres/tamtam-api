<?php

namespace Tests\Feature;

use App\Services\SupabaseTokenVerifier;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    private const PRODUCT = '11111111-1111-4111-8111-111111111111';

    public function test_orders_require_a_login(): void
    {
        $this->postJson('/api/v1/orders', [])->assertStatus(401);
        $this->postJson('/api/v1/orders/void', [])->assertStatus(401);
    }

    public function test_an_order_needs_items(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/orders', ['items' => [], 'tendered' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_quantity_must_be_a_positive_whole_number(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => self::PRODUCT, 'qty' => 0]], 'tendered' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.qty');

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => self::PRODUCT, 'qty' => 1.5]], 'tendered' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.qty');
    }

    public function test_a_paid_order_needs_the_cash_amount(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => self::PRODUCT, 'qty' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('tendered');
    }

    public function test_the_browser_cannot_send_its_own_prices(): void
    {
        $this->actingAsAdmin();

        // product_id must be a uuid; a made-up item with a price is rejected before any work is done
        $this->postJson('/api/v1/orders', ['items' => [['product_id' => 'free-drink', 'qty' => 1, 'price' => 0]], 'tendered' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_a_voided_cart_still_needs_items(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/orders/void', [])
            ->assertStatus(422)->assertJsonValidationErrors('items');
    }

    private function actingAsAdmin(): void
    {
        config(['services.supabase.admin_emails' => []]);

        $this->app->instance(SupabaseTokenVerifier::class, new class extends SupabaseTokenVerifier {
            public function verify(string $token): array
            {
                return ['sub' => 'user-123', 'email' => 'admin@example.com'];
            }
        });

        $this->withToken('test-token');
    }
}

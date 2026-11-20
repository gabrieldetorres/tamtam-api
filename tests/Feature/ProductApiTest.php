<?php

namespace Tests\Feature;

use App\Services\SupabaseTokenVerifier;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    private const BREAD = '11111111-1111-4111-8111-111111111111';
    private const PATTY = '22222222-2222-4222-8222-222222222222';

    public function test_products_require_a_login(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/products', ['name' => 'Burger', 'price' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_name_is_required(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/products', ['price' => 45])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_malformed_id_is_a_clean_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/products/not-a-uuid', ['price' => 10])->assertStatus(404);
    }

    public function test_a_recipe_amount_must_be_above_zero(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/products', [
            'name' => 'Burger', 'price' => 60,
            'ingredients' => [['item_id' => self::BREAD, 'qty' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('ingredients.0.qty');
    }

    public function test_a_recipe_ingredient_must_be_a_valid_id(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/products', [
            'name' => 'Burger', 'price' => 60,
            'ingredients' => [['item_id' => 'bread', 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('ingredients.0.item_id');
    }

    public function test_the_same_ingredient_twice_in_a_recipe_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/products/33333333-3333-4333-8333-333333333333', [
            'ingredients' => [
                ['item_id' => self::BREAD, 'qty' => 1],
                ['item_id' => self::BREAD, 'qty' => 2],
            ],
        ])->assertStatus(422);
    }

    public function test_a_recipe_cannot_be_longer_than_twenty_ingredients(): void
    {
        $this->actingAsAdmin();

        $many = array_fill(0, 21, ['item_id' => self::PATTY, 'qty' => 1]);

        $this->postJson('/api/v1/products', ['name' => 'Burger', 'price' => 60, 'ingredients' => $many])
            ->assertStatus(422)->assertJsonValidationErrors('ingredients');
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

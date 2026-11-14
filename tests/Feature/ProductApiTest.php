<?php

namespace Tests\Feature;

use App\Services\SupabaseTokenVerifier;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    public function test_products_require_a_login(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/products', ['name' => 'Milk Tea', 'price' => -5])
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

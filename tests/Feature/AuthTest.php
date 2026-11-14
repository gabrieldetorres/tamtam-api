<?php

namespace Tests\Feature;

use App\Services\SupabaseTokenVerifier;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_request_without_token_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->fakeVerifier(fn () => throw new \RuntimeException('bad token'));

        $this->withToken('garbage')->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_valid_token_returns_the_user(): void
    {
        config(['services.supabase.admin_emails' => []]);
        $this->fakeVerifier(fn () => ['sub' => 'user-123', 'email' => 'admin@example.com']);

        $this->withToken('any')->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson(['id' => 'user-123', 'email' => 'admin@example.com']);
    }

    public function test_email_not_on_admin_list_is_forbidden(): void
    {
        config(['services.supabase.admin_emails' => ['admin@example.com']]);
        $this->fakeVerifier(fn () => ['sub' => 'user-999', 'email' => 'stranger@example.com']);

        $this->withToken('any')->getJson('/api/v1/auth/me')->assertStatus(403);
    }

    private function fakeVerifier(callable $fn): void
    {
        $this->app->instance(SupabaseTokenVerifier::class, new class($fn) extends SupabaseTokenVerifier {
            public function __construct(private $fn) {}

            public function verify(string $token): array
            {
                return ($this->fn)($token);
            }
        });
    }
}

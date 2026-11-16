<?php

namespace Tests\Feature;

use App\Services\SupabaseTokenVerifier;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    public function test_reports_require_a_login(): void
    {
        $this->getJson('/api/v1/reports/summary')->assertStatus(401);
        $this->get('/api/v1/reports/export')->assertStatus(401);
    }

    public function test_dates_must_look_like_yyyy_mm_dd(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/reports/summary?from=16-11-2026')
            ->assertStatus(422)->assertJsonValidationErrors('from');

        $this->getJson('/api/v1/reports/summary?to=yesterday')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_an_end_date_before_the_start_date_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/reports/summary?from=2026-11-16&to=2026-11-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
    }

    public function test_a_range_over_a_year_is_rejected_before_any_export_starts(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/reports/export?from=2020-01-01&to=2026-11-16')
            ->assertStatus(422)->assertJsonValidationErrors('to');
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

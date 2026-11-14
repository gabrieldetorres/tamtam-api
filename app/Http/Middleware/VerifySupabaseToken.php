<?php

namespace App\Http\Middleware;

use App\Services\SupabaseTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Every protected route passes through here: no valid Supabase token, no entry. */
class VerifySupabaseToken
{
    public function __construct(private SupabaseTokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing token.'], 401);
        }

        try {
            $user = $this->verifier->verify($token);
        } catch (Throwable $e) {
            Log::warning('Token rejected: '.$e->getMessage());

            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        // Extra safety: if ADMIN_EMAILS is set, only those emails may use the API,
        // even if someone manages to create another Supabase account.
        $allowed = array_map('strtolower', config('services.supabase.admin_emails', []));

        Log::info('Admin email check', [
    'verified_email' => $user['email'] ?? null,
    'allowed_emails' => $allowed,
    'email_matches' => in_array(
        strtolower(trim((string) ($user['email'] ?? ''))),
        array_map('trim', $allowed),
        true
    ),
]);

        if ($allowed && ! in_array(strtolower((string) $user['email']), $allowed, true)) {
            return response()->json(['message' => 'Not allowed.'], 403);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}

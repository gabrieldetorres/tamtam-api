<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Business layer: checks that a Supabase access token is real, not expired,
 * and issued by OUR Supabase project.
 *
 *  - ES256 / RS256 tokens (asymmetric signing keys): verified locally against
 *    the project's public keys (JWKS), cached for 10 minutes.
 *  - HS256 tokens (legacy shared secret): verified by asking Supabase Auth.
 *  - Any other algorithm is rejected.
 */
class SupabaseTokenVerifier
{
    /** @return array{sub: string, email: ?string} */
    public function verify(string $token): array
    {
        $alg = $this->headerAlg($token);

        return match ($alg) {
            'ES256', 'RS256' => $this->verifyLocally($token),
            'HS256' => $this->verifyWithAuthServer($token),
            default => throw new RuntimeException('Unsupported token algorithm.'),
        };
    }

    private function verifyLocally(string $token): array
    {
        JWT::$leeway = 30; // tolerate small clock differences (seconds)

        try {
            $claims = (array) JWT::decode($token, JWK::parseKeySet($this->jwks()));
        } catch (\Throwable $e) {
            // Keys may have been rotated: fetch fresh keys and try once more.
            Cache::forget('supabase_jwks');
            $claims = (array) JWT::decode($token, JWK::parseKeySet($this->jwks()));
        }

        if (($claims['iss'] ?? null) !== $this->authUrl()) {
            throw new RuntimeException('Wrong token issuer.');
        }

        $aud = (array) ($claims['aud'] ?? []);
        if (! in_array('authenticated', $aud, true)) {
            throw new RuntimeException('Wrong token audience.');
        }

        if (empty($claims['sub'])) {
            throw new RuntimeException('Token has no subject.');
        }

        return ['sub' => (string) $claims['sub'], 'email' => $claims['email'] ?? null];
    }

    private function verifyWithAuthServer(string $token): array
    {
        $res = Http::timeout(5)->withHeaders([
            'apikey' => (string) config('services.supabase.publishable_key'),
            'Authorization' => 'Bearer '.$token,
        ])->get($this->authUrl().'/user');

        if (! $res->ok() || empty($res->json('id'))) {
            throw new RuntimeException('Supabase rejected the token.');
        }

        return ['sub' => (string) $res->json('id'), 'email' => $res->json('email')];
    }

    private function jwks(): array
    {
        return Cache::remember('supabase_jwks', 600, function () {
            $res = Http::timeout(5)->get($this->authUrl().'/.well-known/jwks.json');

            if (! $res->ok() || empty($res->json('keys'))) {
                throw new RuntimeException('Could not load Supabase signing keys.');
            }

            return $res->json();
        });
    }

    private function authUrl(): string
    {
        return rtrim((string) config('services.supabase.url'), '/').'/auth/v1';
    }

    private function headerAlg(string $token): string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token.');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);

        return is_array($header) ? (string) ($header['alg'] ?? '') : '';
    }
}

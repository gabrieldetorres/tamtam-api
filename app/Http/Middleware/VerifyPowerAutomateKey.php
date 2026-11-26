<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPowerAutomateKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.power_automate.api_key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            return response()->json([
                'message' => 'Automation authentication is not configured.',
            ], 503);
        }

        $providedKey = $request->header('X-TAMTAM-AUTOMATION-KEY', '');

        if (! is_string($providedKey) || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Laravel adds the /api prefix automatically, so this is GET /api/v1/health
Route::prefix('v1')->group(function () {

    // Public check: is the API up, which environment, can it reach the database?
    Route::get('/health', function () {
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (Throwable $e) {
            $db = 'error';
        }

        return response()->json([
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database' => $db,
        ]);
    });

    // Protected routes (products, orders, ...) will go here behind the auth middleware.
});

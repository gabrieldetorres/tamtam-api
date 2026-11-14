<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// {id} must look like a UUID, otherwise Postgres would throw an error (500) instead of a clean 404.
Route::pattern('id', '[0-9a-fA-F-]{36}');

// Laravel adds the /api prefix automatically, so these are /api/v1/...
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

    // Everything below requires a valid Supabase login token.
    Route::middleware('supabase.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        Route::get('/ingredients', [IngredientController::class, 'index']);
        Route::post('/ingredients', [IngredientController::class, 'store']);
        Route::put('/ingredients/{id}', [IngredientController::class, 'update']);
        Route::delete('/ingredients/{id}', [IngredientController::class, 'destroy']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::patch('/customers/{id}/archive', [CustomerController::class, 'archive']);

        Route::get('/audit-log', [AuditLogController::class, 'index']);

        // orders and reports arrive in the next phases.
    });
});

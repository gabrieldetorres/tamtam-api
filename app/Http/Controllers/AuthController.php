<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

    return response()->json([
        'id' => $user['sub'] ?? $user['id'] ?? null,
        'email' => $user['email'] ?? null,
    ]);
        
    }
    }
?>
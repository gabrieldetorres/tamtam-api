<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Supabase user id of the logged-in admin (set by the supabase.auth middleware). */
    protected function userId(Request $request): string
    {
        return $request->attributes->get('auth_user')['sub'];
    }
}
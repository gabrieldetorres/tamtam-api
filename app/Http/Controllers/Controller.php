<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    
    protected function userId(Request $request): string
    {
        return $request->attributes->get('auth_user')['sub'];
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    // Messages in the user's language, like the push notifications
    protected function language(Request $request): string
    {
        return $request->user()->language ?: 'nl';
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppVersionResource;

/**
 * The oldest app version (and build) that still works and where to update, per platform (config/app_version.php). The app asks
 * at launch, before logging in, so it needs no login.
 */
class AppVersionController extends Controller
{
    public function show(): AppVersionResource
    {
        return new AppVersionResource(config('app_version'));
    }
}

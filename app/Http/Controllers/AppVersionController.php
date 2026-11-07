<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The oldest app version (and build) that still works and where to update, per platform (config/app_version.php). The app asks
 * at launch, before logging in, so it needs no login.
 */
class AppVersionController extends Controller
{
    public function show(): JsonResponse
    {
        $platform = fn (string $name) => [
            'minimum_version' => (string) config("app_version.minimum.$name"),
            'minimum_build' => (int) config("app_version.minimum_build.$name"),
            'update_url' => (string) config("app_version.update_url.$name"),
        ];

        return response()->json(['data' => ['ios' => $platform('ios'), 'android' => $platform('android')]]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => auth()->user()->notificationSettings()]);
    }

    // Accepts any subset of the settings, e.g. {"items_added": false}; the others keep their value
    public function update(Request $request): JsonResponse
    {
        $rules = collect(User::NOTIFICATION_SETTINGS)
            ->mapWithKeys(fn ($types, string $setting) => [$setting => ['sometimes', 'boolean']])
            ->all();
        $validated = $request->validate($rules);

        /** @var User $user */
        $user = auth()->user();
        $user->notification_settings = array_merge(
            $user->notificationSettings(),
            array_map(fn ($value) => (bool) $value, $validated),
        );
        $user->save();

        return response()->json(['data' => $user->notificationSettings()]);
    }
}

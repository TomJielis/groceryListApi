<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\NotificationSettingsRequest;
use App\Http\Resources\NotificationSettingsResource;
use Illuminate\Http\Request;

// Which pushes the user wants; everything is on unless they switched it off (User::NOTIFICATION_SETTINGS)
class NotificationSettingsController extends Controller
{
    public function show(Request $request): NotificationSettingsResource
    {
        return new NotificationSettingsResource($request->user());
    }

    // Accepts any subset of the settings, e.g. {"items_added": false}; the others keep their value
    public function update(NotificationSettingsRequest $request): NotificationSettingsResource
    {
        $user = $request->user();
        $user->notification_settings = array_merge($user->notificationSettings(), $request->settings());
        $user->save();

        return new NotificationSettingsResource($user);
    }
}

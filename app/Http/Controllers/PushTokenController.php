<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class PushTokenController extends Controller
{
    /**
     * Saves the Expo push token of the device the user is logged in on.
     * A device that switches account moves to the new user, so pushes never reach the previous one.
     * It is tied to the login it was sent with: when that login ends, the device stops getting pushes.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
        ]);

        $accessToken = $request->user()->currentAccessToken();

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => auth()->id(),
                'access_token_id' => $accessToken instanceof PersonalAccessToken ? $accessToken->id : null,
            ],
        );

        return response()->json(['message' => 'Push token opgeslagen']);
    }

    /**
     * The app on this device can't show notifications (switched off in the phone's settings): stop pushing to it, so
     * what would have been a push (e.g. a support answer) goes by mail instead
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken) {
            $request->user()->pushTokens()->where('access_token_id', $accessToken->id)->delete();
        }

        return response()->json(['message' => 'Push token verwijderd']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * Saves the Expo push token of the device the user is logged in on.
     * A device that switches account moves to the new user, so pushes never reach the previous one.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
        ]);

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            ['user_id' => auth()->id()],
        );

        return response()->json(['message' => 'Push token opgeslagen']);
    }
}

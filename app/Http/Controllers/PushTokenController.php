<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushToken\PushTokenStoreRequest;
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
    public function store(PushTokenStoreRequest $request): JsonResponse
    {
        PushToken::updateOrCreate(
            ['token' => $request->token()],
            ['user_id' => $request->user()->id, 'access_token_id' => $this->loginId($request)],
        );

        return response()->json(['message' => 'Push token opgeslagen']);
    }

    /**
     * The app on this device can't show notifications (switched off in the phone's settings): stop pushing to it, so
     * what would have been a push (e.g. a support answer) goes by mail instead
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $loginId = $this->loginId($request);
        if ($loginId !== null) {
            $request->user()->pushTokens()->where('access_token_id', $loginId)->delete();
        }

        return response()->json(['message' => 'Push token verwijderd']);
    }

    // The login (Sanctum token) the request came with; none for a session or a test's actingAs
    private function loginId(Request $request): ?int
    {
        $accessToken = $request->user()->currentAccessToken();

        return $accessToken instanceof PersonalAccessToken ? $accessToken->id : null;
    }
}

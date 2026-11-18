<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ApproveTermsRequest;
use App\Http\Requests\User\CheckResetLinkRequest;
use App\Http\Requests\User\LoginRequest;
use App\Http\Requests\User\LogoutRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\SetNewPasswordRequest;
use App\Http\Requests\User\UpdateLanguageRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Logging in and out, registering, the password reset and the user's own account. Login and registration answer a
 * failure with HTTP 200 and `success: false` plus `error` (also their rate limits, see AppServiceProvider): the app and
 * the website show that message.
 */
class AuthController extends Controller
{
    public function __construct(private AccountService $accounts)
    {
    }

    public function register(RegisterUserRequest $request): RegistrationResource|JsonResponse
    {
        $account = $request->account();
        if (!$this->accounts->register($account)) {
            return response()->json(['message' => 'Email versturen is mislukt'], 500);
        }

        return new RegistrationResource($account);
    }

    public function login(LoginRequest $request): LoginResource|JsonResponse
    {
        $result = $this->accounts->login($request->email(), $request->password(), $request);

        return $result->failedToLogIn() ? response()->json($result->toArray()) : new LoginResource($result);
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->name = $request->name();
        $user->save();

        return new UserResource($user);
    }

    public function updateLanguage(UpdateLanguageRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->language = $request->language();
        $user->save();

        return new UserResource($user);
    }

    public function approveTerms(ApproveTermsRequest $request): UserResource
    {
        $this->accounts->acceptTerms($request->user(), $request->termsVersion());

        return new UserResource($request->user());
    }

    // `reason` lets the app and the website show a fitting message (they read it from an HTTP 200 answer)
    public function verifyUser(string $token): JsonResponse
    {
        return response()->json(match ($this->accounts->verifyEmail($token)) {
            AccountService::INVALID_LINK => ['success' => false, 'reason' => 'invalid_link', 'message' => 'Deze link is ongeldig.'],
            AccountService::ALREADY_VERIFIED => ['success' => false, 'reason' => 'already_verified', 'message' => 'Gebruiker is al geverifieerd'],
            AccountService::VERIFIED => ['success' => true, 'message' => 'Gebruiker is geverifieerd'],
        });
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email())->first();

        // Without an account the same answer as when an email is sent, so nobody can find out which addresses have one
        if ($user && ($error = $this->accounts->sendResetLink($user, $request->email()))) {
            return response()->json(['message' => $error], 500);
        }

        return response()->json(['message' => 'Wachtwoordherstel-e-mail verzonden']);
    }

    /**
     * Whether a reset link can still be used, so the page can say "request a new one" before the user types
     * anything. The same rules as setNewPassword: not used yet and at most an hour old.
     */
    public function checkResetLink(CheckResetLinkRequest $request): JsonResponse
    {
        $token = $request->token();

        return response()->json(['valid' => $token !== null && $this->accounts->usableResetCode($token) !== null]);
    }

    public function setNewPassword(SetNewPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email())->first();
        $code = $user ? $this->accounts->usableResetCode($request->token(), $user) : null;

        // Used before, expired, or not a real link
        if (!$code) {
            return response()->json([
                'reason' => 'invalid_link',
                'message' => 'Deze link is ongeldig, al gebruikt of verlopen. Vraag een nieuwe aan.',
            ], 400);
        }

        $this->accounts->setNewPassword($user, $code, $request->password());

        return response()->json(['message' => 'Wachtwoord is geupdate']);
    }

    // Answers an empty 200, as it always did
    public function deactivate(Request $request): void
    {
        $this->accounts->deactivate($request->user());
    }

    /**
     * Revokes only the token of this device; other devices stay logged in.
     * The app also sends its push token, so this device stops getting notifications for this account.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        if ($pushToken = $request->pushToken()) {
            $request->user()->pushTokens()->where('token', $pushToken)->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }
}

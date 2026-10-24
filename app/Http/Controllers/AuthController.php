<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use App\Http\Requests\User\RegisterUserRequest;
use App\Jobs\Users\LoginJob;
use App\Jobs\Users\StoreUserJob;
use App\Jobs\Users\UpdateUserJob;
use App\Mail\ResetPassword;
use App\Models\TemporaryPasswordCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
    private const RESET_LINK_MINUTES = 60;
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        // Answered like a failed login (HTTP 200 with `error`): the app and the website show that message
        if ($error = $this->registrationError($request)) {
            return response()->json(['success' => false, 'error' => $error, 'message' => $error, 'status' => 422]);
        }

        $user = (new StoreUserJob(array_merge($request->all(), ['email' => trim($request->input('email'))])))->handle();
        return response()->json($user);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        // Nothing to check without both fields: answer like any failed login (the app and website read
        // `success`/`error`), but don't record it, so empty bot requests don't fill the failed-login list
        if (!is_string($request->input('email')) || trim($request->input('email')) === ''
            || !is_string($request->input('password')) || $request->input('password') === '') {
            return response()->json([
                'error' => 'Email and password are required',
                'success' => false,
                'status' => 422,
            ]);
        }

        $user = (new LoginJob($request))->handle();
        return response()->json($user);
    }

    public function update(Request $request): JsonResponse
    {
        $request = $request->all();

        /** @var User $user */
        $user = \auth()->user();
        $user->name = $request['body']['name'];
        $user->save();

        return response()->json($user);
    }

    public function updateLanguage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = \auth()->user();
        $user->language = $request->get('language');
        $user->save();
        return response()->json($user);
    }

    public function approveTerms(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = \auth()->user();
        $user->accepted_terms = true;
        $user->accepted_terms_at = Carbon::now();
        $user->accepted_terms_version = $request->get('acceptedTermsVersion');
        $user->save();
        return response()->json($user);
    }

    public function verifyUser(Request $request, string $token)
    {
        // `reason` lets the app and the website show a fitting message (they read it from an HTTP 200 answer)
        try {
            $user = User::find(Crypt::decryptString($token));
        } catch (DecryptException) {
            $user = null;
        }

        if (!$user) {
            return response()->json(['success' => false, 'reason' => 'invalid_link', 'message' => 'Deze link is ongeldig.']);
        }

        // Opening the link again: nothing to do, the account is ready to use
        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'reason' => 'already_verified', 'message' => 'Gebruiker is al geverifieerd']);
        }

        $user->email_verified_at = Carbon::now();
        $user->save();

        return response()->json(['success' => true, 'message' => 'Gebruiker is geverifieerd'], 200);

    }

    // The first problem with a registration, in the language the user picked; null when it's fine
    private function registrationError(Request $request): ?string
    {
        $english = $request->input('language') === 'en';
        $data = array_merge($request->all(), ['email' => is_string($request->input('email')) ? trim($request->input('email')) : null]);
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:' . self::MIN_PASSWORD_LENGTH . '|max:255',
        ]);

        if ($validator->errors()->has('name')) {
            return $english ? 'Please enter your name.' : 'Vul je naam in.';
        }
        if ($validator->errors()->has('email')) {
            return $english ? 'Please enter a valid email address.' : 'Vul een geldig e-mailadres in.';
        }
        if ($validator->errors()->has('password')) {
            return $english
                ? 'Your password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
                : 'Je wachtwoord moet minimaal ' . self::MIN_PASSWORD_LENGTH . ' tekens hebben.';
        }
        if (User::where('email', $data['email'])->exists()) {
            return $english ? 'An account with this email address already exists.' : 'Er bestaat al een account met dit e-mailadres.';
        }

        return null;
    }

    public function resetPassword(Request $request)
    {
        $email = $request->get('email');

        if (!$email) {
            return response()->json(['message' => 'Email is verplicht'], 400);
        }

        $user = User::where('email', $email)->first();

        // The same answer as when an email is sent, so nobody can find out which addresses have an account
        if(!$user)
        {
            return response()->json(['message' => 'Wachtwoordherstel-e-mail verzonden'], 200);
        }

        TemporaryPasswordCode::where('user_id', '=', $user->id)
            ->where('is_used', '=', false)
            ->delete();

        $code = Crypt::encryptString($user->email . '-' . Carbon::now() . '-' . $user->id);

        $temporaryPasswordCode = new TemporaryPasswordCode();
        $temporaryPasswordCode->user_id = $user->id;
        $temporaryPasswordCode->code = $code;
        $temporaryPasswordCode->is_used = false;
        $temporaryPasswordCode->save();

        $url = config('app.url') . '/auth/password/' . $code;

        $mail = new ResetPassword($url, $user);

        Config::set('mail.from', [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ]);

        try {
            app(\App\Services\MailService::class)->send($mail, $email);

        } catch (\Exception $exception) {
            \Log::error($exception->getMessage());
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json(['message' => 'Wachtwoordherstel-e-mail verzonden'], 200);

    }


    /**
     * Whether a reset link can still be used, so the page can say "request a new one" before the user types
     * anything. The same rules as setNewPassword: not used yet and at most an hour old.
     */
    public function checkResetLink(Request $request): JsonResponse
    {
        $token = $request->get('token');

        $valid = is_string($token) && TemporaryPasswordCode::where('code', $token)
            ->where('is_used', false)
            ->where('created_at', '>=', now()->subMinutes(self::RESET_LINK_MINUTES))
            ->exists();

        return response()->json(['valid' => $valid]);
    }

    public function setNewPassword(Request $request)
    {
        $email = $request->get('email');
        $token = $request->get('token');
        $newPassword = $request->get('password');

        if (!is_string($newPassword) || mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return response()->json([
                'reason' => 'password_too_short',
                'message' => 'Je wachtwoord moet minimaal ' . self::MIN_PASSWORD_LENGTH . ' tekens hebben.',
            ], 400);
        }
        if ($request->has('repeatPassword') && $request->get('repeatPassword') !== $newPassword) {
            return response()->json(['reason' => 'passwords_do_not_match', 'message' => 'De wachtwoorden zijn niet hetzelfde.'], 400);
        }

        $user = User::where('email', $email)->first();
        // A reset link works for an hour (and once)
        $temporaryPasswordCode = $user ? TemporaryPasswordCode::where('user_id', '=', $user->id)
            ->where('code', '=', $token)
            ->where('is_used', '=', false)
            ->where('created_at', '>=', now()->subMinutes(self::RESET_LINK_MINUTES))
            ->first() : null;

        // Used before, expired, or not a real link
        if (!$temporaryPasswordCode) {
            return response()->json([
                'reason' => 'invalid_link',
                'message' => 'Deze link is ongeldig, al gebruikt of verlopen. Vraag een nieuwe aan.',
            ], 400);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $temporaryPasswordCode->is_used = true;
        $temporaryPasswordCode->save();

        // Whoever knew the old password is logged out everywhere
        $user->tokens()->delete();

        return response()->json(['message' => 'Wachtwoord is geupdate'], 200);
    }

    public function deactivate(Request $request)
    {
        /** @var User $user */
        $user = \auth()->user();
        foreach ($user->groceryLists as $groceryList)
        {
            $groceryList->groceryListItems()->delete();
            $groceryList->groceryListInvites()->delete();
            $groceryList->delete();
        }

        $user->groceryListInvites()->delete();
        $user->cards()->delete();

        $user->temporaryPasswordCodes()->delete();
        $user->personalAccessTokens()->delete();
        $user->delete();
    }

    /**
     * Revokes only the token of this device; other devices stay logged in.
     * The app also sends its push token, so this device stops getting notifications for this account.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($pushToken = $request->get('pushToken')) {
            $request->user()->pushTokens()->where('token', $pushToken)->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd']);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function me(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'user' => $user
        ]);
    }
}

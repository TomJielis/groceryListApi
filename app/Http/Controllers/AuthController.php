<?php

namespace App\Http\Controllers;

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


class AuthController extends Controller
{
    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $user = (new StoreUserJob($request->all()))->handle();
        return response()->json($user);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
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
        $userId = Crypt::decryptString($token);
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'Url is verlopen'], 400);
        }

        if ($user->email_verified_at) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Gebruiker is al geverifieerd',
            ]));
        }

        $user->email_verified_at = Carbon::now();
        $user->save();

        return response()->json(['success' => true, 'message' => 'Gebruiker is geverifieerd'], 200);

    }

    public function resetPassword(Request $request)
    {
        $email = $request->get('email');

        if (!$email) {
            return response()->json(['message' => 'Email is verplicht'], 400);
        }

        $user = User::where('email', $email)->first();

        if(!$user)
        {
            return response()->json(['message' => 'Geen gebruiker gevonden met dit e-mailadres'], 404);
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


    public function setNewPassword(Request $request)
    {
        $email = $request->get('email');
        $token = $request->get('token');
        $newPassword = $request->get('password');

        $user = User::where('email', $email)->first();
        $temporaryPasswordCode = TemporaryPasswordCode::where('user_id', '=', $user->id)
            ->where('code', '=', $token)
            ->where('is_used', '=', false)
            ->first();

        if (!$temporaryPasswordCode) {
            return response()->json(['message' => 'Foutieve code'], 400);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $temporaryPasswordCode->is_used = true;
        $temporaryPasswordCode->save();

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

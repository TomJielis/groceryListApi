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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

    public function resetPassword(Request $request)
    {
        $email = $request->get('email');

        if (!$email) {
            return response()->json(['message' => 'Email is required'], 400);
        }

        $user = User::where('email', $email)->first();

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
            'address' => env('MAIL_FROM_ADDRESS'),
            'name' => env('MAIL_FROM_NAME'),
        ]);

        try {
            Mail::to($email)
                ->send($mail);

        } catch (\Exception $exception) {
            \Log::error($exception->getMessage());
            return response()->json(['message' => 'Failed to send email'], 500);
        }

        return response()->json(['message' => 'Password reset email sent'], 200);

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
            return response()->json(['message' => 'Invalid code'], 400);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $temporaryPasswordCode->is_used = true;
        $temporaryPasswordCode->save();

        return response()->json(['message' => 'Password updated'], 200);
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

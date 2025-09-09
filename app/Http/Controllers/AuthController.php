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
        $content = '<br />Dear ' . $user->name . ', <br/><br/> Click the button below to set a new password';
        $content .= '<br /><br/> <a target="_blank" rel="noopener noreferrer" href="' . $url . '" class="button button-blue" style="font-family: Avenir, Helvetica, sans-serif; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #ffffff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #d0393b; border-top: 10px solid #d0393b; border-right: 18px solid #d0393b; border-bottom: 10px solid #d0393b; border-left: 18px solid #d0393b;"> Click here to set a password</a>';
        $content .= '<br /><br/> Kind regards,<br>' . env('company_name') . '<br />';
        $title = "Set a new password";

        $mail = new ResetPassword($url, $user, $content, $title);

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

<?php

namespace App\Jobs\Users;

use App\Models\InvalidLoginAttempt;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class LoginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Request $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return array
     */
    public function handle()
    {
        // Validate the request data
        $request = $this->request;
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $isBlockedTill = $user && InvalidLoginAttempt::where('user_id', $user->id)
                    ->where('blocked_till', '>', now())
                    ->orderBy('blocked_till', 'desc')
                    ->first();

            if ($isBlockedTill) {
                return [
                    'error' => 'Too many failed login attempts. Please try again later.',
                    'success' => false,
                    'status' => 429,
                ];
            }


            if (!Auth::attempt($request->only('email', 'password'))) {
                if ($user) {
                    $invalidAttemptsCount = InvalidLoginAttempt::where('user_id', $user->id)
                        ->where('attempted_at', '>=', now()->subMinutes(15))
                        ->count();

                    InvalidLoginAttempt::create([
                        'user_id' => $user->id,
                        'username' => $user->email,
                        'ip_address' => $request->ip(),
                        'attempted_at' => now(),
                        'blocked_till' => $invalidAttemptsCount >= 2 ? now()->addMinutes(15) : null,
                    ]);
                }

                return [
                    'error' => 'Invalid login attempt',
                    'success' => false,
                    'status' => 401,
                ];
            }

            $user = \auth()->user();

            if ($user->email_verified_at === null) {
                return [
                    'error' => 'Email not verified. Please activate your account through the email sent to you.',
                    'success' => false,
                    'status' => 403,
                ];
            }

            if ($user->blocked) {
                return [
                    'error' => 'You account has been blocked.',
                    'success' => false,
                    'status' => 403,
                ];
            }

            $token = $user->createToken('nuxt-frontend', ['*'], now()->addDays(14))->plainTextToken;

            return [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ];
        } else {
            InvalidLoginAttempt::create([
                'username' => $request->email,
                'user_id' => null,
                'ip_address' => $request->ip(),
                'attempted_at' => now(),
                'blocked_till' => null,
            ]);

            return [
                'error' => 'Invalid login attempt',
                'success' => false,
                'status' => 401,
            ];
        }

    }
}

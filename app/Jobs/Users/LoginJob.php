<?php

namespace App\Jobs\Users;

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
        if (!Auth::attempt($request->only('email', 'password'))) {
            return [
                'error' => 'Invalid login attempt',
                'success' => false,
                'status' => 401,
            ];
        }

        $user = \auth()->user();

        if($user->email_verified_at === null) {
            return [
                'error' => 'Email not verified. Please activate your account through the email sent to you.',                'success' => false,
                'status' => 403,
            ];
        }

        $token = $user->createToken('nuxt-frontend')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}

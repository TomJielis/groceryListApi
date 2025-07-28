<?php

namespace App\Jobs\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
        $request = $this->request;

        if ( ! Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'message' => 'Invalid login details',
            ], 401);
        }

        /** @var User $user */
        $user = User::where('username', $request['username'])
                    ->firstOrFail();

        PersonalAccessToken::where('tokenable_id', '=', $user->id)
                           ->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        $userName = $user->firstname . (isset($user->prefix) ? ' ' . $user->prefix : '') . ' ' . $user->lastname;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'                => $user->id,
                'name'              => $userName,
                'email'             => $user->email,
                'username'          => $user->username,
                'email_verified_at' => $user->email_verified_at,
            ],
        ];
    }
}

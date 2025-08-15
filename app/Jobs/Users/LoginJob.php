<?php

namespace App\Jobs\Users;

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
        $request = $this->request;
        if ( ! Auth::attempt($request->only('email', 'password'))) {
            ray('Invalid login attempt', $request->only('email', 'password'));
            throw new \Exception('Invalid login attempt');
        }

        /** @var User $user */
        $user = User::where('email', $request['email'])
                    ->firstOrFail();

        // Delete existing tokens for the user
        PersonalAccessToken::where('tokenable_id', '=', $user->id)
                           ->delete();
        $user = \auth()->user();
        $token = $user->createToken('nuxt-frontend')->plainTextToken;

        ray($token);
        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ];
    }
}

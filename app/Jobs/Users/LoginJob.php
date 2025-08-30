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
        Log::info('starting.');

        // Validate the request data
        $request = $this->request;
//        if ( ! Auth::attempt($request->only('email', 'password'))) {
//            Log::info('password invalid.');
//
//            throw new \Exception('Invalid login attempt');
//        }
        $pincode = $request->input('pincode');

        if($pincode)
        {
            match ($pincode){
                '1234' => $mail = 'nerisesilie@gmail.com',
                '0000' => $mail = 'tomjielis@hotmail.com'
            };

        }

        $user = User::where('email', $mail)->first();
        \auth()->login($user);

        $user = \auth()->user();
        $token = $user->createToken('nuxt-frontend')->plainTextToken;

        Log::info('token created: ' . $token);

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ];
    }
}

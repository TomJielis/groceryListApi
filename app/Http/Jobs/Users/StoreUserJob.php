<?php

namespace App\Jobs\Users;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class StoreUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $userData;

    /**
     * Create a new job instance.
     *
     * @return $user
     */
    public function __construct($userData)
    {
        $this->userData = $userData;
    }

    /**
     * Execute the job.
     *
     * @return array
     */
    public function handle()
    {
        $userData = $this->userData;

        /** User $user */
        $user = new User();
        $user->username = $userData['username'];
        $user->password = Hash::make($userData['password']);
        $user->language = isset($userData['language']) ? $userData['language'] : 'nl';
        $user->firstname = $userData['firstname'];
        $user->prefix = isset($userData['prefix']) ? $userData['prefix'] : null;
        $user->lastname = $userData['lastname'];
        $user->email = $userData['email'];
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $userName = $user->firstname . (isset($user->prefix) ? ' ' . $user->prefix : '')  . ' ' . $user->lastname;
        $userData = [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'name'     => $userName,
                'email'    => $user->email,
                'username' => $user->username,
                'email_verified_at' => $user->email_verified_at,
            ],
        ];

        return $userData;
    }
}

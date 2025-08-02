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
        $user->name = $userData['name'];
        $user->password = Hash::make($userData['password']);
        $user->email = $userData['email'];

        $user->save();

//        $token = $user->createToken('auth_token')->plainTextToken;

        $userData = [
//            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'    => $user,
        ];

        return $userData;
    }
}

<?php

namespace App\Jobs\Users;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private User $user;
    private $userData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userData, User $user)
    {
        $this->userData = $userData;
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $userData = $this->userData;
        $user = $this->user;
        $user->firstname = $userData->firstname;
        $user->prefix = $userData->prefix;
        $user->lastname = $userData->lastname;
        $user->email = $userData->email;
        $user->save();

        return $user;
    }
}

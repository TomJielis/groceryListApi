<?php

namespace App\Jobs\Users;

use App\Mail\ResetPassword;
use App\Mail\Welcome;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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
        $user->language = $userData['language'] ?? 'nl';
        $user->save();

        $verifyCode = Crypt::encryptString($user->id);
        $url = config('app.url') . '/auth/' . $verifyCode;

        $emailTemplate = $user->language === 'en' ? 'emails.welcome-en' : 'emails.welcome';
        $mail = new Welcome($url, $user, $emailTemplate);

        Config::set('mail.from', [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ]);

        try {
            Mail::to($user->email)
                ->send($mail);

        } catch (\Exception $exception) {
            \Log::error($exception->getMessage());
            return response()->json(['message' => 'Email versturen is mislukt'], 500);
        }

        return [
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}

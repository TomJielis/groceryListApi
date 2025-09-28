<?php

namespace App\Console\Commands;

use App\Mail\Welcome;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class sendWelcomeEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-welcome-email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = config('app.url') . '/auth/login';
        $users = User::all();
        foreach ($users as $user)
        {
            $mail = new Welcome($url, $user);

            Config::set('mail.from', [
                'address' => env('MAIL_FROM_ADDRESS'),
                'name' => env('MAIL_FROM_NAME'),
            ]);

            try {
                Mail::to($user->email)
                    ->send($mail);

            } catch (\Exception $exception) {
                \Log::error($exception->getMessage());
                return response()->json(['message' => 'Email versturen mislukt'], 500);
            }
        }
    }
}

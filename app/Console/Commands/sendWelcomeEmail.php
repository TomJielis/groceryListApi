<?php

namespace App\Console\Commands;

use App\Mail\Welcome;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;


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
                'address' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ]);

            try {
                app(\App\Services\MailService::class)->send($mail, $user->email, $user->name);
            } catch (\Exception $exception) {
                \Log::error($exception->getMessage());
                return response()->json(['message' => 'Email versturen mislukt'], 500);
            }
        }
    }
}

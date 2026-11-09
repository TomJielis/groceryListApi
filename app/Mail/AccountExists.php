<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Someone registered with an address that already has an account. Registering answers the same as for a new address,
// so nobody can find out which addresses have an account; the owner of the address hears it here.
class AccountExists extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;
    public User $user;
    public string $language;

    public function __construct(User $user)
    {
        $this->url = rtrim((string) config('app.url'), '/') . '/auth/login';
        $this->user = $user;
        $this->language = $user->language ?? 'nl';
    }

    public function build()
    {
        \App::setLocale($this->language);
        return $this->subject(__('account-exists.subject'))
            ->view('emails.account.exists')
            ->text('emails.account.exists_text');
    }
}

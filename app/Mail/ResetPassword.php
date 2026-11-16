<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public User $user;
    public $content;
    public $title;
    public string $language;
    // The code in the link: when the mail is sent again later (SendMail), its hour starts again then
    public ?string $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($url, $user, ?string $code = null)
    {
        $this->url = $url;
        $this->code = $code;
        $this->user = $user;
        $this->language = $user->language;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    // Sent again later: not when a newer reset was requested meanwhile (that removed this code) or the link was used.
    // Otherwise the link works for an hour from now, as if the mail went out right away (renewLink).
    public function stillWanted(): bool
    {
        return $this->code === null || \App\Models\TemporaryPasswordCode::where('code', $this->code)
            ->where('is_used', false)
            ->exists();
    }

    // The link's hour starts when the mail is actually sent, not when it was asked for: a mail that arrives a day
    // later (the mail service was down) still has a working link. The code stays the same, so the mail's link too.
    public function renewLink(): void
    {
        if ($this->code !== null) {
            \App\Models\TemporaryPasswordCode::where('code', $this->code)
                ->where('is_used', false)
                ->update(['created_at' => now()]);
        }
    }

    public function build()
    {
        \App::setLocale($this->language);
        return $this->subject(__('password-reset.subject'))
            ->view('emails.password.user-password-reset')
            ->text('emails.password.user-password-reset_text');
    }
}

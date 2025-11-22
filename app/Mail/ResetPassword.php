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

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($url, $user, $markdown = 'emails.password.user-password-reset')
    {
        $this->url = $url;
        $this->user = $user;
        $this->markdown = $markdown;
        $this->language = $user->language;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        \App::setLocale($this->language);
        return $this->markdown($this->markdown);
    }
}

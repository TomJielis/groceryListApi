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

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($url, $user, $content, $title, $markdown = 'emails.password.user-password-reset')
    {
        $this->url = $url;
        $this->user = $user;
        $this->content = $content;
        $this->title = $title;
        $this->markdown = $markdown;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown($this->markdown);
    }
}

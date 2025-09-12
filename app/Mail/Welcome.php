<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Welcome extends Mailable
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
    public function __construct($url, $user, $markdown = 'emails.welcome')
    {
        $this->url = $url;
        $this->user = $user;
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

<?php

namespace App\Mail;

use App\Models\GroceryList;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GroceryListInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;
    public User $user;
    public GroceryList $list;
    public $markdown;

    public function __construct($user, $list)
    {
        $this->user = $user;
        $this->list = $list;
        $this->url = config('app.url') . '/auth/register/';
        $this->markdown = $user->language == 'en'
            ? 'emails.grocerylist.invite-en'
            : 'emails.grocerylist.invite';
    }

    public function build()
    {
        return $this->markdown($this->markdown);
    }
}

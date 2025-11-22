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
    public User|null $invitedUser;
    public string|null $email;
    public GroceryList $list;
    public $markdown;
    public string $language;

    public function __construct($user, $list, $invitedUser, $email)
    {
        $this->user = $user;
        $this->invitedUser = $invitedUser;
        $this->list = $list;
        $this->email = $email;
        $this->url = config('app.url') . '/auth/register/' . (isset($email) ? '?email=' . $email : '');

        $this->language = $invitedUser ? $invitedUser->language : $user->language;
        $this->markdown = 'emails.grocerylist.invite';
    }

    public function build()
    {
        \App::setLocale($this->language);
        return $this->markdown($this->markdown);
    }
}

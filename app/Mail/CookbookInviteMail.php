<?php

namespace App\Mail;

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Like GroceryListInviteMail, for a cookbook
class CookbookInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;
    public User $user;
    public User|null $invitedUser;
    public string|null $email;
    public Cookbook $cookbook;
    public $markdown;
    public string $language;

    public function __construct($user, $cookbook, $invitedUser, $email)
    {
        $this->user = $user;
        $this->invitedUser = $invitedUser;
        $this->cookbook = $cookbook;
        $this->email = $email;
        $this->url = config('app.url') . '/auth/register/' . (isset($email) ? '?email=' . urlencode($email) : '');

        $this->language = ($invitedUser ? $invitedUser->language : $user->language) ?: 'nl';
        $this->markdown = 'emails.cookbook.invite';
    }

    public function build()
    {
        \App::setLocale($this->language);
        return $this->markdown($this->markdown);
    }
}

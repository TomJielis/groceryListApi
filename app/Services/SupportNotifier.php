<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Mail\SupportMail;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Tells the other side of a support ticket that something new was written: the admins about questions and replies, the
 * person who asked about answers. By push when the app can be reached, otherwise by mail, so nobody gets both.
 * A mail that can't be sent is reported but doesn't fail the request: the message is saved and shows up in the admin.
 */
class SupportNotifier
{
    public function __construct(private MailService $mail)
    {
    }

    // Nothing to the person who asked: they see their question in the app or on the website right away
    public function questionAsked(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->notifyAdmins($ticket, $message, 'support_new');
    }

    public function userReplied(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->notifyAdmins($ticket, $message, 'support_message');
    }

    // A push when the user has the app with notifications on; a mail only when a push can't reach them
    public function adminReplied(SupportTicket $ticket, SupportMessage $message): void
    {
        $user = $ticket->user;
        if ($user && $this->reachableByPush($user, 'support_reply')) {
            SendPushNotification::dispatch($user->id, 'support_reply', ['subject' => $ticket->subject], ['url' => '/support/' . $ticket->id]);
            return;
        }

        $this->sendMail(new SupportMail($ticket, $message, 'reply', $ticket->language), $ticket->email, $ticket->name);
    }

    // A push to every admin with the app; the notification address gets a mail only when no admin can be pushed
    private function notifyAdmins(SupportTicket $ticket, SupportMessage $message, string $pushType): void
    {
        $pushed = false;
        foreach (User::where('is_admin', true)->get() as $admin) {
            if ($this->reachableByPush($admin, $pushType)) {
                SendPushNotification::dispatch(
                    $admin->id,
                    $pushType,
                    ['name' => $ticket->name, 'subject' => $ticket->subject],
                    ['url' => '/admin/support/' . $ticket->id],
                );
                $pushed = true;
            }
        }

        $notifyEmail = config('support.notify_email');
        if (!$pushed && $notifyEmail) {
            $language = User::where('email', $notifyEmail)->value('language') ?: 'nl';
            $this->sendMail(new SupportMail($ticket, $message, 'admin', $language), $notifyEmail);
        }
    }

    // A device that's logged in and has notifications on (the app removes its token when they're switched off)
    private function reachableByPush(User $user, string $type): bool
    {
        return $user->wantsPush($type)
            && $user->pushTokens()
                ->whereDoesntHave('accessToken', fn ($query) => $query->where('expires_at', '<', now()))
                ->exists();
    }

    private function sendMail(SupportMail $mail, string $email, ?string $name = null): void
    {
        try {
            $this->mail->send($mail, $email, $name);
        } catch (\Throwable $e) {
            // Logged as failed in sent_emails by MailService
            report($e);
        }
    }
}

<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Mail\SupportMail;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Tells the other side of a support ticket that something new was written: the admins by email (support.notify_email)
 * and push, the person who asked (only about an answer) by email and, when they have an account, push.
 * A mail that can't be sent is reported but doesn't fail the request: the message is saved and shows up in the admin.
 */
class SupportNotifier
{
    public function __construct(private MailService $mail)
    {
    }

    // No mail to the person who asked: they see the link to the conversation right away, and a confirmation mail would
    // let anyone send a mail with their own text to any address through the form
    public function questionAsked(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->notifyAdmins($ticket, $message, 'support_new');
    }

    public function userReplied(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->notifyAdmins($ticket, $message, 'support_message');
    }

    public function adminReplied(SupportTicket $ticket, SupportMessage $message): void
    {
        $this->sendMail(new SupportMail($ticket, $message, 'reply', $ticket->language), $ticket->email, $ticket->name);

        if ($ticket->user_id) {
            SendPushNotification::dispatch($ticket->user_id, 'support_reply', ['subject' => $ticket->subject], ['url' => '/support/' . $ticket->id]);
        }
    }

    private function notifyAdmins(SupportTicket $ticket, SupportMessage $message, string $pushType): void
    {
        $notifyEmail = config('support.notify_email');
        if ($notifyEmail) {
            $language = User::where('email', $notifyEmail)->value('language') ?: 'nl';
            $this->sendMail(new SupportMail($ticket, $message, 'admin', $language), $notifyEmail);
        }

        User::where('is_admin', true)->pluck('id')->each(fn (int $adminId) => SendPushNotification::dispatch(
            $adminId,
            $pushType,
            ['name' => $ticket->name, 'subject' => $ticket->subject],
            ['url' => '/admin/support/' . $ticket->id],
        ));
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

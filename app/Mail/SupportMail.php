<?php

namespace App\Mail;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mails about a support ticket. Nobody answers by replying to them: the person who asked replies through the
 * link to the conversation, the admin in the admin of the app or the website.
 *
 * - reply: to the person who asked, with the admin's answer (the only mail they get, so the form can't be used to
 *          send someone a mail with text of your own choosing)
 * - admin: to the admin (support.notify_email), with a new question or reply
 */
class SupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $supportMessage,
        public string $kind,
        string $language,
    ) {
        // Renders in this language without changing the language of the rest of the request
        $this->locale($language);
    }

    public function build()
    {
        $isNew = $this->ticket->messages()->count() === 1;
        $subject = match ($this->kind) {
            'reply' => __('support.reply_subject', ['subject' => $this->ticket->subject]),
            default => __($isNew ? 'support.admin_new_title' : 'support.admin_message_title') . ': ' . $this->ticket->subject,
        };

        return $this->subject($subject)
            ->view('emails.support.' . $this->kind, [
                'ticket' => $this->ticket,
                'body' => $this->supportMessage->body,
                'isNew' => $isNew,
            ])
            ->text('emails.support.' . $this->kind . '_text', [
                'ticket' => $this->ticket,
                'body' => $this->supportMessage->body,
                'isNew' => $isNew,
            ]);
    }
}

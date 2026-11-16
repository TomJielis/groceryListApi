<?php

namespace App\Services;

use App\Jobs\SendMail;
use App\Models\SentEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailService
{
    // Emails whose body holds a working link to someone's account or support conversation, or the conversation itself;
    // the log keeps only that they were sent
    private const PRIVATE_BODY_TYPES = ['reset_password', 'support_mail'];

    // How long a mail that failed is tried again: 3 days, enough for a weekend outage or a few days over the mail
    // service's daily limit. A reset mail too: its link's hour starts when it's actually sent (ResetPassword).
    private const RETRY_HOURS = 72;

    /**
     * Puts the mail in the queue (the jobs table), where the worker sends it within seconds and tries again when
     * that fails (SendMail). Without a queue worker (QUEUE_CONNECTION=sync, as locally and in tests) it's sent at
     * once, and a failure reaches the caller. Should the queue itself not work (the database), it's sent right away.
     */
    public function send(Mailable $mailable, string $email, ?string $name = null): void
    {
        $triggeredBy = Auth::id();
        $job = new SendMail($mailable, $email, $name, $triggeredBy, now()->addHours(self::RETRY_HOURS));

        if (config('queue.default') === 'sync') {
            dispatch($job);
            return;
        }

        try {
            dispatch($job);
        } catch (\Throwable $e) {
            report($e);
            $this->deliver($mailable, $email, $name, $triggeredBy);
        }
    }

    /**
     * Sends the mail and logs it as sent; throws when sending fails (nothing logged then).
     */
    public function deliver(Mailable $mailable, string $email, ?string $name, ?int $triggeredBy): void
    {
        $type = Str::snake(class_basename($mailable));
        $sentMessage = Mail::to($email)->send($mailable);

        $subject = null;
        $bodyHtml = null;
        $bodyText = null;

        if ($sentMessage) {
            // getOriginalMessage() is forwarded via __call to Symfony\Mailer\SentMessage,
            // which returns the Symfony\Mime\Email that was rendered.
            $symfonyEmail = $sentMessage->getOriginalMessage();
            $subject = $symfonyEmail->getSubject();
            if (!in_array($type, self::PRIVATE_BODY_TYPES, true)) {
                $bodyHtml = $symfonyEmail->getHtmlBody();
                $bodyText = $symfonyEmail->getTextBody();
            }
        }

        SentEmail::create([
            'type' => $type,
            'recipient_email' => $email,
            'recipient_name' => $name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'status' => 'sent',
            'triggered_by_user_id' => $triggeredBy,
            'sent_at' => now(),
        ]);
    }
}

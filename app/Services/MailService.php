<?php

namespace App\Services;

use App\Models\SentEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailService
{
    public function send(Mailable $mailable, string $email, ?string $name = null): void
    {
        $type = Str::snake(class_basename($mailable));

        try {
            $sentMessage = Mail::to($email)->send($mailable);

            $subject = null;
            $bodyHtml = null;
            $bodyText = null;

            if ($sentMessage) {
                // getOriginalMessage() is forwarded via __call to Symfony\Mailer\SentMessage,
                // which returns the Symfony\Mime\Email that was rendered.
                $symfonyEmail = $sentMessage->getOriginalMessage();
                $subject = $symfonyEmail->getSubject();
                $bodyHtml = $symfonyEmail->getHtmlBody();
                $bodyText = $symfonyEmail->getTextBody();
            }

            SentEmail::create([
                'type' => $type,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'status' => 'sent',
                'triggered_by_user_id' => Auth::id(),
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            SentEmail::create([
                'type' => $type,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => null,
                'body_html' => null,
                'body_text' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'triggered_by_user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }
}

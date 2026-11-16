<?php

namespace App\Jobs;

use App\Models\SentEmail;
use App\Models\User;
use App\Services\MailService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every mail of the application, sent by the queue worker (MailService::send puts it in the jobs table first, so
 * nothing is lost when the mail service is down, blocks us or the daily limit is reached). When sending fails it's
 * tried again after 1, 5, 15 and 30 minutes, then every hour until `$until`. When it still fails then, it's logged
 * and the admins get a push (a mail would fail too).
 */
class SendMail implements ShouldQueue
{
    use Queueable;

    // The user (or ticket) the mail is about was deleted meanwhile: nothing to send any more
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Mailable $mailable,
        public string $email,
        public ?string $name,
        public ?int $triggeredBy,
        public CarbonInterface $until,
    ) {
    }

    public function handle(MailService $mail): void
    {
        // Not needed any more, e.g. a newer reset link was requested or the account was activated meanwhile
        if ($this->attempts() > 1) {
            if (method_exists($this->mailable, 'stillWanted') && !$this->mailable->stillWanted()) {
                return;
            }
            // A reset link works for an hour from now
            if (method_exists($this->mailable, 'renewLink')) {
                $this->mailable->renewLink();
            }
        }

        try {
            $mail->deliver($this->mailable, $this->email, $this->name, $this->triggeredBy);
        } catch (Throwable $e) {
            // Logged once, at the first failure; the end result (sent or given up) is logged too
            if ($this->attempts() === 1) {
                SentEmail::create([
                    'type' => $this->type(),
                    'recipient_email' => $this->email,
                    'recipient_name' => $this->name,
                    'status' => 'failed',
                    'error_message' => $e->getMessage() . ' (will retry)',
                    'triggered_by_user_id' => $this->triggeredBy,
                ]);
            }
            // The queue tries again after the next backoff
            throw $e;
        }
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    // Instead of a number of tries (the worker's --tries doesn't apply when this is set)
    public function retryUntil(): CarbonInterface
    {
        return $this->until;
    }

    // Gave up: logged, so it shows in the admin's mail log, and the admins are told. The job stays in failed_jobs:
    // `php artisan queue:retry all` sends it once more.
    public function failed(?Throwable $e): void
    {
        SentEmail::create([
            'type' => $this->type(),
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'status' => 'failed',
            'error_message' => 'Gave up retrying: ' . ($e?->getMessage() ?? 'unknown error'),
            'triggered_by_user_id' => $this->triggeredBy,
        ]);

        foreach (User::where('is_admin', true)->pluck('id') as $adminId) {
            SendPushNotification::dispatch($adminId, 'mail_failed', ['type' => $this->type(), 'email' => $this->email], ['url' => '/admin/emails']);
        }
    }

    private function type(): string
    {
        return Str::snake(class_basename($this->mailable));
    }
}

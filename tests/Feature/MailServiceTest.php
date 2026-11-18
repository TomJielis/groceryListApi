<?php

namespace Tests\Feature;

use App\Jobs\SendMail;
use App\Jobs\SendPushNotification;
use App\Mail\ResetPassword;
use App\Mail\Welcome;
use App\Models\SentEmail;
use App\Models\TemporaryPasswordCode;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mail_goes_into_the_queue_first(): void
    {
        Queue::fake();
        $user = User::factory()->create(['language' => 'nl', 'email_verified_at' => null]);
        $this->actingAs($user);

        app(MailService::class)->send(new Welcome('http://example.com', $user), 'to@example.com', 'Tom');

        Queue::assertPushed(SendMail::class, fn (SendMail $job) => $job->email === 'to@example.com'
            && $job->name === 'Tom'
            && $job->triggeredBy === $user->id
            && $job->retryUntil()->greaterThan(now()->addHours(71)));
    }

    public function test_a_failed_try_is_logged_once_and_tried_again(): void
    {
        // As on the server, with a queue worker
        config(['queue.default' => 'database']);
        Mail::shouldReceive('to')->twice()->andThrow(new \Exception('SMTP failure'));
        $job = $this->job(new Welcome('http://example.com', User::factory()->create(['language' => 'nl', 'email_verified_at' => null])));

        foreach ([1, 2] as $attempt) {
            $job->job->attempts = $attempt;
            try {
                $job->handle(app(MailService::class));
                $this->fail('Expected exception was not thrown');
            } catch (\Exception $e) {
                // Thrown, so the queue tries again
                $this->assertSame('SMTP failure', $e->getMessage());
            }
        }

        $this->assertSame(1, SentEmail::where('status', 'failed')->count());
        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP failure (will retry)',
        ]);
    }

    public function test_without_a_queue_worker_a_failure_reaches_the_caller(): void
    {
        // QUEUE_CONNECTION=sync (locally, tests): sent at once
        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP failure'));

        try {
            app(MailService::class)->send(new Welcome('http://example.com', User::factory()->create(['language' => 'nl', 'email_verified_at' => null])), 'to@example.com');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertSame('SMTP failure', $e->getMessage());
        }
    }

    public function test_the_retry_sends_and_logs_the_mail(): void
    {
        Mail::fake();
        $job = new SendMail(new Welcome('http://example.com', User::factory()->create(['language' => 'nl', 'email_verified_at' => null])), 'to@example.com', 'Tom', null, now()->addDay());
        $job->withFakeQueueInteractions();

        $job->handle(app(MailService::class));

        Mail::assertSent(Welcome::class);
        $this->assertDatabaseHas('sent_emails', ['recipient_email' => 'to@example.com', 'status' => 'sent']);
    }

    public function test_giving_up_is_logged_and_the_admins_are_told(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $job = new SendMail(new Welcome('http://example.com', User::factory()->create(['language' => 'nl', 'email_verified_at' => null])), 'to@example.com', null, null, now()->addDay());

        $job->failed(new \Exception('SMTP failure'));

        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'status' => 'failed',
            'error_message' => 'Gave up retrying: SMTP failure',
        ]);
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $push) => $push->userId === $admin->id
            && $push->type === 'mail_failed'
            && $push->replace === ['type' => 'welcome', 'email' => 'to@example.com']);
    }

    public function test_a_late_reset_mail_gets_a_link_that_works_for_an_hour_from_then(): void
    {
        Mail::fake();
        $user = User::factory()->create(['language' => 'nl']);
        // Asked for yesterday; the mail service was down until now
        $code = TemporaryPasswordCode::forceCreate(['user_id' => $user->id, 'code' => 'late', 'is_used' => false]);
        $code->forceFill(['created_at' => now()->subDay()])->save();

        $job = $this->job(new ResetPassword('http://example.com/auth/password/late', $user, 'late'), $user->email);
        $job->job->attempts = 20;
        $job->handle(app(MailService::class));

        Mail::assertSent(ResetPassword::class);
        $this->postJson('/api/check-reset-link', ['token' => 'late'])->assertJsonPath('valid', true);
    }

    public function test_a_reset_mail_is_tried_for_three_days_like_the_others(): void
    {
        Queue::fake();
        $user = User::factory()->create(['language' => 'nl']);

        app(MailService::class)->send(new ResetPassword('http://example.com/auth/password/abc', $user, 'abc'), $user->email);

        Queue::assertPushed(SendMail::class, fn (SendMail $job) => $job->retryUntil()->greaterThan(now()->addHours(71)));
    }

    public function test_a_reset_mail_whose_link_no_longer_works_is_not_sent_again(): void
    {
        Mail::fake();
        $user = User::factory()->create(['language' => 'nl', 'email_verified_at' => null]);
        // A newer request replaced the code (the old one was deleted), one was used already; a code still open is sent
        TemporaryPasswordCode::forceCreate(['user_id' => $user->id, 'code' => 'new', 'is_used' => false]);
        TemporaryPasswordCode::forceCreate(['user_id' => $user->id, 'code' => 'used', 'is_used' => true]);

        foreach (['old', 'used', 'new'] as $code) {
            // A later try (the first one is sent as it is)
            $job = $this->job(new ResetPassword("http://example.com/auth/password/{$code}", $user, $code), $user->email);
            $job->job->attempts = 2;
            $job->handle(app(MailService::class));
        }

        Mail::assertSent(ResetPassword::class, 1);
        Mail::assertSent(ResetPassword::class, fn (ResetPassword $mail) => $mail->code === 'new');
    }

    public function test_an_activation_mail_is_not_sent_again_once_the_account_is_active(): void
    {
        Mail::fake();
        $user = User::factory()->create(['language' => 'nl', 'email_verified_at' => now()]);

        $job = $this->job(new Welcome('http://example.com', $user), $user->email);
        $job->job->attempts = 2;
        $job->handle(app(MailService::class));

        Mail::assertNothingSent();
    }

    public function test_type_is_snake_case_class_name(): void
    {
        Mail::fake();

        app(MailService::class)->send(new Welcome('http://example.com', User::factory()->create(['language' => 'en', 'email_verified_at' => null])), 'to@example.com');

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'welcome',
        ]);
    }

    public function test_without_a_queue_worker_a_failure_is_logged_once_and_nobody_is_pushed(): void
    {
        // Locally and in tests (sync): the caller gets the error, so no "will retry", no "gave up", no push
        Queue::fake()->except(SendMail::class);
        User::factory()->create(['is_admin' => true]);
        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP failure'));

        try {
            app(MailService::class)->send(new Welcome('http://example.com', User::factory()->create(['language' => 'nl', 'email_verified_at' => null])), 'to@example.com');
        } catch (\Exception) {
        }

        $this->assertSame(['SMTP failure'], SentEmail::pluck('error_message')->all());
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_a_reset_mail_gets_its_hour_also_on_the_first_try(): void
    {
        // The worker was down for a while: the first try comes after the link would have expired
        Mail::fake();
        $user = User::factory()->create(['language' => 'nl']);
        TemporaryPasswordCode::forceCreate(['user_id' => $user->id, 'code' => 'late', 'is_used' => false])
            ->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->job(new ResetPassword('http://example.com/auth/password/late', $user, 'late'), $user->email)->handle(app(MailService::class));

        Mail::assertSent(ResetPassword::class);
        $this->postJson('/api/check-reset-link', ['token' => 'late'])->assertJsonPath('valid', true);
    }

    public function test_a_reset_mail_whose_link_was_used_is_not_sent_even_the_first_time(): void
    {
        Mail::fake();
        $user = User::factory()->create(['language' => 'nl']);
        TemporaryPasswordCode::forceCreate(['user_id' => $user->id, 'code' => 'used', 'is_used' => true]);

        $this->job(new ResetPassword('http://example.com/auth/password/used', $user, 'used'), $user->email)->handle(app(MailService::class));

        Mail::assertNothingSent();
    }

    private function job(\Illuminate\Mail\Mailable $mailable, string $email = 'to@example.com'): SendMail
    {
        return (new SendMail($mailable, $email, null, null, now()->addDay()))->withFakeQueueInteractions();
    }
}

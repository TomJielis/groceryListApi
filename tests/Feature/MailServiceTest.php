<?php

namespace Tests\Feature;

use App\Models\SentEmail;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_writes_sent_record_on_success(): void
    {
        Mail::fake();

        $mailable = new class extends Mailable {
            public function build(): static {
                return $this->subject('Hello')->text('emails.welcome');
            }
        };

        app(MailService::class)->send($mailable, 'to@example.com', 'Test User');

        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'recipient_name' => 'Test User',
            'status' => 'sent',
        ]);
    }

    public function test_writes_failed_record_and_rethrows_on_exception(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP failure'));

        $mailable = new class extends Mailable {
            public function build(): static {
                return $this->subject('Hello')->text('emails.welcome');
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SMTP failure');

        app(MailService::class)->send($mailable, 'to@example.com');

        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP failure',
        ]);
    }

    public function test_type_is_snake_case_class_name(): void
    {
        Mail::fake();

        app(MailService::class)->send(new \App\Mail\Welcome('http://example.com', \App\Models\User::factory()->make(['language' => 'en'])), 'to@example.com');

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'welcome',
        ]);
    }
}

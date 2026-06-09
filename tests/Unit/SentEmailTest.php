<?php

namespace Tests\Unit;

use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_fillable_attributes(): void
    {
        $email = SentEmail::create([
            'type' => 'welcome',
            'recipient_email' => 'test@example.com',
            'recipient_name' => 'Test User',
            'subject' => 'Welcome!',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertDatabaseHas('sent_emails', ['recipient_email' => 'test@example.com']);
        $this->assertEquals('welcome', $email->type);
    }

    public function test_triggered_by_relation_returns_user(): void
    {
        $user = User::factory()->create();
        $email = SentEmail::create([
            'type' => 'welcome',
            'recipient_email' => 'test@example.com',
            'status' => 'sent',
            'triggered_by_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $email->triggeredBy);
        $this->assertEquals($user->id, $email->triggeredBy->id);
    }

    public function test_triggered_by_is_null_when_not_set(): void
    {
        $email = SentEmail::create([
            'type' => 'reset_password',
            'recipient_email' => 'test@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertNull($email->triggeredBy);
    }
}

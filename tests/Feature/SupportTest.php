<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Mail\SupportMail;
use App\Models\PushToken;
use App\Models\SentEmail;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Support questions: asking (signed in only), the conversation, answering from the admin, and who gets which mail
 * and push
 */
class SupportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config(['support.notify_email' => 'support-admin@example.com']);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['name' => 'Sanne', 'email' => 'sanne@example.com', 'language' => 'en']);
    }

    private function ask(array $data = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user, 'sanctum')->postJson('/api/support/tickets', $data + [
            'subject' => 'Can not share', 'message' => 'Help me please',
        ]);
    }

    private function answer(SupportTicket $ticket, string $message = 'Try again')
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => $message]);
    }

    public function test_a_signed_in_user_asks_with_their_own_name_and_address(): void
    {
        $this->ask()->assertCreated()->assertJsonPath('data.messages.0.body', 'Help me please');

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $this->user->id, 'name' => 'Sanne', 'email' => 'sanne@example.com', 'language' => 'en', 'status' => 'open',
        ]);
    }

    public function test_asking_needs_an_account(): void
    {
        $this->postJson('/api/support/tickets', ['subject' => 'Hi', 'message' => 'Hello'])->assertUnauthorized();
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_a_subject_and_message_are_required(): void
    {
        $this->ask(['subject' => '', 'message' => ''])->assertUnprocessable()->assertJsonValidationErrors(['subject', 'message']);
    }

    public function test_without_an_admin_on_the_app_the_notification_address_gets_a_mail(): void
    {
        $this->ask()->assertCreated();

        Mail::assertNotSent(SupportMail::class, fn (SupportMail $mail) => $mail->hasTo('sanne@example.com'));
        Mail::assertSent(SupportMail::class, fn (SupportMail $mail) => $mail->kind === 'admin' && $mail->hasTo('support-admin@example.com'));
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_users_see_only_their_own_tickets(): void
    {
        $this->ask();
        $theirs = SupportTicket::create(['user_id' => $this->admin->id, 'name' => 'X', 'email' => 'x@example.com', 'subject' => 'Other']);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/support/tickets')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->user, 'sanctum')->getJson("/api/support/tickets/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->postJson("/api/support/tickets/{$theirs->id}/messages", ['message' => 'Hi'])->assertNotFound();
    }

    public function test_the_conversation_does_not_name_the_admin(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        $this->answer($ticket);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/support/tickets/{$ticket->id}")->assertOk();
        $this->assertArrayNotHasKey('admin', $response->json('data.messages.1'));
        $this->assertTrue($response->json('data.messages.1.from_admin'));
    }

    public function test_a_users_reply_notifies_the_admins(): void
    {
        PushToken::create(['user_id' => $this->admin->id, 'token' => 'ExponentPushToken[admin]']);
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        Queue::fake();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", ['message' => 'More info', 'via' => 'app'])
            ->assertCreated();

        $this->assertDatabaseHas('support_messages', ['body' => 'More info', 'via' => 'app', 'from_admin' => false]);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->type === 'support_message');
    }

    public function test_the_admin_answers_and_a_user_without_the_app_gets_a_mail(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();

        $this->answer($ticket, 'Try resetting your password')
            ->assertCreated()
            ->assertJsonPath('data.status', 'answered')
            ->assertJsonPath('data.messages.1.admin.id', $this->admin->id);

        Mail::assertSent(SupportMail::class, fn (SupportMail $mail) => $mail->kind === 'reply' && $mail->hasTo('sanne@example.com'));
        Queue::assertNotPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->user->id);
    }

    public function test_a_user_with_the_app_gets_a_push_and_no_mail(): void
    {
        PushToken::create(['user_id' => $this->user->id, 'token' => 'ExponentPushToken[user]']);
        $this->ask();
        $ticket = SupportTicket::firstOrFail();

        $this->answer($ticket)->assertCreated();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->user->id && $job->type === 'support_reply');
        Mail::assertNotSent(SupportMail::class, fn (SupportMail $mail) => $mail->hasTo('sanne@example.com'));
    }

    public function test_an_admin_with_the_app_gets_a_push_and_no_mail(): void
    {
        PushToken::create(['user_id' => $this->admin->id, 'token' => 'ExponentPushToken[admin]']);

        $this->ask()->assertCreated();

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->admin->id && $job->type === 'support_new');
        Mail::assertNothingSent();
    }

    public function test_a_device_with_notifications_switched_off_is_no_longer_pushed(): void
    {
        $login = $this->user->createToken('phone');
        PushToken::create(['user_id' => $this->user->id, 'token' => 'ExponentPushToken[user]', 'access_token_id' => $login->accessToken->id]);

        $this->withToken($login->plainTextToken)->deleteJson('/api/push-tokens/current')->assertOk();

        $this->assertSame(0, PushToken::count());
    }

    public function test_the_answer_mail_points_to_the_app_in_the_askers_language(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        $this->answer($ticket)->assertCreated();

        $locale = app()->getLocale();
        Mail::assertSent(SupportMail::class, function (SupportMail $mail) use ($ticket) {
            $html = $mail->render();
            return $mail->kind === 'reply'
                && str_contains($html, 'Profile → Help &amp; contact')
                && str_contains($html, 'Replies to this email are not read')
                && $mail->subject === 'Reply to your question: Can not share';
        });
        // Rendering the mail in the asker's language doesn't change the language of everything after it
        $this->assertSame($locale, app()->getLocale());
    }

    public function test_the_mail_log_keeps_no_conversation(): void
    {
        Mail::swap(new MailManager(app()));
        config(['mail.default' => 'array']);
        $this->ask();

        $logged = SentEmail::where('type', 'support_mail')->firstOrFail();
        $this->assertNull($logged->body_html);
        $this->assertNull($logged->body_text);
    }

    public function test_an_answer_stays_unread_until_the_user_opens_the_conversation(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        $this->actingAs($this->user, 'sanctum')->getJson('/api/support/unread-count')->assertOk()->assertJsonPath('count', 0);

        $this->answer($ticket);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/support/unread-count')->assertJsonPath('count', 1);
        $this->actingAs($this->user, 'sanctum')->getJson('/api/support/tickets')->assertJsonPath('data.0.unread_by_user', true);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/support/tickets/{$ticket->id}")->assertOk();

        $this->actingAs($this->user, 'sanctum')->getJson('/api/support/unread-count')->assertJsonPath('count', 0);
    }

    public function test_a_reply_after_closing_opens_the_ticket_again(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/support/tickets/{$ticket->id}/messages", ['message' => 'Still broken'])->assertCreated();
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_reopening_restores_the_status_from_the_last_message(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        $this->answer($ticket, 'Done');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/close");

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'answered');
    }

    public function test_the_admin_list_puts_waiting_tickets_first_and_counts_per_status(): void
    {
        $base = ['user_id' => $this->user->id, 'name' => 'A', 'email' => 'a@example.com'];
        $answered = SupportTicket::create($base + ['subject' => 'A', 'status' => 'answered', 'last_message_at' => now()]);
        $open = SupportTicket::create($base + ['subject' => 'B', 'status' => 'open', 'last_message_at' => now()->subDay()]);
        SupportTicket::create($base + ['subject' => 'C', 'status' => 'closed']);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/support/tickets')->assertOk();

        $this->assertSame([$open->id, $answered->id], array_slice(array_column($response->json('data'), 'id'), 0, 2));
        $this->assertSame(['open' => 1, 'answered' => 1, 'closed' => 1], $response->json('meta.counts'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/support/tickets?status=closed')->assertJsonCount(1, 'data');
        // A % in the search is a literal %, not "anything"
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/support/tickets?search=%25')->assertJsonCount(0, 'data');
    }

    public function test_only_admins_reach_the_admin_endpoints(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/support/tickets')->assertForbidden();
    }

    public function test_asking_is_rate_limited_per_user(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->ask()->assertCreated();
        }

        $this->ask()->assertStatus(429);
    }

    public function test_deleting_an_account_removes_its_support_questions(): void
    {
        $this->ask();

        $this->actingAs($this->user, 'sanctum')->postJson('/api/deactivate')->assertOk();

        $this->assertSame(0, SupportTicket::count());
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_old_conversations_are_pruned(): void
    {
        $this->ask();
        SupportTicket::query()->update(['last_message_at' => now()->subMonths(13)]);
        $recent = SupportTicket::create(['user_id' => $this->user->id, 'name' => 'R', 'email' => 'r@example.com', 'subject' => 'Recent']);

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertSame([$recent->id], SupportTicket::pluck('id')->all());
    }
}

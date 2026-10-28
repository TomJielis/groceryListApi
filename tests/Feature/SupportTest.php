<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Mail\SupportMail;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Support questions: asking with and without an account, the conversation through the app and the email link,
 * answering from the admin, and who gets which mail and push
 */
class SupportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config(['support.notify_email' => 'support-admin@example.com']);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function ask(array $data = [], ?User $user = null)
    {
        $request = $user ? $this->actingAs($user, 'sanctum') : $this;

        return $request->postJson('/api/support/tickets', $data + [
            'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Can not log in', 'message' => 'Help me please',
        ]);
    }

    public function test_someone_without_an_account_can_ask_a_question(): void
    {
        $response = $this->ask()->assertCreated();

        $ticket = SupportTicket::firstOrFail();
        $this->assertNull($ticket->user_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame($ticket->access_token, $response->json('data.access_token'));
        $this->assertSame('Help me please', $response->json('data.messages.0.body'));
    }

    public function test_a_guest_must_give_a_name_and_email(): void
    {
        $this->postJson('/api/support/tickets', ['subject' => 'Hi', 'message' => 'Hello'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_a_signed_in_user_asks_with_their_own_name_and_address(): void
    {
        $user = User::factory()->create(['name' => 'Sanne', 'email' => 'sanne@example.com', 'language' => 'en']);

        $this->ask(['name' => null, 'email' => null], $user)->assertCreated();

        $this->assertDatabaseHas('support_tickets', ['user_id' => $user->id, 'name' => 'Sanne', 'email' => 'sanne@example.com', 'language' => 'en']);
    }

    public function test_asking_mails_and_pushes_the_admins_but_not_the_asker(): void
    {
        $this->ask()->assertCreated();
        $ticket = SupportTicket::firstOrFail();

        // A confirmation would let anyone mail their own text to any address through the form
        Mail::assertNotSent(SupportMail::class, fn (SupportMail $mail) => $mail->hasTo('guest@example.com'));
        Mail::assertSent(SupportMail::class, fn (SupportMail $mail) => $mail->kind === 'admin' && $mail->hasTo('support-admin@example.com'));
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->admin->id
            && $job->type === 'support_new'
            && $job->data['url'] === '/admin/support/' . $ticket->id);
    }

    public function test_the_answer_mail_links_to_the_conversation_in_the_askers_language(): void
    {
        $this->ask(['language' => 'en']);
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Try again'])->assertCreated();

        $locale = app()->getLocale();
        Mail::assertSent(SupportMail::class, function (SupportMail $mail) use ($ticket) {
            $html = $mail->render();
            return $mail->kind === 'reply'
                && str_contains($html, $ticket->publicUrl())
                && str_contains($html, 'Replies to this email are not read')
                && $mail->subject === 'Reply to your question: Can not log in';
        });
        // Rendering the mail in the asker's language doesn't change the language of everything after it
        $this->assertSame($locale, app()->getLocale());
    }

    public function test_a_bot_filling_the_hidden_field_is_refused(): void
    {
        $this->ask(['company' => 'Spam BV'])->assertUnprocessable();

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_the_mail_log_keeps_no_conversation_or_link(): void
    {
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        config(['mail.default' => 'array']);
        $this->ask();

        $logged = \App\Models\SentEmail::where('type', 'support_mail')->firstOrFail();
        $this->assertNull($logged->body_html);
        $this->assertNull($logged->body_text);
    }

    public function test_the_email_link_shows_the_conversation_and_takes_a_reply(): void
    {
        $token = $this->ask()->json('data.access_token');

        $this->getJson("/api/support/t/{$token}")->assertOk()->assertJsonPath('data.messages.0.body', 'Help me please');
        $this->postJson("/api/support/t/{$token}/messages", ['message' => 'Any news?'])->assertCreated()
            ->assertJsonCount(2, 'data.messages');

        $this->getJson('/api/support/t/not-a-real-token')->assertNotFound();
    }

    public function test_the_conversation_does_not_include_the_link_token_or_admin_details(): void
    {
        $token = $this->ask()->json('data.access_token');

        $response = $this->getJson("/api/support/t/{$token}")->assertOk();
        $this->assertArrayNotHasKey('access_token', $response->json('data'));
        $this->assertArrayNotHasKey('admin', $response->json('data.messages.0'));
    }

    public function test_users_see_only_their_own_tickets(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->ask([], $user);
        $theirs = SupportTicket::create(['user_id' => $other->id, 'name' => 'X', 'email' => 'x@example.com', 'subject' => 'Other']);

        $this->actingAs($user, 'sanctum')->getJson('/api/support/tickets')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user, 'sanctum')->getJson("/api/support/tickets/{$theirs->id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->postJson("/api/support/tickets/{$theirs->id}/messages", ['message' => 'Hi'])->assertNotFound();
    }

    public function test_a_users_reply_notifies_the_admins(): void
    {
        $user = User::factory()->create();
        $this->ask([], $user);
        $ticket = SupportTicket::firstOrFail();
        Queue::fake();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", ['message' => 'More info', 'via' => 'app'])
            ->assertCreated();

        $this->assertDatabaseHas('support_messages', ['body' => 'More info', 'via' => 'app', 'from_admin' => false]);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->type === 'support_message');
    }

    public function test_the_admin_answers_and_the_user_gets_a_mail_and_a_push(): void
    {
        $user = User::factory()->create();
        $this->ask([], $user);
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Try resetting your password', 'via' => 'app'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'answered')
            ->assertJsonPath('data.messages.1.admin.id', $this->admin->id);

        Mail::assertSent(SupportMail::class, fn (SupportMail $mail) => $mail->kind === 'reply' && $mail->hasTo($user->email));
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $user->id
            && $job->type === 'support_reply'
            && $job->data['url'] === '/support/' . $ticket->id);
    }

    public function test_an_answer_stays_unread_until_the_user_opens_the_conversation(): void
    {
        $user = User::factory()->create();
        $this->ask([], $user);
        $ticket = SupportTicket::firstOrFail();
        $this->actingAs($user, 'sanctum')->getJson('/api/support/unread-count')->assertOk()->assertJsonPath('count', 0);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Answer']);

        $this->actingAs($user, 'sanctum')->getJson('/api/support/unread-count')->assertJsonPath('count', 1);
        $this->actingAs($user, 'sanctum')->getJson('/api/support/tickets')->assertJsonPath('data.0.unread_by_user', true);

        $this->actingAs($user, 'sanctum')->getJson("/api/support/tickets/{$ticket->id}")->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/support/unread-count')->assertJsonPath('count', 0);
    }

    public function test_opening_the_email_link_also_reads_the_answer(): void
    {
        $token = $this->ask()->json('data.access_token');
        $ticket = SupportTicket::firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Answer']);
        $this->assertTrue($ticket->fresh()->unread_by_user);

        $this->getJson("/api/support/t/{$token}")->assertOk();

        $this->assertFalse($ticket->fresh()->unread_by_user);
    }

    public function test_a_reply_after_closing_opens_the_ticket_again(): void
    {
        $token = $this->ask()->json('data.access_token');
        $ticket = SupportTicket::firstOrFail();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $this->postJson("/api/support/t/{$token}/messages", ['message' => 'Still broken'])->assertCreated();
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_reopening_restores_the_status_from_the_last_message(): void
    {
        $this->ask();
        $ticket = SupportTicket::firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reply", ['message' => 'Done']);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/close");

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/support/tickets/{$ticket->id}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'answered');
    }

    public function test_the_admin_list_puts_waiting_tickets_first_and_counts_per_status(): void
    {
        $answered = SupportTicket::create(['name' => 'A', 'email' => 'a@example.com', 'subject' => 'A', 'status' => 'answered', 'last_message_at' => now()]);
        $open = SupportTicket::create(['name' => 'B', 'email' => 'b@example.com', 'subject' => 'B', 'status' => 'open', 'last_message_at' => now()->subDay()]);
        SupportTicket::create(['name' => 'C', 'email' => 'c@example.com', 'subject' => 'C', 'status' => 'closed']);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/support/tickets')->assertOk();

        $this->assertSame([$open->id, $answered->id], array_slice(array_column($response->json('data'), 'id'), 0, 2));
        $this->assertSame(['open' => 1, 'answered' => 1, 'closed' => 1], $response->json('meta.counts'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/support/tickets?status=closed')
            ->assertJsonCount(1, 'data');
    }

    public function test_only_admins_reach_the_admin_endpoints(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/admin/support/tickets')->assertForbidden();
    }

    public function test_asking_is_rate_limited_per_sender(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->ask()->assertCreated();
        }

        $this->ask()->assertStatus(429);
    }

    public function test_deleting_an_account_removes_its_support_questions(): void
    {
        $user = User::factory()->create(['email' => 'leaving@example.com']);
        $this->ask([], $user);
        // Asked earlier without logging in, with the same address
        $this->ask(['email' => 'leaving@example.com']);

        $this->actingAs($user, 'sanctum')->postJson('/api/deactivate')->assertOk();

        $this->assertSame(0, SupportTicket::count());
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_old_conversations_are_pruned(): void
    {
        $this->ask();
        SupportTicket::query()->update(['last_message_at' => now()->subMonths(13)]);
        $recent = SupportTicket::create(['name' => 'R', 'email' => 'r@example.com', 'subject' => 'Recent']);

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertSame([$recent->id], SupportTicket::pluck('id')->all());
    }
}

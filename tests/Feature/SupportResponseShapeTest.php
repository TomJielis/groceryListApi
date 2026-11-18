<?php

namespace Tests\Feature;

use App\Jobs\SendMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// The exact fields the app and the website read from the support endpoints: a field added, renamed or dropped by
// accident fails here (SupportConversationResource, SupportTicketResource, SupportTicketMessageResource)
class SupportResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const CONVERSATION = [
        'id', 'subject', 'status', 'name', 'email', 'created_at', 'last_message_at',
        'messages' => ['*' => ['id', 'body', 'from_admin', 'via', 'created_at']],
    ];

    private const TICKET = [
        'id', 'user_id', 'name', 'email', 'subject', 'status', 'unread_by_user', 'language', 'last_message_at',
        'closed_at', 'created_at', 'updated_at',
    ];

    private const ADMIN_CONVERSATION = [
        ...self::TICKET,
        'user' => ['id', 'name', 'email', 'language'],
        'messages' => ['*' => ['id', 'from_admin', 'body', 'via', 'created_at', 'updated_at', 'admin']],
    ];

    private const PAGE_META = ['current_page', 'last_page', 'per_page', 'total'];

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake()->except(SendMail::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create();
    }

    private function ask(): int
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/support/tickets', ['subject' => 'Help', 'message' => 'It broke'])
            ->assertCreated()
            ->assertExactJsonStructure(['data' => self::CONVERSATION])
            ->json('data.id');
    }

    public function test_the_user_side(): void
    {
        $id = $this->ask();

        $this->getJson('/api/support/tickets')->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [...self::TICKET, 'messages_count']]]);
        $this->getJson("/api/support/tickets/{$id}")->assertOk()->assertExactJsonStructure(['data' => self::CONVERSATION]);
        $this->postJson("/api/support/tickets/{$id}/messages", ['message' => 'Again'])->assertCreated()
            ->assertExactJsonStructure(['data' => self::CONVERSATION]);
        $this->getJson('/api/support/unread-count')->assertOk()->assertExactJsonStructure(['count']);
    }

    public function test_the_admin_side(): void
    {
        $id = $this->ask();
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/support/tickets')->assertOk()->assertExactJsonStructure([
            'data' => ['*' => [...self::TICKET, 'messages_count', 'user' => ['id', 'name', 'email']]],
            'meta' => [...self::PAGE_META, 'counts' => ['open', 'answered', 'closed']],
        ]);
        $this->getJson("/api/admin/support/tickets/{$id}")->assertOk()->assertExactJsonStructure(['data' => self::ADMIN_CONVERSATION]);

        $answer = $this->postJson("/api/admin/support/tickets/{$id}/reply", ['message' => 'Fixed'])->assertCreated()
            ->assertExactJsonStructure(['data' => self::ADMIN_CONVERSATION]);
        $answer->assertJsonPath('data.messages.1.admin', ['id' => $this->admin->id, 'name' => $this->admin->name]);

        $this->postJson("/api/admin/support/tickets/{$id}/close")->assertOk()->assertExactJsonStructure(['data' => self::ADMIN_CONVERSATION]);
        $this->postJson("/api/admin/support/tickets/{$id}/reopen")->assertOk()->assertExactJsonStructure(['data' => self::ADMIN_CONVERSATION]);
    }
}

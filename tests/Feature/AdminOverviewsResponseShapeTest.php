<?php

namespace Tests\Feature;

use App\Models\InvalidLoginAttempt;
use App\Models\SentEmail;
use App\Models\SuccessfulLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The exact fields the website reads from the admin users, mails and failed logins overviews: a field added, renamed
// or dropped by accident fails here (AdminUserOverviewResource, SentEmailResource, FailedLoginResource, PageMeta)
class AdminOverviewsResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE_META = ['current_page', 'last_page', 'per_page', 'total'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_the_users_overview(): void
    {
        $this->getJson('/api/admin/users/overview')->assertOk()->assertExactJsonStructure([
            'data' => ['*' => [
                'id', 'name', 'email', 'created_at', 'email_verified', 'terms_version', 'terms_current', 'last_active',
                'lists_count', 'failed_logins', 'blocked',
            ]],
            'meta' => self::PAGE_META,
            'summary' => ['total', 'active', 'unverified', 'blocked', 'terms_version'],
        ]);
    }

    public function test_the_mail_log(): void
    {
        SentEmail::create(['type' => 'welcome', 'recipient_email' => 'a@example.com', 'status' => 'sent', 'triggered_by_user_id' => $this->admin->id, 'sent_at' => now()]);
        $email = [
            'id', 'type', 'recipient_email', 'recipient_name', 'subject', 'body_html', 'body_text', 'status', 'error_message',
            'triggered_by_user_id', 'sent_at', 'created_at', 'updated_at', 'triggered_by' => ['id', 'name', 'email'],
        ];

        $this->getJson('/api/admin/emails')->assertOk()->assertExactJsonStructure([
            'data' => ['*' => $email],
            'meta' => [...self::PAGE_META, 'from', 'to'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
        $this->getJson('/api/admin/emails?limit=5')->assertOk()->assertExactJsonStructure(['data' => ['*' => $email]]);
    }

    public function test_the_failed_logins(): void
    {
        foreach (range(1, 3) as $i) {
            InvalidLoginAttempt::create(['user_id' => $this->admin->id, 'username' => $this->admin->email, 'ip_address' => '203.0.113.5', 'attempted_at' => now()->subHours($i)]);
        }
        SuccessfulLogin::create(['user_id' => $this->admin->id, 'ip_address' => '203.0.113.5', 'client' => 'app', 'logged_in_at' => now()]);

        $this->getJson('/api/admin/login-attempts')->assertOk()->assertExactJsonStructure([
            'data' => ['*' => ['id', 'email', 'user' => ['id', 'name'], 'ip_address', 'attempted_at', 'caused_lockout']],
            'meta' => self::PAGE_META,
            'summary' => [
                'attempts', 'accounts', 'ip_addresses', 'lockouts',
                'top_accounts' => ['*' => ['email', 'user_id', 'attempts', 'ip_addresses', 'last_attempt']],
                'top_ip_addresses' => ['*' => ['ip_address', 'attempts', 'accounts', 'last_attempt']],
                'suspicious' => ['*' => ['ip_address', 'failed_attempts', 'accounts' => ['*' => ['id', 'name', 'email']], 'last_login']],
            ],
        ]);
    }
}

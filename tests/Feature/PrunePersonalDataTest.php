<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * privacy:prune removes login history, failed login attempts and email logs older than the retention period,
 * and keeps everything newer
 */
class PrunePersonalDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function login(string $when): void
    {
        DB::table('successful_logins')->insert([
            'user_id' => $this->user->id, 'ip_address' => '203.0.113.7', 'user_agent' => 'test', 'client' => 'app',
            'logged_in_at' => $when, 'created_at' => $when, 'updated_at' => $when,
        ]);
    }

    private function failedAttempt(string $when): void
    {
        DB::table('invalid_login_attempts')->insert([
            'user_id' => $this->user->id, 'username' => $this->user->email, 'ip_address' => '203.0.113.7',
            'attempted_at' => $when, 'created_at' => $when, 'updated_at' => $when,
        ]);
    }

    private function sentEmail(string $when): void
    {
        DB::table('sent_emails')->insert([
            'type' => 'reset_password', 'recipient_email' => $this->user->email, 'subject' => 'Reset',
            'status' => 'sent', 'sent_at' => $when, 'created_at' => $when, 'updated_at' => $when,
        ]);
    }

    public function test_records_older_than_the_retention_period_are_removed(): void
    {
        $old = now()->subMonths(13)->toDateTimeString();
        $this->login($old);
        $this->failedAttempt($old);
        $this->sentEmail($old);

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('successful_logins')->count());
        $this->assertSame(0, DB::table('invalid_login_attempts')->count());
        $this->assertSame(0, DB::table('sent_emails')->count());
    }

    public function test_recent_records_stay(): void
    {
        $recent = now()->subMonths(11)->toDateTimeString();
        $this->login($recent);
        $this->failedAttempt($recent);
        $this->sentEmail($recent);

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('successful_logins')->count());
        $this->assertSame(1, DB::table('invalid_login_attempts')->count());
        $this->assertSame(1, DB::table('sent_emails')->count());
    }

    public function test_the_retention_period_can_be_configured(): void
    {
        config(['app.personal_data_retention_months' => 6]);
        $this->login(now()->subMonths(7)->toDateTimeString());

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('successful_logins')->count());
    }

    public function test_it_runs_every_day(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('privacy:prune')->assertSuccessful();
    }
}

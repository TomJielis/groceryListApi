<?php

namespace Tests\Feature;

use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmptyLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_login_without_email_or_password_is_refused_and_not_recorded(): void
    {
        foreach ([[], ['email' => ''], ['email' => '  ', 'password' => 'x'], ['email' => 'a@example.com'], ['password' => 'secret'], ['email' => ['a'], 'password' => 'x']] as $body) {
            $this->postJson('/api/login', $body)
                ->assertOk()
                ->assertJsonPath('success', false)
                ->assertJsonPath('status', 422);
        }

        $this->assertSame(0, InvalidLoginAttempt::count());
    }

    public function test_a_wrong_password_is_still_recorded(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertJsonPath('success', false);

        $this->assertSame(1, InvalidLoginAttempt::count());
    }
}

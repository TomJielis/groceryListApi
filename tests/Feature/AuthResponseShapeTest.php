<?php

namespace Tests\Feature;

use App\Jobs\SendMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// The exact fields the app and the website read from login, registration and the own account: a field added, renamed
// or dropped by accident fails here (UserResource, LoginResource, RegistrationResource). Failures of login and
// registration are HTTP 200 with `success`/`error`, which the app and the website show.
class AuthResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    // Every column of users, without password and remember_token
    private const USER = [
        'id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at', 'favorite_list_id', 'language',
        'accepted_terms', 'accepted_terms_at', 'accepted_terms_version', 'is_admin', 'blocked', 'notification_settings',
        'last_active_at', 'favorite_cookbook_id', 'week_planner',
    ];

    private const LOGIN_FAILED = ['error', 'success', 'status'];
    private const REGISTER_FAILED = ['success', 'error', 'message', 'status'];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake()->except(SendMail::class);
    }

    private function register(array $data = [])
    {
        return $this->postJson('/api/register', array_merge([
            'name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'long-enough', 'language' => 'nl',
            'acceptedTerms' => true, 'acceptedTermsVersion' => '1.1',
        ], $data));
    }

    public function test_registering_answers_only_what_was_sent(): void
    {
        $this->register()
            ->assertOk()
            ->assertExactJsonStructure(['token_type', 'user' => ['name', 'email', 'language']])
            ->assertExactJson(['token_type' => 'Bearer', 'user' => ['name' => 'Sam', 'email' => 'sam@example.com', 'language' => 'nl']]);

        // The same answer for an address that already has an account
        $this->register(['name' => 'Other'])->assertOk()->assertJsonPath('user.name', 'Other');
    }

    public function test_a_registration_problem_is_answered_like_a_failed_login(): void
    {
        $this->register(['password' => 'short', 'language' => 'en'])
            ->assertOk()
            ->assertExactJsonStructure(self::REGISTER_FAILED)
            ->assertExactJson([
                'success' => false,
                'error' => 'Your password must be at least 8 characters.',
                'message' => 'Your password must be at least 8 characters.',
                'status' => 422,
            ]);
    }

    public function test_logging_in_answers_a_token_and_the_account(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertExactJsonStructure(['access_token', 'token_type', 'user' => self::USER])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertIsString($response->json('access_token'));
    }

    public function test_a_failed_login_is_answered_with_200(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertOk()
            ->assertExactJsonStructure(self::LOGIN_FAILED)
            ->assertExactJson(['error' => 'Invalid login attempt', 'success' => false, 'status' => 401]);

        $this->postJson('/api/login', [])
            ->assertOk()
            ->assertExactJson(['error' => 'Email and password are required', 'success' => false, 'status' => 422]);
    }

    public function test_the_own_account_is_answered_without_a_data_wrapper(): void
    {
        // With a real token, so the user is read from the database like in the app (actingAs would answer only the
        // factory's fields)
        $this->withToken(User::factory()->create()->createToken('phone')->plainTextToken);

        $this->postJson('/api/me')->assertOk()->assertExactJsonStructure(['user' => self::USER]);
        $this->postJson('/api/update', ['body' => ['name' => 'Sam']])->assertOk()->assertExactJsonStructure(self::USER);
        $this->postJson('/api/update-language', ['language' => 'en'])->assertOk()->assertExactJsonStructure(self::USER);
        $this->postJson('/api/approve-terms', ['acceptedTermsVersion' => '1.2'])->assertOk()->assertExactJsonStructure(self::USER);
    }

    public function test_the_plain_answers_keep_their_shape(): void
    {
        $this->postJson('/api/check-reset-link', ['token' => 'x'])->assertOk()->assertExactJson(['valid' => false]);
        $this->getJson('/api/verify-email/x')->assertOk()->assertExactJsonStructure(['success', 'reason', 'message']);
        $this->postJson('/api/set-new-password', ['password' => 'short'])->assertStatus(400)->assertExactJsonStructure(['reason', 'message']);
        $this->postJson('/api/reset-password', [])->assertStatus(400)->assertExactJson(['message' => 'Email is verplicht']);
    }
}

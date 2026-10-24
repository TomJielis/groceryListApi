<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2eUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_makes_a_verified_user_who_accepted_the_terms(): void
    {
        $this->assertSame(0, Artisan::call('e2e:user', ['action' => 'create', 'email' => 'someone@e2e.test', '--admin' => true]));

        $data = json_decode(Artisan::output(), true);
        $user = User::where('email', 'someone@e2e.test')->firstOrFail();
        $this->assertSame($user->id, $data['id']);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->accepted_terms);
        $this->assertSame(config('app.terms_version'), $user->accepted_terms_version);
        $this->assertTrue($user->is_admin);
    }

    public function test_only_e2e_test_addresses_are_accepted(): void
    {
        $real = User::factory()->create(['email' => 'real@example.com']);

        $this->assertSame(1, Artisan::call('e2e:user', ['action' => 'create', 'email' => 'new@example.com']));
        $this->assertSame(1, Artisan::call('e2e:user', ['action' => 'delete', 'email' => 'real@example.com']));
        $this->assertSame(1, Artisan::call('e2e:user', ['action' => 'reset', 'email' => 'real@example.com']));

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
    }

    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame(1, Artisan::call('e2e:user', ['action' => 'create', 'email' => 'someone@e2e.test']));
        $this->assertDatabaseMissing('users', ['email' => 'someone@e2e.test']);
    }

    public function test_delete_removes_the_matching_users_and_what_they_made(): void
    {
        $real = User::factory()->create(['email' => 'real@example.com']);
        Artisan::call('e2e:user', ['action' => 'create', 'email' => 'e2e-web@e2e.test']);
        Artisan::call('e2e:user', ['action' => 'create', 'email' => 'e2e-app@e2e.test']);
        $web = User::where('email', 'e2e-web@e2e.test')->firstOrFail();
        $listId = DB::table('grocery_lists')->insertGetId(['name' => 'E2E', 'created_by' => $web->id]);
        DB::table('grocery_list_items')->insert(['name' => 'Milk', 'list_id' => $listId, 'created_by' => $web->id]);
        DB::table('grocery_list_invites')->insert(['grocery_list_id' => $listId, 'email' => 'real@example.com', 'status' => 'pending']);

        $this->assertSame(0, Artisan::call('e2e:user', ['action' => 'delete', 'email' => 'e2e-web*@e2e.test']));

        $this->assertDatabaseMissing('users', ['id' => $web->id]);
        $this->assertDatabaseMissing('grocery_lists', ['id' => $listId]);
        $this->assertDatabaseMissing('grocery_list_items', ['list_id' => $listId]);
        $this->assertDatabaseMissing('grocery_list_invites', ['grocery_list_id' => $listId]);
        // Other test users (the app's) and real users stay
        $this->assertDatabaseHas('users', ['email' => 'e2e-app@e2e.test']);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
    }

    public function test_reset_removes_lists_and_cards_but_keeps_the_user(): void
    {
        Artisan::call('e2e:user', ['action' => 'create', 'email' => 'e2e-web@e2e.test']);
        $user = User::where('email', 'e2e-web@e2e.test')->firstOrFail();
        DB::table('grocery_lists')->insert(['name' => 'E2E', 'created_by' => $user->id]);
        DB::table('cards')->insert(['title' => 'Card', 'attachment' => 'data:', 'user_id' => $user->id]);

        $this->assertSame(0, Artisan::call('e2e:user', ['action' => 'reset', 'email' => 'e2e-web@e2e.test']));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('grocery_lists', ['created_by' => $user->id]);
        $this->assertDatabaseMissing('cards', ['user_id' => $user->id]);
    }
}

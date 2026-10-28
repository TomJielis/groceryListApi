<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Throwaway users for the end-to-end tests of the website (Playwright) and the app (Maestro).
 * Only runs locally and only touches addresses on the reserved @e2e.test domain, so real users are never affected.
 */
class E2eUser extends Command
{
    public const DOMAIN = '@e2e.test';

    protected $signature = 'e2e:user
        {action : create, reset (removes the lists and cards of the user, keeps the user) or delete}
        {email? : An address ending in @e2e.test; delete also takes a * wildcard, or no address for every @e2e.test user}
        {--name=E2E User : Name of the user to create}
        {--admin : Make the user an admin}
        {--language=en : Language of the user to create (en or nl)}';

    protected $description = 'Create or delete a throwaway @e2e.test user for the end-to-end tests (local/testing only)';

    public function handle(): int
    {
        if (!app()->environment('local', 'testing')) {
            $this->error('e2e:user only runs when APP_ENV is local or testing.');
            return self::FAILURE;
        }

        $email = $this->argument('email');
        if ($email !== null && !str_ends_with(strtolower($email), self::DOMAIN)) {
            $this->error('Only addresses ending in ' . self::DOMAIN . ' can be used.');
            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'create' => $this->create($email),
            'reset' => $this->reset($email),
            'delete' => $this->delete($email),
            default => $this->invalidAction(),
        };
    }

    /**
     * Prints {"id", "email", "password"} as JSON: the caller keeps it in a git-ignored file
     */
    private function create(?string $email): int
    {
        if ($email === null) {
            $this->error('create needs an email address.');
            return self::FAILURE;
        }

        // Start from a clean slate when a previous run didn't clean up
        $this->deleteUsers([strtolower($email)]);

        $password = Str::password(24, symbols: false);
        $id = DB::table('users')->insertGetId([
            'name' => $this->option('name'),
            'email' => strtolower($email),
            'password' => Hash::make($password),
            'language' => $this->option('language') === 'nl' ? 'nl' : 'en',
            'email_verified_at' => now(),
            'accepted_terms' => true,
            'accepted_terms_at' => now(),
            'accepted_terms_version' => config('app.terms_version'),
            'is_admin' => (bool) $this->option('admin'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line(json_encode(['id' => $id, 'email' => strtolower($email), 'password' => $password]));
        return self::SUCCESS;
    }

    private function reset(?string $email): int
    {
        if ($email === null) {
            $this->error('reset needs an email address.');
            return self::FAILURE;
        }

        $userId = DB::table('users')->where('email', strtolower($email))->value('id');
        if ($userId !== null) {
            DB::transaction(function () use ($userId) {
                $this->deleteLists([$userId]);
                DB::table('cards')->where('user_id', $userId)->delete();
            });
        }
        return self::SUCCESS;
    }

    private function delete(?string $email): int
    {
        $pattern = $email === null ? '%' . self::DOMAIN : str_replace('*', '%', strtolower($email));
        $emails = DB::table('users')->where('email', 'like', $pattern)->pluck('email')->all();

        $count = $this->deleteUsers($emails);
        // Invites and mails to test addresses without an account
        DB::table('grocery_list_invites')->where('email', 'like', $pattern)->delete();
        DB::table('sent_emails')->where('recipient_email', 'like', $pattern)->delete();
        DB::table('support_tickets')->where('email', 'like', $pattern)->delete();

        $this->info("Deleted {$count} e2e user(s).");
        return self::SUCCESS;
    }

    /**
     * Raw queries on purpose: the GroceryList model has a global scope that hides other users' lists
     *
     * @param list<string> $emails
     */
    private function deleteUsers(array $emails): int
    {
        $emails = array_values(array_filter($emails, fn ($e) => str_ends_with($e, self::DOMAIN)));
        if ($emails === []) {
            return 0;
        }

        $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id')->all();

        DB::transaction(function () use ($userIds, $emails) {
            $this->deleteLists($userIds);
            DB::table('grocery_list_invites')->whereIn('user_id', $userIds)->orWhereIn('email', $emails)->delete();

            DB::table('cards')->whereIn('user_id', $userIds)->delete();
            DB::table('push_tokens')->whereIn('user_id', $userIds)->delete();
            DB::table('successful_logins')->whereIn('user_id', $userIds)->delete();
            DB::table('temporary_password_codes')->whereIn('user_id', $userIds)->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();
            DB::table('invalid_login_attempts')->whereIn('user_id', $userIds)->orWhereIn('username', $emails)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            DB::table('sent_emails')->whereIn('triggered_by_user_id', $userIds)->orWhereIn('recipient_email', $emails)->delete();
            DB::table('support_tickets')->whereIn('user_id', $userIds)->orWhereIn('email', $emails)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        });

        return count($userIds);
    }

    /**
     * The lists these users own, with their items and invites
     *
     * @param list<int> $userIds
     */
    private function deleteLists(array $userIds): void
    {
        $listIds = DB::table('grocery_lists')->whereIn('created_by', $userIds)->pluck('id')->all();

        DB::table('grocery_list_items')->whereIn('list_id', $listIds)->delete();
        DB::table('grocery_list_invites')->whereIn('grocery_list_id', $listIds)->delete();
        DB::table('users')->whereIn('favorite_list_id', $listIds)->update(['favorite_list_id' => null]);
        DB::table('grocery_lists')->whereIn('id', $listIds)->delete();
    }

    private function invalidAction(): int
    {
        $this->error('Action must be create or delete.');
        return self::FAILURE;
    }
}

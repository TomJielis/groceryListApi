<?php

namespace App\Services;

use App\Data\LoginResult;
use App\Data\NewAccount;
use App\Mail\AccountExists;
use App\Mail\ResetPassword;
use App\Mail\Welcome;
use App\Models\Cookbook;
use App\Models\InvalidLoginAttempt;
use App\Models\SuccessfulLogin;
use App\Models\TemporaryPasswordCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Accounts: logging in (with the lock after failed attempts), registering, verifying the email address, resetting
 * the password and removing an account with everything that belongs to it
 */
class AccountService
{
    public const RESET_LINK_MINUTES = 60;

    // verifyEmail's outcomes; the app and the website show a message for each
    public const VERIFIED = 'verified';
    public const INVALID_LINK = 'invalid_link';
    public const ALREADY_VERIFIED = 'already_verified';

    /**
     * Every failed attempt is recorded; the third within 15 minutes locks the account from that address for 15 minutes
     */
    public function login(string $email, string $password, Request $request): LoginResult
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            InvalidLoginAttempt::create([
                'username' => $email,
                'user_id' => null,
                'ip_address' => $request->ip(),
                'attempted_at' => now(),
                'blocked_till' => null,
            ]);

            return LoginResult::failed('Invalid login attempt', 401);
        }

        // Locked per account and address together: someone guessing elsewhere can't lock the owner out
        $isBlocked = InvalidLoginAttempt::where('user_id', $user->id)
            ->where('ip_address', $request->ip())
            ->where('blocked_till', '>', now())
            ->exists();

        if ($isBlocked) {
            return LoginResult::failed('Too many failed login attempts. Please try again later.', 429);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            $invalidAttemptsCount = InvalidLoginAttempt::where('user_id', $user->id)
                ->where('ip_address', $request->ip())
                ->where('attempted_at', '>=', now()->subMinutes(15))
                ->count();

            InvalidLoginAttempt::create([
                'user_id' => $user->id,
                'username' => $user->email,
                'ip_address' => $request->ip(),
                'attempted_at' => now(),
                'blocked_till' => $invalidAttemptsCount >= 2 ? now()->addMinutes(15) : null,
            ]);

            return LoginResult::failed('Invalid login attempt', 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->email_verified_at === null) {
            return LoginResult::failed('Email not verified. Please activate your account through the email sent to you.', 403);
        }

        if ($user->blocked) {
            return LoginResult::failed('You account has been blocked.', 403);
        }

        $login = SuccessfulLogin::record($user, $request);
        // Named after where it was made (app, website or other), like the login it belongs to
        $token = $user->createToken($login->client, ['*'], now()->addDays(14))->plainTextToken;

        return LoginResult::succeeded($user, $token);
    }

    /**
     * Makes the account and mails the verify link; an address that has an account gets a mail saying so instead.
     * False when the mail couldn't be sent (a new account is kept then).
     */
    public function register(NewAccount $account): bool
    {
        $existing = User::where('email', $account->email)->first();

        return $existing ? $this->tellAccountExists($existing, $account->password) : $this->create($account);
    }

    private function create(NewAccount $account): bool
    {
        $user = new User();
        $user->name = $account->name;
        $user->password = Hash::make($account->password);
        $user->email = $account->email;
        $user->language = $account->language;
        $user->accepted_terms = $account->acceptedTerms;
        $user->accepted_terms_at = Carbon::now();
        $user->accepted_terms_version = $account->acceptedTermsVersion;
        $user->save();
        Cookbook::createFirstFor($user);

        $url = config('app.url') . '/auth/' . Crypt::encryptString($user->id);
        $this->resetMailFrom();

        return $this->send(fn () => $this->mail()->send(new Welcome($url, $user), $user->email, $user->name)) === null;
    }

    private function tellAccountExists(User $user, string $password): bool
    {
        // A new account hashes the password; the same work here, so the answer doesn't come noticeably sooner
        Hash::make($password);

        return $this->send(fn () => $this->mail()->send(new AccountExists($user), $user->email, $user->name)) === null;
    }

    // The outcome of the link in the welcome mail (the user's id, encrypted)
    public function verifyEmail(string $token): string
    {
        try {
            $user = User::find(Crypt::decryptString($token));
        } catch (DecryptException) {
            $user = null;
        }

        if (!$user) {
            return self::INVALID_LINK;
        }
        // Opening the link again: nothing to do, the account is ready to use
        if ($user->email_verified_at) {
            return self::ALREADY_VERIFIED;
        }

        $user->email_verified_at = Carbon::now();
        $user->save();

        return self::VERIFIED;
    }

    public function acceptTerms(User $user, string $version): void
    {
        $user->accepted_terms = true;
        $user->accepted_terms_at = Carbon::now();
        // Never lower it: an older app accepting its own (older) version after a newer one was accepted elsewhere
        // would otherwise make the newer app and the website ask again, back and forth
        $current = (string) $user->accepted_terms_version;
        if ($current === '' || version_compare($version, $current, '>')) {
            $user->accepted_terms_version = $version;
        }
        $user->save();
    }

    /**
     * Mails a new reset link (an earlier one that's still open stops working) to the address as it was typed.
     * The error message when the mail couldn't be sent, otherwise null.
     */
    public function sendResetLink(User $user, string $email): ?string
    {
        TemporaryPasswordCode::where('user_id', '=', $user->id)
            ->where('is_used', '=', false)
            ->delete();

        $code = Crypt::encryptString($user->email . '-' . Carbon::now() . '-' . $user->id);

        $temporaryPasswordCode = new TemporaryPasswordCode();
        $temporaryPasswordCode->user_id = $user->id;
        $temporaryPasswordCode->code = $code;
        $temporaryPasswordCode->is_used = false;
        $temporaryPasswordCode->save();

        $url = config('app.url') . '/auth/password/' . $code;
        $this->resetMailFrom();

        return $this->send(fn () => $this->mail()->send(new ResetPassword($url, $user, $code), $email));
    }

    /**
     * A reset link that can still be used: not used yet and at most an hour old (and of this user, when given)
     */
    public function usableResetCode(mixed $token, ?User $user = null): ?TemporaryPasswordCode
    {
        return TemporaryPasswordCode::where('code', '=', $token)
            ->when($user, fn ($query) => $query->where('user_id', '=', $user->id))
            ->where('is_used', '=', false)
            ->where('created_at', '>=', now()->subMinutes(self::RESET_LINK_MINUTES))
            ->first();
    }

    public function setNewPassword(User $user, TemporaryPasswordCode $code, string $password): void
    {
        $user->password = Hash::make($password);
        $user->save();
        $code->is_used = true;
        $code->save();

        // Whoever knew the old password is logged out everywhere
        $user->tokens()->delete();
    }

    public function deactivate(User $user): void
    {
        // Others who had one of these lists as favorite no longer point at a list that's gone
        User::whereIn('favorite_list_id', $user->groceryLists->pluck('id'))->update(['favorite_list_id' => null]);
        foreach ($user->groceryLists as $groceryList) {
            $groceryList->groceryListItems()->delete();
            $groceryList->groceryListInvites()->delete();
            $groceryList->delete();
        }

        $user->groceryListInvites()->delete();
        $user->cards()->delete();
        // Their cookbooks go with all recipes and photos, like their lists; recipes they added to someone else's stay
        foreach ($user->cookbooks as $cookbook) {
            $cookbook->deleteWithRecipes();
        }
        $user->cookbookInvites()->delete();

        $user->temporaryPasswordCodes()->delete();
        $user->personalAccessTokens()->delete();
        // Personal data that isn't linked to the user row (so it isn't removed with it): failed logins with their
        // email address, the mails sent to them, their web sessions and support questions. Login history and push tokens go with the user.
        DB::table('invalid_login_attempts')->where('user_id', $user->id)->orWhere('username', $user->email)->delete();
        DB::table('sent_emails')->where('recipient_email', $user->email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        // Support questions, also those asked without logging in with this address (messages go with their ticket)
        DB::table('support_tickets')->where('user_id', $user->id)->orWhere('email', $user->email)->delete();
        $user->delete();
    }

    private function resetMailFrom(): void
    {
        Config::set('mail.from', [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ]);
    }

    // Resolved when sending, not once with the service, so a mail service swapped in (tests) is the one used
    private function mail(): MailService
    {
        return app(MailService::class);
    }

    // Sends a mail; the error message when that failed (logged), otherwise null
    private function send(callable $send): ?string
    {
        try {
            $send();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return $exception->getMessage();
        }

        return null;
    }
}

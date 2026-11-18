<?php

namespace App\Queries\Security;

use App\Data\LoginAttemptFilter;
use App\Models\InvalidLoginAttempt;
use App\Models\SuccessfulLogin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Failed logins for the admin overview: the attempts themselves (paginated) plus a summary of the same selection, so
 * an attack on one account or from one address stands out
 */
class FailedLoginsQuery
{
    private const PERIODS = ['24h' => 24, '7d' => 24 * 7, '30d' => 24 * 30];

    // From this many failed attempts, a successful login from the same address afterwards is a warning sign
    private const SUSPICIOUS_FAILURES = 3;

    // Requests from this machine itself: every old record (and website logins before the website passed the
    // visitor's address on) has one of these, so they would only give false alarms
    private const LOCAL_ADDRESSES = ['127.0.0.1', '::1'];

    public function page(LoginAttemptFilter $filter, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filter)
            ->with('user:id,name,email')
            ->orderByDesc('attempted_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param int $attempts The number of attempts in the selection (the paginator's total)
     * @return array<string, mixed>
     */
    public function summary(LoginAttemptFilter $filter, int $attempts): array
    {
        return [
            'attempts' => $attempts,
            'accounts' => $this->filtered($filter)->distinct()->count('username'),
            'ip_addresses' => $this->filtered($filter)->distinct()->count('ip_address'),
            'lockouts' => $this->filtered($filter)->whereNotNull('blocked_till')->count(),
            'top_accounts' => $this->filtered($filter)
                ->selectRaw('username, user_id, COUNT(*) as attempts, COUNT(DISTINCT ip_address) as ip_addresses, MAX(attempted_at) as last_attempt')
                ->groupBy('username', 'user_id')
                ->orderByDesc('attempts')
                ->limit(5)
                ->get()
                ->load('user:id,email')
                ->map(fn ($row) => [
                    'email' => $row->username ?? $row->user?->email,
                    'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                    'attempts' => (int) $row->attempts,
                    'ip_addresses' => (int) $row->ip_addresses,
                    'last_attempt' => Carbon::parse($row->last_attempt)->toJSON(),
                ]),
            'top_ip_addresses' => $this->filtered($filter)
                ->selectRaw('ip_address, COUNT(*) as attempts, COUNT(DISTINCT username) as accounts, MAX(attempted_at) as last_attempt')
                ->groupBy('ip_address')
                ->orderByDesc('attempts')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'ip_address' => $row->ip_address,
                    'attempts' => (int) $row->attempts,
                    'accounts' => (int) $row->accounts,
                    'last_attempt' => Carbon::parse($row->last_attempt)->toJSON(),
                ]),
            'suspicious' => $this->suspicious($filter),
        ];
    }

    /**
     * Addresses with several failed attempts that afterwards logged in successfully: someone may have
     * guessed a password. Per address, the accounts that logged in from there after the first failure.
     */
    private function suspicious(LoginAttemptFilter $filter): array
    {
        return $this->filtered($filter)
            ->whereNotIn('ip_address', self::LOCAL_ADDRESSES)
            ->selectRaw('ip_address, COUNT(*) as attempts, MIN(attempted_at) as first_attempt')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [self::SUSPICIOUS_FAILURES])
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $logins = SuccessfulLogin::with('user:id,name,email')
                    ->where('ip_address', $row->ip_address)
                    ->where('logged_in_at', '>=', $row->first_attempt)
                    ->orderByDesc('logged_in_at')
                    ->get();

                return $logins->isEmpty() ? null : [
                    'ip_address' => $row->ip_address,
                    'failed_attempts' => (int) $row->attempts,
                    'accounts' => $logins->pluck('user')->filter()->unique('id')
                        ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
                        ->values(),
                    'last_login' => $logins->first()->logged_in_at,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    // The same filters for the list and every summary number
    private function filtered(LoginAttemptFilter $filter): Builder
    {
        return InvalidLoginAttempt::query()
            ->when(isset(self::PERIODS[$filter->period]), fn ($q) => $q->where('attempted_at', '>=', now()->subHours(self::PERIODS[$filter->period])))
            ->when($filter->account === 'known', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filter->account === 'unknown', fn ($q) => $q->whereNull('user_id'))
            ->when($filter->email !== null, fn ($q) => $q->where('username', 'like', '%' . $filter->email . '%'))
            ->when($filter->ip !== null, fn ($q) => $q->where('ip_address', $filter->ip));
    }
}

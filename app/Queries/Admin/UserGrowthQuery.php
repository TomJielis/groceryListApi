<?php

namespace App\Queries\Admin;

use App\Models\InvalidLoginAttempt;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

// Accounts by the end of a month: how many, new, active, verified, and the failed logins in it
class UserGrowthQuery
{
    /**
     * @return array{total: int, new_registrations: int, active: int, verified_email: int, invalid_login_attempts: int}
     */
    public function forMonth(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();

        // The current month counts who used the app in the last 30 days. last_active_at only holds the
        // latest visit, so earlier months still come from the tokens (as close as that history goes).
        $active = $endOfMonth->isFuture()
            ? User::active()->count()
            : PersonalAccessToken::where('tokenable_type', User::class)
                ->where('last_used_at', '>=', $endOfMonth->copy()->subDays(30))
                ->where('last_used_at', '<=', $endOfMonth)
                ->distinct('tokenable_id')
                ->count('tokenable_id');

        return [
            'total' => User::where('created_at', '<=', $endOfMonth)->count(),
            'new_registrations' => User::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            'active' => $active,
            'verified_email' => User::where('created_at', '<=', $endOfMonth)->whereNotNull('email_verified_at')->count(),
            'invalid_login_attempts' => InvalidLoginAttempt::whereYear('attempted_at', $month->year)->whereMonth('attempted_at', $month->month)->count(),
        ];
    }
}

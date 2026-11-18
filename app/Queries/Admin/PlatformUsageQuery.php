<?php

namespace App\Queries\Admin;

use App\Models\SuccessfulLogin;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who used the website, the iPhone app and the Android app in the same 30 days as "Active users". Someone on two
 * platforms counts once on each, so the counts can add up to more than active_users (who are counted once).
 * A token only remembers its latest use and goes on logout, so logins in the period count too.
 */
class PlatformUsageQuery
{
    // What the admin calls each platform => how tokens and logins name it (SuccessfulLogin::record)
    private const PLATFORM_CLIENTS = ['website' => 'website', 'ios' => 'app', 'android' => 'android'];

    /**
     * @return array{active_users: int, on_multiple_platforms: int, breakdown: array<string, array{count: int, percentage: float|int}>}
     */
    public function forMonth(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();
        $until = $endOfMonth->isFuture() ? Carbon::now() : $endOfMonth;
        $period = [$until->copy()->subDays(30), $until];
        $clients = array_values(self::PLATFORM_CLIENTS);

        // The union leaves one row per user and client
        $usage = PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('name', $clients)
            ->whereBetween('last_used_at', $period)
            ->select('name as client', 'tokenable_id as user_id')
            ->toBase()
            ->union(
                SuccessfulLogin::whereIn('client', $clients)
                    ->whereBetween('logged_in_at', $period)
                    ->select('client', 'user_id')
                    ->toBase()
            )
            ->get();

        $perUser = $usage->countBy('user_id');
        $activeUsers = $perUser->count();

        $breakdown = [];
        foreach (self::PLATFORM_CLIENTS as $platform => $client) {
            $count = $usage->where('client', $client)->count();
            $breakdown[$platform] = [
                'count' => $count,
                'percentage' => $activeUsers > 0 ? round(($count / $activeUsers) * 100, 1) : 0,
            ];
        }

        return [
            'active_users' => $activeUsers,
            'on_multiple_platforms' => $perUser->filter(fn (int $platforms) => $platforms > 1)->count(),
            'breakdown' => $breakdown,
        ];
    }
}

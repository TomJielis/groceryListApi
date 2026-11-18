<?php

namespace App\Queries\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Which terms version the accounts by the end of a month accepted, and how many are on the current one
class TermsVersionsQuery
{
    /**
     * @return array{latest_version: string, breakdown: array<string, array{count: int, percentage: float|int}>, on_latest: array{count: int, percentage: float|int}}
     */
    public function forMonth(Carbon $month): array
    {
        // The terms version that currently applies (not the highest one found, which sorts as text: "1.10" < "1.9")
        $latestVersion = (string) config('app.terms_version');

        $versionCounts = User::select('accepted_terms_version', DB::raw('COUNT(*) as count'))
            ->where('created_at', '<=', $month->copy()->endOfMonth())
            ->groupBy('accepted_terms_version')
            ->get();

        $total = $versionCounts->sum('count');
        $percentage = fn ($count) => $total > 0 ? round(($count / $total) * 100, 1) : 0;

        $breakdown = [];
        $onLatestCount = 0;
        foreach ($versionCounts as $version) {
            $breakdown[$version->accepted_terms_version ?? 'undefined'] = [
                'count' => $version->count,
                'percentage' => $percentage($version->count),
            ];
            if ($version->accepted_terms_version === $latestVersion) {
                $onLatestCount = $version->count;
            }
        }

        return [
            'latest_version' => $latestVersion,
            'breakdown' => $breakdown,
            'on_latest' => ['count' => $onLatestCount, 'percentage' => $percentage($onLatestCount)],
        ];
    }
}

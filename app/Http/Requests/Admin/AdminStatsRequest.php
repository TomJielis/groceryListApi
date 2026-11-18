<?php

namespace App\Http\Requests\Admin;

use App\Data\StatsMonths;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin's statistics for a month (?month=2026-10) and the one before. Without a month, or with one that isn't
 * written like that, it's the current month (the dashboard always shows something). Admins only: AdminMiddleware.
 */
class AdminStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function months(): StatsMonths
    {
        $month = $this->query('month');
        $current = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            // '!' sets the missing day to the 1st; otherwise today's day is used and the 31st overflows a month
            ? Carbon::createFromFormat('!Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();

        return new StatsMonths($current, $current->copy()->subMonth());
    }
}

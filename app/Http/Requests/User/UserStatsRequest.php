<?php

namespace App\Http\Requests\User;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "My statistics" for a month (?month=2026-10). Without a month, or with one that isn't written like that, it's the
 * current month: the app always shows something, so nothing is rejected.
 */
class UserStatsRequest extends FormRequest
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

    public function month(): Carbon
    {
        $month = $this->query('month');
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            // '!' sets the missing day to the 1st; otherwise today's day is used and the 31st overflows a month
            return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }
}

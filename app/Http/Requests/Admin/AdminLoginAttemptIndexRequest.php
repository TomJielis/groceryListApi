<?php

namespace App\Http\Requests\Admin;

use App\Data\LoginAttemptFilter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Failed logins for the admin overview: period, account, email and address filters, and paging
 */
class AdminLoginAttemptIndexRequest extends FormRequest
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
        return [
            'period' => 'sometimes|in:24h,7d,30d,all',
            'account' => 'sometimes|in:known,unknown',
            'email' => 'sometimes|string|max:255',
            'ip' => 'sometimes|string|max:45',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function filter(): LoginAttemptFilter
    {
        return new LoginAttemptFilter(
            period: $this->query('period', '7d'),
            account: $this->query('account'),
            email: $this->filled('email') ? $this->query('email') : null,
            ip: $this->filled('ip') ? $this->query('ip') : null,
        );
    }

    public function perPage(): int
    {
        return (int) $this->query('per_page', 20);
    }
}

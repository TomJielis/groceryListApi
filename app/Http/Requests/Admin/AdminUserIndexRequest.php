<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin users overview: search, filters, sorting and paging. Only admins get here (the admin middleware).
 */
class AdminUserIndexRequest extends FormRequest
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
            'search' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,unverified,blocked',
            'terms' => 'sometimes|in:current,outdated',
            'sort' => 'sometimes|in:last_active,newest,lists',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function search(): ?string
    {
        return $this->query('search');
    }

    public function status(): ?string
    {
        return $this->query('status');
    }

    public function terms(): ?string
    {
        return $this->query('terms');
    }

    public function sort(): string
    {
        return $this->query('sort', 'last_active');
    }

    public function perPage(): int
    {
        return (int) $this->query('per_page', 20);
    }
}

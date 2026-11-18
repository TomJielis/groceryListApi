<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin's support tickets: a status filter, a search on subject, address and name, and paging
 */
class AdminSupportIndexRequest extends FormRequest
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
        return ['status' => ['nullable', 'in:open,answered,closed']];
    }

    public function status(): ?string
    {
        return $this->filled('status') ? $this->query('status') : null;
    }

    public function search(): ?string
    {
        return $this->filled('search') ? (string) $this->query('search') : null;
    }

    // Not validated: out of range is brought back to 1–100
    public function perPage(): int
    {
        return max(1, min((int) $this->query('per_page', 20), 100));
    }
}

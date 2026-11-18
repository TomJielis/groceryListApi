<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The log of sent mails for the admin: status, type and date filters, and a page or the latest few (limit). Nothing is
 * validated: an unknown filter value simply finds nothing, as it always has.
 */
class AdminEmailIndexRequest extends FormRequest
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

    public function status(): ?string
    {
        return $this->filledQuery('status');
    }

    public function type(): ?string
    {
        return $this->filledQuery('type');
    }

    public function dateFrom(): ?string
    {
        return $this->filledQuery('date_from');
    }

    public function dateTo(): ?string
    {
        return $this->filledQuery('date_to');
    }

    // The latest few instead of a page (the dashboard)
    public function limit(): ?int
    {
        return $this->filled('limit') ? (int) $this->query('limit') : null;
    }

    public function perPage(): int
    {
        return min((int) $this->query('per_page', 20), 100);
    }

    private function filledQuery(string $key): mixed
    {
        return $this->filled($key) ? $this->query($key) : null;
    }
}

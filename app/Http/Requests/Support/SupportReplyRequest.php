<?php

namespace App\Http\Requests\Support;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The person who asked writes again in their own ticket (which reopens a closed one)
 */
class SupportReplyRequest extends FormRequest
{
    private ?SupportTicket $ticket = null;

    public function authorize(): bool
    {
        return true;
    }

    // Someone else's or a missing ticket answers 404 before the message is validated, as it always has
    protected function prepareForValidation(): void
    {
        $this->ticket();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'via' => ['nullable', 'in:app,website'],
        ];
    }

    // Only the user's own tickets
    public function ticket(): SupportTicket
    {
        return $this->ticket ??= $this->user()->supportTickets()->findOrFail((int) $this->route('id'));
    }

    public function message(): string
    {
        return $this->validated('message');
    }

    public function via(): string
    {
        return $this->validated('via') ?? 'website';
    }
}

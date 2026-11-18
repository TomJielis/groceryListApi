<?php

namespace App\Http\Requests\Support;

use App\Data\SupportQuestion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking a support question (signed in only)
 */
class SupportTicketStoreRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'language' => ['nullable', 'in:nl,en'],
            'via' => ['nullable', 'in:app,website'],
        ];
    }

    public function question(): SupportQuestion
    {
        return new SupportQuestion(
            subject: $this->validated('subject'),
            message: $this->validated('message'),
            language: $this->validated('language'),
            via: $this->validated('via') ?? 'website',
        );
    }
}

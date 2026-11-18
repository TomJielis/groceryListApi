<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin answers a support ticket, from the app or the website
 */
class AdminSupportReplyRequest extends FormRequest
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
        return ['message' => ['required', 'string', 'max:5000']];
    }

    public function message(): string
    {
        return $this->validated('message');
    }

    // Not validated: anything but app counts as the website
    public function via(): string
    {
        return $this->input('via') === 'app' ? 'app' : 'website';
    }
}

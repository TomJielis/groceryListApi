<?php

namespace App\Http\Requests\PushToken;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Expo push token of the device the user is logged in on. The messages are Laravel's own (not the user's
 * language), as the app has always received them.
 */
class PushTokenStoreRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
        ];
    }

    public function token(): string
    {
        return $this->validated('token');
    }
}

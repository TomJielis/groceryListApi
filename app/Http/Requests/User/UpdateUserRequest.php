<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

// The user's own name; the app and the website send it as body.name. Without one: 422 (it used to be a server error)
class UpdateUserRequest extends FormRequest
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
            'body.name' => 'required|string|max:255',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $english = $this->user()?->language === 'en';

        $message = $english ? 'Please enter your name (at most 255 characters).' : 'Vul je naam in (hoogstens 255 tekens).';

        return ['body.name.required' => $message, 'body.name.string' => $message, 'body.name.max' => $message];
    }

    public function name(): string
    {
        return $this->validated('body.name');
    }
}

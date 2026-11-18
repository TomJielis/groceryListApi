<?php

namespace App\Http\Requests\User;

use App\Data\NewAccount;
use Illuminate\Contracts\Validation\Validator;

/**
 * A new account. A problem is answered like a failed login (HTTP 200 with `error`): the app and the website show that
 * message. Only the first problem (name, email, password, then the terms), in the language the user picked.
 */
class RegisterUserRequest extends AccountRequest
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:' . self::MIN_PASSWORD_LENGTH . '|max:255',
            // The terms version they accepted, a version number like ApproveTermsRequest ("1.4"); the app and the
            // website always send it, without it no account is made
            'acceptedTermsVersion' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,3}){0,2}$/'],
        ];
    }

    // " sam@example.com " from a phone keyboard is the same address
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }

    protected function failedValidation(Validator $validator): never
    {
        $english = $this->input('language') === 'en';
        $errors = $validator->errors();

        if ($errors->has('name')) {
            $error = $english ? 'Please enter your name.' : 'Vul je naam in.';
        } elseif ($errors->has('email')) {
            $error = $english ? 'Please enter a valid email address.' : 'Vul een geldig e-mailadres in.';
        } elseif ($errors->has('password')) {
            $error = $english
                ? 'Your password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
                : 'Je wachtwoord moet minimaal ' . self::MIN_PASSWORD_LENGTH . ' tekens hebben.';
        } else {
            $error = $english ? 'Please accept the terms to continue.' : 'Accepteer de voorwaarden om verder te gaan.';
        }

        $this->answer(['success' => false, 'error' => $error, 'message' => $error, 'status' => 422]);
    }

    public function account(): NewAccount
    {
        return new NewAccount(
            name: $this->input('name'),
            email: $this->input('email'),
            password: $this->input('password'),
            language: $this->input('language') ?? 'nl',
            acceptedTerms: $this->input('acceptedTerms') ?? false,
            acceptedTermsVersion: $this->validated('acceptedTermsVersion'),
        );
    }
}

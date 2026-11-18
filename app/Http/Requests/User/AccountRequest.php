<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The base of the account requests (login, registration, password reset, own account). Most of them have no rules:
 * their checks answer in their own shape (HTTP 200 with `success`/`error` for login and registration, a `reason` for
 * the reset page), which the app and the website read, instead of the usual 422 with `errors`.
 */
abstract class AccountRequest extends FormRequest
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

    /**
     * Stops the request with this answer, before the controller runs
     *
     * @param array<string, mixed> $body
     */
    protected function answer(array $body, int $status = 200): never
    {
        throw new HttpResponseException(response()->json($body, $status));
    }
}

<?php

namespace App\Http\Requests\User;

use App\Data\LoginResult;

class LoginRequest extends AccountRequest
{
    // Nothing to check without both fields: answer like any failed login (the app and website read `success`/`error`),
    // but before LoginService, so empty bot requests don't fill the failed-login list
    protected function passedValidation(): void
    {
        $email = $this->input('email');
        $password = $this->input('password');

        if (!is_string($email) || trim($email) === '' || !is_string($password) || $password === '') {
            $this->answer(LoginResult::failed('Email and password are required', 422)->toArray());
        }
    }

    public function email(): string
    {
        return $this->input('email');
    }

    public function password(): string
    {
        return $this->input('password');
    }
}

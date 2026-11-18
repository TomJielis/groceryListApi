<?php

namespace App\Http\Requests\User;

/**
 * A new password from the reset page. Its problems come with a `reason`, so the page can show them at the right field.
 * Whether the link still works is checked by the controller.
 */
class SetNewPasswordRequest extends AccountRequest
{
    protected function passedValidation(): void
    {
        $password = $this->get('password');

        if (!is_string($password) || mb_strlen($password) < RegisterUserRequest::MIN_PASSWORD_LENGTH) {
            $this->answer([
                'reason' => 'password_too_short',
                'message' => 'Je wachtwoord moet minimaal ' . RegisterUserRequest::MIN_PASSWORD_LENGTH . ' tekens hebben.',
            ], 400);
        }
        // Older pages don't send it
        if ($this->has('repeatPassword') && $this->get('repeatPassword') !== $password) {
            $this->answer(['reason' => 'passwords_do_not_match', 'message' => 'De wachtwoorden zijn niet hetzelfde.'], 400);
        }
    }

    // As sent, not validated (never was): an unknown address reads as an invalid link
    public function email(): mixed
    {
        return $this->get('email');
    }

    public function token(): mixed
    {
        return $this->get('token');
    }

    public function password(): string
    {
        return $this->get('password');
    }
}

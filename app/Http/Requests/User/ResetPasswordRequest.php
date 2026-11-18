<?php

namespace App\Http\Requests\User;

class ResetPasswordRequest extends AccountRequest
{
    protected function passedValidation(): void
    {
        if (!$this->email()) {
            $this->answer(['message' => 'Email is verplicht'], 400);
        }
    }

    // As sent, not validated further (never was)
    public function email(): mixed
    {
        return $this->get('email');
    }
}

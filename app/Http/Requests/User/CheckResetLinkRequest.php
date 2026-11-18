<?php

namespace App\Http\Requests\User;

class CheckResetLinkRequest extends AccountRequest
{
    // Null when there's none (or it's not text): such a link is simply not valid
    public function token(): ?string
    {
        $token = $this->get('token');

        return is_string($token) ? $token : null;
    }
}

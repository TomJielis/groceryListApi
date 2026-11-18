<?php

namespace App\Http\Requests\User;

class LogoutRequest extends AccountRequest
{
    // The app's push token for this device, so it stops getting notifications for this account
    public function pushToken(): mixed
    {
        return $this->get('pushToken');
    }
}

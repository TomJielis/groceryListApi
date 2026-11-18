<?php

namespace App\Http\Requests\User;

class UpdateLanguageRequest extends AccountRequest
{
    // As sent, not validated (never was)
    public function language(): mixed
    {
        return $this->get('language');
    }
}

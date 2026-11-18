<?php

namespace App\Http\Requests\User;

class ApproveTermsRequest extends AccountRequest
{
    // Only a version number ("1.4"): anything else would compare oddly, and stay forever as it's never lowered
    protected function passedValidation(): void
    {
        abort_unless(preg_match('/^\d{1,3}(\.\d{1,3}){0,2}$/', $this->termsVersion()) === 1, 422, 'Invalid terms version');
    }

    public function termsVersion(): string
    {
        return (string) $this->get('acceptedTermsVersion');
    }
}

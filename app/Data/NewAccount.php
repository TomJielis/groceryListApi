<?php

namespace App\Data;

/**
 * A registration as the app or the website sends it (RegisterUserRequest). Language and acceptedTerms are taken as
 * sent, like before: they aren't validated.
 */
final readonly class NewAccount
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public mixed $language,
        public mixed $acceptedTerms,
        public string $acceptedTermsVersion,
    ) {
    }
}

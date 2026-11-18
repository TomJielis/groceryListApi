<?php

namespace App\Data;

/**
 * The selection of failed logins on the admin overview: the same for the list and every summary number
 */
final readonly class LoginAttemptFilter
{
    public function __construct(
        // 24h, 7d, 30d or all (anything else: all)
        public ?string $period,
        // known, unknown or null for both
        public ?string $account,
        // Part of the username typed
        public ?string $email,
        public ?string $ip,
    ) {
    }
}

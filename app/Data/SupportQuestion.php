<?php

namespace App\Data;

/**
 * A new support question from the app or the website
 */
final readonly class SupportQuestion
{
    public function __construct(
        public string $subject,
        public string $message,
        // nl or en; null: the user's own language
        public ?string $language,
        // app or website
        public string $via,
    ) {
    }
}

<?php

namespace App\Data;

use App\Models\User;

/**
 * How a login went: the user with a new token, or why not. A failed login is answered with HTTP 200 and
 * `success: false` (the real status in `status`), which the app and the website read.
 */
final readonly class LoginResult
{
    private function __construct(
        public ?User $user,
        public ?string $token,
        public ?string $error,
        public ?int $status,
    ) {
    }

    public static function succeeded(User $user, string $token): self
    {
        return new self($user, $token, null, null);
    }

    public static function failed(string $error, int $status): self
    {
        return new self(null, null, $error, $status);
    }

    public function failedToLogIn(): bool
    {
        return $this->user === null;
    }

    /**
     * The answer for a failed login
     *
     * @return array{error: string, success: false, status: int}
     */
    public function toArray(): array
    {
        return ['error' => $this->error, 'success' => false, 'status' => $this->status];
    }
}

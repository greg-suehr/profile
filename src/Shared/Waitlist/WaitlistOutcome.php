<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

use App\Katzen\Entity\KatzenWaitlist;

final class WaitlistOutcome
{
    private function __construct(
        public readonly ?string $error,
        public readonly int $httpStatus,
        public readonly ?KatzenWaitlist $entry,
    ) {
    }

    public static function saved(KatzenWaitlist $entry): self
    {
        return new self(null, 200, $entry);
    }

    public static function dropped(): self
    {
        return new self(null, 200, null);
    }

    public static function error(int $httpStatus, string $message): self
    {
        return new self($message, $httpStatus, null);
    }

    public function looksSuccessful(): bool
    {
        return null === $this->error;
    }
}

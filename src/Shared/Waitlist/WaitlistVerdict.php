<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

final class WaitlistVerdict
{
    private function __construct(
        public readonly bool $passed,
        public readonly bool $silent,
        public readonly ?string $reason,
        public readonly ?string $email,
    ) {
    }

    public static function pass(?string $email): self
    {
        return new self(true, false, null, $email);
    }

    public static function silent(string $reason, ?string $email = null): self
    {
        return new self(false, true, $reason, $email);
    }

    public static function expired(): self
    {
        return new self(false, false, 'expired', null);
    }
}

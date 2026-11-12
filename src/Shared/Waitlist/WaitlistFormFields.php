<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

final class WaitlistFormFields
{
    public function __construct(
        public readonly string $emailField,
        public readonly string $canaryField,
        public readonly string $canaryValue,
        public readonly string $trapField,
        public readonly string $decoyClass,
        public readonly bool $canaryFirst,
    ) {
    }
}

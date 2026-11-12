<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Waitlist\TurnstileVerifier;
use App\Shared\Waitlist\WaitlistGuard;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WaitlistGuardExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly WaitlistGuard $guard,
        private readonly TurnstileVerifier $turnstile,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('waitlist_guard', $this->waitlistGuard(...))];
    }

    /** @return array<string, string|bool> */
    public function waitlistGuard(): array
    {
        $fields = $this->guard->issue($this->requestStack->getSession());

        return [
            'email_field' => $fields->emailField,
            'canary_field' => $fields->canaryField,
            'canary_value' => $fields->canaryValue,
            'trap_field' => $fields->trapField,
            'decoy_class' => $fields->decoyClass,
            'canary_first' => $fields->canaryFirst,
            'turnstile_site_key' => $this->turnstile->siteKey(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class WaitlistGuard
{
    public const MIN_SECONDS_TO_SUBMIT = 3;
    public const MAX_FORM_AGE_SECONDS = 12 * 3600;

    private const SESSION_KEY = '_waitlist_guard_seed';
    private const PHANTOM_FIELD = 'email';

    private const EMAIL_NAMES = ['inbox', 'reach_me', 'contact', 'mailbox', 'notify_at', 'updates_to'];
    private const CANARY_NAMES = ['visit_ref', 'origin', 'ticket', 'campaign_ref', 'session_tag', 'entry'];
    private const TRAP_NAMES = ['website', 'homepage', 'company_site', 'portfolio', 'url', 'blog'];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function issue(SessionInterface $session): WaitlistFormFields
    {
        $seed = $session->get(self::SESSION_KEY);
        if (!\is_string($seed) || '' === $seed) {
            $seed = bin2hex(random_bytes(16));
            $session->set(self::SESSION_KEY, $seed);
        }

        return $this->fields($seed, $this->now());
    }

    public function inspect(Request $request): WaitlistVerdict
    {
        $seed = $request->hasSession() ? $request->getSession()->get(self::SESSION_KEY) : null;
        if (!\is_string($seed) || '' === $seed) {
            return WaitlistVerdict::silent('no_seed');
        }

        $input = $request->request;
        $fields = $this->fields($seed, $this->now());

        if ($input->has(self::PHANTOM_FIELD)) {
            return WaitlistVerdict::silent('phantom_field');
        }

        if ('' !== (self::scalar($input, $fields->trapField) ?? 'missing')) {
            return WaitlistVerdict::silent('trap_disturbed');
        }

        $renderedAt = $this->renderedAt($seed, self::scalar($input, $fields->canaryField));
        if (null === $renderedAt) {
            return WaitlistVerdict::silent('canary_tampered');
        }

        $age = $this->now() - $renderedAt;
        if ($age < self::MIN_SECONDS_TO_SUBMIT) {
            return WaitlistVerdict::silent('too_fast');
        }
        if ($age > self::MAX_FORM_AGE_SECONDS) {
            return WaitlistVerdict::expired();
        }

        return WaitlistVerdict::pass(self::scalar($input, $fields->emailField));
    }

    private function fields(string $seed, int $renderedAt): WaitlistFormFields
    {
        $digest = hash('sha256', $seed);
        $pick = static fn (array $names, int $offset): string => $names[hexdec(substr($digest, $offset, 2)) % \count($names)];

        return new WaitlistFormFields(
            emailField: $pick(self::EMAIL_NAMES, 0),
            canaryField: $pick(self::CANARY_NAMES, 2),
            canaryValue: $this->stamp($seed, $renderedAt),
            trapField: $pick(self::TRAP_NAMES, 4),
            decoyClass: 'f'.substr($digest, 6, 7),
            canaryFirst: 0 === hexdec($digest[13]) % 2,
        );
    }

    private function stamp(string $seed, int $time): string
    {
        $t = base_convert((string) $time, 10, 36);

        return $t.'-'.substr(hash_hmac('sha256', $t, $seed), 0, 12);
    }

    private function renderedAt(string $seed, ?string $stamp): ?int
    {
        if (null === $stamp || 1 !== preg_match('/^([0-9a-z]{1,10})-([0-9a-f]{12})$/', $stamp, $m)) {
            return null;
        }

        $expected = substr(hash_hmac('sha256', $m[1], $seed), 0, 12);
        if (!hash_equals($expected, $m[2])) {
            return null;
        }

        return (int) base_convert($m[1], 36, 10);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private static function scalar(InputBag $input, string $name): ?string
    {
        $value = $input->all()[$name] ?? null;

        return \is_string($value) ? $value : null;
    }
}

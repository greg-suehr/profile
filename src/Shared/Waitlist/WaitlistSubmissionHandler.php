<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

use App\Katzen\Entity\KatzenWaitlist;
use App\Shared\Entity\WaitlistRejection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class WaitlistSubmissionHandler
{
    public const SOURCE_GETKATZEN = 'getkatzen';
    public const SOURCE_GREGISHERE = 'gregishere';

    public const CSRF_ID = 'waitlist';
    public const TURNSTILE_ACTION = 'waitlist';

    public function __construct(
        private readonly WaitlistGuard $guard,
        private readonly TurnstileVerifier $turnstile,
        private readonly RateLimiterFactory $waitlistBurstLimiter,
        private readonly RateLimiterFactory $waitlistDailyLimiter,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(Request $request, string $source): WaitlistOutcome
    {
        $ip = $request->getClientIp() ?? 'unknown';

        foreach ([$this->waitlistBurstLimiter, $this->waitlistDailyLimiter] as $limiter) {
            if (!$limiter->create($ip)->consume()->isAccepted()) {
                return $this->reject($request, $source, 'rate_limited', WaitlistOutcome::error(429, 'Too many attempts. Please try again later.'));
            }
        }

        $csrf = $request->request->all()['_token'] ?? null;
        if (!\is_string($csrf) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ID, $csrf))) {
            return $this->reject($request, $source, 'csrf', WaitlistOutcome::error(400, 'Invalid security token. Please refresh and try again.'));
        }

        $verdict = $this->guard->inspect($request);
        if ($verdict->silent) {
            $turnstilePassed = $this->turnstilePassed($request);

            return $this->reject($request, $source, (string) $verdict->reason, WaitlistOutcome::dropped(), $verdict->email, $turnstilePassed);
        }
        if (!$verdict->passed) {
            return $this->reject($request, $source, 'expired', WaitlistOutcome::error(400, 'This form has expired. Please refresh the page and try again.'));
        }

        $email = trim((string) $verdict->email);
        if ('' === $email || \strlen($email) > 255 || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->reject($request, $source, 'invalid_email', WaitlistOutcome::error(400, 'Please enter a valid email address.'), $email);
        }

        if (!$this->turnstilePassed($request)) {
            return $this->reject($request, $source, 'turnstile', WaitlistOutcome::error(400, "We couldn't verify you're human. Please try again."), $email, false);
        }

        $entry = (new KatzenWaitlist())
            ->setEmail($email)
            ->setSource($source)
            ->setStatus('pending')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setSessionId($request->getSession()->getId())
            ->setReferralSource(self::clip($request, '_ref', 100))
            ->setUtmCampaign(self::clip($request, '_utm', 100));

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return WaitlistOutcome::saved($entry);
    }

    private function reject(Request $request, string $source, string $reason, WaitlistOutcome $outcome, ?string $email = null, ?bool $turnstilePassed = null): WaitlistOutcome
    {
        $email = null === $email ? null : trim((string) preg_replace('/\p{C}+/u', '', $email));
        $at = null === $email ? false : strrpos($email, '@');
        $domain = false === $at ? null : strtolower(substr($email, $at + 1));
        $country = strtoupper(trim((string) $request->headers->get('CF-IPCountry')));
        $userAgent = trim((string) preg_replace('/\p{C}+/u', '', (string) $request->headers->get('User-Agent')));

        $this->entityManager->persist(new WaitlistRejection(
            source: $source,
            reason: $reason,
            emailDomain: '' === (string) $domain ? null : mb_substr($domain, 0, 255),
            country: 1 === preg_match('/^[A-Z0-9]{2}$/', $country) ? $country : null,
            userAgent: '' === $userAgent ? null : mb_substr($userAgent, 0, 255),
            turnstilePassed: $turnstilePassed,
            email: true === $turnstilePassed && null !== $email && '' !== $email ? mb_substr($email, 0, 255) : null,
        ));
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE waitlist_rejection SET email = NULL WHERE email IS NOT NULL AND created_at < NOW() - INTERVAL '30 days'"
        );

        return $outcome;
    }

    private function turnstilePassed(Request $request): bool
    {
        $token = $request->request->all()[TurnstileVerifier::TOKEN_FIELD] ?? null;

        return $this->turnstile->verify(\is_string($token) ? $token : null, $request->getClientIp(), self::TURNSTILE_ACTION, $request->getHost());
    }

    private static function clip(Request $request, string $name, int $max): ?string
    {
        $value = $request->request->all()[$name] ?? null;
        if (!\is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\p{C}+/u', '', $value));

        return '' === $value ? null : mb_substr($value, 0, $max);
    }
}

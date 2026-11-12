<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

use App\Katzen\Entity\KatzenWaitlist;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request, string $source): WaitlistOutcome
    {
        $ip = $request->getClientIp() ?? 'unknown';
        $log = ['source' => $source, 'ip' => $ip];

        foreach ([$this->waitlistBurstLimiter, $this->waitlistDailyLimiter] as $limiter) {
            if (!$limiter->create($ip)->consume()->isAccepted()) {
                $this->logger->notice('Waitlist submission rate-limited', $log);

                return WaitlistOutcome::error(429, 'Too many attempts. Please try again later.');
            }
        }

        $csrf = $request->request->all()['_token'] ?? null;
        if (!\is_string($csrf) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ID, $csrf))) {
            return WaitlistOutcome::error(400, 'Invalid security token. Please refresh and try again.');
        }

        $verdict = $this->guard->inspect($request);
        if ($verdict->silent) {
            $this->logger->notice('Waitlist submission dropped by bot trap', $log + ['reason' => $verdict->reason]);

            return WaitlistOutcome::dropped();
        }
        if (!$verdict->passed) {
            return WaitlistOutcome::error(400, 'This form has expired. Please refresh the page and try again.');
        }

        $email = trim((string) $verdict->email);
        if ('' === $email || \strlen($email) > 255 || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return WaitlistOutcome::error(400, 'Please enter a valid email address.');
        }

        $token = $request->request->all()[TurnstileVerifier::TOKEN_FIELD] ?? null;
        if (!$this->turnstile->verify(\is_string($token) ? $token : null, $request->getClientIp(), self::TURNSTILE_ACTION, $request->getHost())) {
            $this->logger->notice('Waitlist submission failed Turnstile', $log);

            return WaitlistOutcome::error(400, "We couldn't verify you're human. Please try again.");
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

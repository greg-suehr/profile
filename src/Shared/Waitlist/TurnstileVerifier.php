<?php

declare(strict_types=1);

namespace App\Shared\Waitlist;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TurnstileVerifier
{
    public const TOKEN_FIELD = 'cf-turnstile-response';

    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const MAX_TOKEN_LENGTH = 2048;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'TURNSTILE_SITE_KEY')]
        private readonly string $siteKey,
        #[Autowire(env: 'TURNSTILE_SECRET_KEY')]
        private readonly string $secretKey,
    ) {
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function verify(?string $token, ?string $remoteIp, string $expectedAction, string $expectedHostname): bool
    {
        if (null === $token || '' === $token || \strlen($token) > self::MAX_TOKEN_LENGTH) {
            return false;
        }

        try {
            $result = $this->httpClient->request('POST', self::ENDPOINT, [
                'body' => array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]),
                'timeout' => 5,
                'max_duration' => 8,
            ])->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Turnstile siteverify unreachable', ['exception' => $e]);

            return false;
        }

        if (true !== ($result['success'] ?? false)) {
            $this->logger->info('Turnstile rejected token', ['error_codes' => $result['error-codes'] ?? []]);

            return false;
        }

        if ($this->usesTestSecret()) {
            return true;
        }

        if (($result['action'] ?? null) !== $expectedAction || ($result['hostname'] ?? null) !== $expectedHostname) {
            $this->logger->warning('Turnstile token minted for a different form or host', [
                'action' => $result['action'] ?? null,
                'hostname' => $result['hostname'] ?? null,
                'expected_action' => $expectedAction,
                'expected_hostname' => $expectedHostname,
            ]);

            return false;
        }

        return true;
    }

    private function usesTestSecret(): bool
    {
        return 1 === preg_match('/^[123]x0{20,}AA$/', $this->secretKey);
    }
}

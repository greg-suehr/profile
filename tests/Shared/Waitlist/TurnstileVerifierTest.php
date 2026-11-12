<?php

declare(strict_types=1);

namespace App\Tests\Shared\Waitlist;

use App\Shared\Waitlist\TurnstileVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TurnstileVerifierTest extends TestCase
{
    private const REAL_SECRET = '0x4AAAAAAAreal-secret';
    private const TEST_SECRET = '1x0000000000000000000000000000000AA';

    public function testGenuineTokenForThisFormAndHostIsAccepted(): void
    {
        $sent = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent = $options['body'];

            return self::json(['success' => true, 'action' => 'waitlist', 'hostname' => 'getkatzen.com']);
        });

        self::assertTrue($this->verifier($client)->verify('tok', '203.0.113.9', 'waitlist', 'getkatzen.com'));
        parse_str($sent, $body);
        self::assertSame(['secret' => self::REAL_SECRET, 'response' => 'tok', 'remoteip' => '203.0.113.9'], $body);
    }

    /**
     * @dataProvider mismatches
     */
    public function testTokenMintedElsewhereIsRejected(array $answer): void
    {
        $client = new MockHttpClient(self::json($answer));

        self::assertFalse($this->verifier($client)->verify('tok', null, 'waitlist', 'getkatzen.com'));
    }

    public static function mismatches(): iterable
    {
        yield 'Cloudflare says no' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']]];
        yield 'another form on the site' => [['success' => true, 'action' => 'login', 'hostname' => 'getkatzen.com']];
        yield 'another site using the same key' => [['success' => true, 'action' => 'waitlist', 'hostname' => 'gregishere.com']];
    }

    public function testCloudflareTestSecretSkipsActionAndHostChecks(): void
    {
        // Test secrets always answer hostname "example.com" with no action.
        $client = new MockHttpClient(self::json(['success' => true, 'hostname' => 'example.com']));

        self::assertTrue($this->verifier($client, self::TEST_SECRET)->verify('XXXX.DUMMY.TOKEN.XXXX', null, 'waitlist', 'getkatzen.local'));
    }

    public function testMissingOrOversizedTokenIsRejectedWithoutCallingCloudflare(): void
    {
        $client = new MockHttpClient(function () {
            self::fail('siteverify must not be called');
        });
        $verifier = $this->verifier($client);

        self::assertFalse($verifier->verify(null, null, 'waitlist', 'getkatzen.com'));
        self::assertFalse($verifier->verify('', null, 'waitlist', 'getkatzen.com'));
        self::assertFalse($verifier->verify(str_repeat('a', 2049), null, 'waitlist', 'getkatzen.com'));
    }

    public function testCloudflareOutageFailsClosed(): void
    {
        $client = new MockHttpClient(static fn () => throw new TransportException('connection refused'));

        self::assertFalse($this->verifier($client)->verify('tok', null, 'waitlist', 'getkatzen.com'));
    }

    private function verifier(MockHttpClient $client, string $secret = self::REAL_SECRET): TurnstileVerifier
    {
        return new TurnstileVerifier($client, new NullLogger(), 'site-key', $secret);
    }

    private static function json(array $data): MockResponse
    {
        return new MockResponse(json_encode($data), ['response_headers' => ['content-type' => 'application/json']]);
    }
}

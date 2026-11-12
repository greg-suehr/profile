<?php

declare(strict_types=1);

namespace App\Tests\Shared\Waitlist;

use App\Shared\Waitlist\WaitlistFormFields;
use App\Shared\Waitlist\WaitlistGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class WaitlistGuardTest extends TestCase
{
    private MockClock $clock;
    private WaitlistGuard $guard;
    private Session $session;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-12 12:00:00');
        $this->guard = new WaitlistGuard($this->clock);
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testSubmissionShapedLikeTheRenderedFormPasses(): void
    {
        $fields = $this->guard->issue($this->session);
        $this->clock->sleep(8);

        $verdict = $this->guard->inspect($this->post($this->browserPayload($fields, 'cat@example.com')));

        self::assertTrue($verdict->passed);
        self::assertSame('cat@example.com', $verdict->email);
    }

    /**
     * @dataProvider botPayloads
     */
    public function testBotShapedSubmissionsAreDroppedSilently(callable $mutate, string $reason): void
    {
        $fields = $this->guard->issue($this->session);
        $this->clock->sleep(8);

        $payload = $mutate($this->browserPayload($fields, 'cat@example.com'), $fields);
        $verdict = $this->guard->inspect($this->post($payload));

        self::assertFalse($verdict->passed);
        self::assertTrue($verdict->silent, 'bot rejections must look like success to the client');
        self::assertSame($reason, $verdict->reason);
    }

    public static function botPayloads(): iterable
    {
        yield 'posts the legacy "email" field' => [
            static fn (array $p) => $p + ['email' => 'victim@example.com'],
            'phantom_field',
        ];
        yield 'fills every text input' => [
            static fn (array $p, WaitlistFormFields $f) => [$f->trapField => 'https://spam.example'] + $p,
            'trap_disturbed',
        ];
        yield 'drops inputs it thinks are hidden' => [
            static function (array $p, WaitlistFormFields $f) {
                unset($p[$f->trapField], $p[$f->canaryField]);

                return $p;
            },
            'trap_disturbed',
        ];
        yield 'overwrites the pre-filled canary' => [
            static fn (array $p, WaitlistFormFields $f) => [$f->canaryField => 'hello'] + $p,
            'canary_tampered',
        ];
        yield 'forges a fresher timestamp' => [
            static function (array $p, WaitlistFormFields $f) {
                [, $sig] = explode('-', $f->canaryValue);
                $p[$f->canaryField] = base_convert('1762948800', 10, 36).'-'.$sig;

                return $p;
            },
            'canary_tampered',
        ];
        yield 'sends a field as an array' => [
            static fn (array $p, WaitlistFormFields $f) => [$f->trapField => ['x']] + $p,
            'trap_disturbed',
        ];
    }

    public function testSubmittingWithinSecondsOfRenderIsDroppedSilently(): void
    {
        $fields = $this->guard->issue($this->session);
        $this->clock->sleep(1);

        $verdict = $this->guard->inspect($this->post($this->browserPayload($fields, 'cat@example.com')));

        self::assertTrue($verdict->silent);
        self::assertSame('too_fast', $verdict->reason);
    }

    public function testCanaryFromAnotherSessionIsRejected(): void
    {
        $otherSession = new Session(new MockArraySessionStorage());
        $foreign = $this->guard->issue($otherSession);
        $this->guard->issue($this->session);
        $this->clock->sleep(8);

        $verdict = $this->guard->inspect($this->post($this->browserPayload($foreign, 'cat@example.com')));

        self::assertFalse($verdict->passed);
        self::assertTrue($verdict->silent);
    }

    public function testFormLeftOpenOvernightGetsAVisibleExpiry(): void
    {
        $fields = $this->guard->issue($this->session);
        $this->clock->sleep(WaitlistGuard::MAX_FORM_AGE_SECONDS + 1);

        $verdict = $this->guard->inspect($this->post($this->browserPayload($fields, 'cat@example.com')));

        self::assertFalse($verdict->passed);
        self::assertFalse($verdict->silent, 'a real person can hit this, so they must be told');
    }

    public function testEachTabGetsItsOwnRenderTimeButTheSameFieldNames(): void
    {
        $tabA = $this->guard->issue($this->session);
        $this->clock->sleep(30);
        $tabB = $this->guard->issue($this->session);
        $this->clock->sleep(1);

        self::assertSame($tabA->emailField, $tabB->emailField);
        self::assertNotSame($tabA->canaryValue, $tabB->canaryValue);
        self::assertTrue($this->guard->inspect($this->post($this->browserPayload($tabA, 'a@example.com')))->passed);
    }

    public function testRequestWithoutAGuardSeedIsDroppedSilently(): void
    {
        $verdict = $this->guard->inspect($this->post(['inbox' => 'cat@example.com']));

        self::assertTrue($verdict->silent);
        self::assertSame('no_seed', $verdict->reason);
    }

    private function browserPayload(WaitlistFormFields $fields, string $email): array
    {
        return [
            $fields->emailField => $email,
            $fields->canaryField => $fields->canaryValue,
            $fields->trapField => '',
            '_token' => 'csrf',
        ];
    }

    private function post(array $payload): Request
    {
        $request = Request::create('/waitlist/submit', 'POST', $payload);
        $request->setSession($this->session);

        return $request;
    }
}

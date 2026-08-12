<?php

declare(strict_types=1);

namespace App\Shared\Service\SeerTechne;

final class PuzzleResult
{
  private function __construct(
    public readonly bool $ok,
    public readonly int $step,
    public readonly bool $done = false,
    public readonly ?string $reward = null,
    public readonly bool $locked = false,
    public readonly int $retryAfter = 0,
  ) {}

  public static function advanced(int $step): self
  {
    return new self(true, $step);
  }

  public static function completed(int $step, string $reward): self
  {
    return new self(true, $step, true, $reward);
  }

  public static function wrong(): self
  {
    return new self(false, 0);
  }

  public static function lockedOut(int $retryAfter): self
  {
    return new self(false, 0, false, null, true, $retryAfter);
  }

  /** @return array<string,mixed> */
  public function toArray(): array
  {
    if ($this->locked) {
      return ['ok' => false, 'step' => 0, 'locked' => true, 'retryAfter' => $this->retryAfter];
    }
    $payload = ['ok' => $this->ok, 'step' => $this->step];
    if ($this->done) {
      $payload['done'] = true;
      $payload['reward'] = $this->reward;
    }
    return $payload;
  }
}

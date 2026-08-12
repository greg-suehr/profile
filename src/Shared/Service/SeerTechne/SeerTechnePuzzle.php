<?php

declare(strict_types=1);

namespace App\Shared\Service\SeerTechne;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SeerTechnePuzzle
{
  /** @var int[] */
  private readonly array $sequence;

  /**
   * @param string $order  comma-separated marker indices, in solve order
   * @param string $reward what the solver is given on completion
   */
  public function __construct(
    string $order,
    private readonly string $reward,
  ) {
    $parsed = array_values(array_filter(
      array_map(static fn (string $n): string => trim($n), explode(',', $order)),
      static fn (string $n): bool => $n !== '',
    ));
    $this->sequence = array_map(static fn (string $n): int => (int) $n, $parsed);
  }

  public function length(): int
  {
    return count($this->sequence);
  }

  public function press(SessionInterface $session, int $marker): PuzzleResult
  {
    if ($session->get(PuzzlePolicy::KEY_SOLVED, false)) {
      return PuzzleResult::completed($this->length(), $this->reward);
    }

    if (($retryAfter = $this->lockRemaining($session)) > 0) {
      return PuzzleResult::lockedOut($retryAfter);
    }

    $this->recordAttempt($session);

    $step = (int) $session->get(PuzzlePolicy::KEY_STEP, 0);

    if (!isset($this->sequence[$step]) || $this->sequence[$step] !== $marker) {
      $session->set(PuzzlePolicy::KEY_STEP, 0);
      return PuzzleResult::wrong();
    }

    $step++;
    $session->set(PuzzlePolicy::KEY_STEP, $step);

    if ($step >= $this->length()) {
      $session->set(PuzzlePolicy::KEY_SOLVED, true);
      return PuzzleResult::completed($step, $this->reward);
    }

    return PuzzleResult::advanced($step);
  }

  public function reset(SessionInterface $session): void
  {
    $session->remove(PuzzlePolicy::KEY_STEP);
  }

  private function lockRemaining(SessionInterface $session): int
  {
    $until = (int) $session->get(PuzzlePolicy::KEY_LOCKED, 0);
    return $until > time() ? $until - time() : 0;
  }

  private function recordAttempt(SessionInterface $session): void
  {
    $now = (int) (microtime(true) * 1000);
    $last = (int) $session->get(PuzzlePolicy::KEY_LAST, 0);
    $attempts = (int) $session->get(PuzzlePolicy::KEY_ATTEMPTS, 0) + 1;

    $session->set(PuzzlePolicy::KEY_LAST, $now);
    $session->set(PuzzlePolicy::KEY_ATTEMPTS, $attempts);

    $streak = ($last > 0 && ($now - $last) < PuzzlePolicy::MIN_INTERVAL_MS)
      ? (int) $session->get(PuzzlePolicy::KEY_STREAK, 0) + 1
      : 0;
    $session->set(PuzzlePolicy::KEY_STREAK, $streak);

    if ($attempts > PuzzlePolicy::MAX_ATTEMPTS || $streak >= PuzzlePolicy::FAST_STREAK_LIMIT) {
      $session->set(PuzzlePolicy::KEY_LOCKED, time() + PuzzlePolicy::LOCKOUT_SECONDS);
    }
  }
}

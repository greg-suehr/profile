<?php

declare(strict_types=1);

namespace App\Shared\Service\SeerTechne;

final class PuzzlePolicy
{
  public const MIN_INTERVAL_MS = 220;

  public const MAX_ATTEMPTS = 240;

  public const LOCKOUT_SECONDS = 900;

  public const FAST_STREAK_LIMIT = 5;

  public const KEY_STEP     = 'st.puzzle.step';
  public const KEY_ATTEMPTS = 'st.puzzle.attempts';
  public const KEY_LAST     = 'st.puzzle.last';
  public const KEY_LOCKED   = 'st.puzzle.locked_until';
  public const KEY_STREAK   = 'st.puzzle.fast_streak';
  public const KEY_SOLVED   = 'st.puzzle.solved';
}

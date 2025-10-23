<?php

namespace App\Katzen\Service\Utility;

/**
 * Katzen Encouragement Engine
 * 
 * A contextual messaging system for the Katzen ERP.
 * 
 * The engine provides dynamic, data-aware encouragement based on:
 *   - Route/Data Context
 *   - Mood Context (energetic, gentle, reflective, determined)
 *   - Environmental cues (time, day, operational data)
 * 
 * Messages are selected from a lightweight cards model, with cooldown tracking
 * to prevent repetition and token replacement for contextual data.
 */
final class EncouragementEngine
{
  /**
   * Message library keyed by [mascot][route][mood][]
   * 
   * @var array<string, array<string, array<string, array<string>>>>
   */
  private array $library = [];

  /**
   * Recently shown messages to avoid repetition
   * 
   * @var array<string>
   */
  private array $recentMessages = [];

  /**
   * Cooldown window for message reuse (number of messages)
   * 
   * @var int
   */
  private int $cooldownWindow = 10;

  /**
   * Available moods
   * 
   * @var array<string>
   */
  private const MOODS = [
    'energetic',   // Monday mornings, high activity
    'gentle',      // Afternoon, steady work
    'reflective',  // Fridays, end of week
    'determined',  // Crunch time, challenges
    'playful',     // Light moments
    'focused',     // Deep work periods
    'calm',        // Late hours, quiet times
  ];
  
  /**
   * Application mascots and their domains
   * 
   * @var array<string, array{domain: string, tone: string, routes: array<string>}>
   */
  private const MASCOTS = [
    'sizzle' => [
      'domain' => 'kitchen/production',
      'tone' => 'confident and energetic',
      'routes' => ['recipe_', 'menu_', 'production_', 'cook_'],
    ],
    'tabby' => [
      'domain' => 'orders and service',
      'tone' => 'friendly and upbeat',
      'routes' => ['order_', 'customer_', 'service_'],
    ],
    'binx' => [
      'domain' => 'inventory and purchasing',
      'tone' => 'methodical and soothing',
      'routes' => ['stock_', 'item_', 'inventory_', 'purchase_'],
    ],
    'ledger' => [
      'domain' => 'accounting and finance',
      'tone' => 'precise and calm',
      'routes' => ['invoice_', 'account_', 'ledger_', 'payment_', 'reports_'],
    ],
  ];

  private ?YamlMessageLoader $loader = null;

  public function __construct(
    private string $projectDir,
  )
  {
#    if ($yamlPath !== null) {
#      $this->loader = new YamlMessageLoader($yamlPath);
#    }
    $this->loader = new YamlMessageLoader($this->projectDir . '/config/encouragement.yaml');

    $this->initializeLibrary();
  }

  /**
   * Generate an encouragement message with full context
   * 
   * @param array{
   *   route: string,
   *   time?: \DateTimeInterface,
   *   day?: \DateTimeInterface,
   *   open_orders?: int,
   *   orders_completed_today?: int,
   *   low_stock_items?: int,
   *   stock_variance?: float,
   *   user_name?: string,
   *   is_first_login_today?: bool,
   *   recent_errors?: int
   * } $context
   * @return array{
   *   header_message: string,
   *   mascot: string,
   *   mood: string,
   *   animation?: string
   * }
   */
  public function generate(array $context): array
  {
    $route = $context['route'] ?? 'dashboard_home';
    $time = $context['time'] ?? new \DateTime();
    $day = $context['day'] ?? new \DateTime();
    
    $mascot = $this->getMascotForRoute($route);
        
    $mood = $this->calculateMood($time, $day, $context);
        
    $message = $this->selectMessage($mascot, $route, $mood);
        
    $message = $this->replaceTokens($message, $context);
        
    return [
      'header_message' => $message,
      'mascot' => $mascot,
      'mood' => $mood,
      'animation' => null,
    ];
  }

  /**
   * Determine which mascot should speak based on the current route
   * 
   * @param string $route
   * @return string
   */
  private function getMascotForRoute(string $route): string
  {
    foreach (self::MASCOTS as $mascot => $config) {
      foreach ($config['routes'] as $routePrefix) {
        if (str_starts_with($route, $routePrefix)) {
          return $mascot;
        }
      }
    }
    // default to tabby
    return 'tabby';
  }

  /**
   * Calculate mood based on environmental cues
   * 
   * @param \DateTimeInterface $time
   * @param \DateTimeInterface $day
   * @param array $context
   * @return string
   */
  private function calculateMood(
    \DateTimeInterface $time,
    \DateTimeInterface $day,
    array $context
  ): string {
    $hour = (int) $time->format('H');
    $dayOfWeek = (int) $day->format('N'); // 1 = Monday, 7 = Sunday
        
    // Check for stress indicators
    if (($context['open_orders'] ?? 0) > 10 || ($context['recent_errors'] ?? 0) > 0) {
      return 'determined';
    }
    
    // Check for positive indicators
    if (($context['orders_completed_today'] ?? 0) > 5) {
      return 'playful';
    }
    
    // First login of the day
    if ($context['is_first_login_today'] ?? false) {
      return $dayOfWeek === 1 ? 'energetic' : 'gentle';
    }
        
    // Time-based moods
    return match (true) {
      // Monday morning - start strong
      $dayOfWeek === 1 && $hour >= 6 && $hour < 12 => 'energetic',
      
      // Friday - wind down
      $dayOfWeek === 5 && $hour >= 14 => 'reflective',
      
      // Early morning (any day)
      $hour >= 5 && $hour < 9 => 'focused',
      
      // Mid-day steady work
      $hour >= 12 && $hour < 17 => 'gentle',
      
      // Evening
      $hour >= 17 && $hour < 21 => 'calm',
      
      // Late night
      $hour >= 21 || $hour < 5 => 'determined',
      
      // Default
      default => 'focused',
    };
  }

  /**
   * Select a message from the library, avoiding recent repetitions
   * 
   * @param string $mascot
   * @param string $route
   * @param string $mood
   * @return string
   */
  private function selectMessage(string $mascot, string $route, string $mood): string
  {
    // Try to get messages for specific route
    $messages = $this->library[$mascot][$route][$mood] 
    ?? $this->library[$mascot]['default'][$mood]
    ?? $this->library[$mascot]['default']['gentle']
    ?? ['You got this!'];
        
        // Filter out recently shown messages
        $available = array_filter(
            $messages,
            fn($msg) => !in_array($msg, $this->recentMessages, true)
        );
        
        // If all messages have been shown recently, reset and use full set
        if (empty($available)) {
            $this->recentMessages = [];
            $available = $messages;
        }
        
        // Select random message
        $selected = $available[array_rand($available)];
        
        // Add to recent messages and trim to cooldown window
        $this->recentMessages[] = $selected;
        if (count($this->recentMessages) > $this->cooldownWindow) {
            array_shift($this->recentMessages);
        }
        
        return $selected;
    }

    /**
     * Replace tokens in message with contextual data
     * 
     * @param string $message
     * @param array $context
     * @return string
     */
    private function replaceTokens(string $message, array $context): string
    {
        $tokens = [
            '{{user_name}}' => $context['user_name'] ?? 'friend',
            '{{orders_completed_today}}' => (string) ($context['orders_completed_today'] ?? 0),
            '{{open_orders}}' => (string) ($context['open_orders'] ?? 0),
            '{{low_stock_items}}' => (string) ($context['low_stock_items'] ?? 0),
            '{{stock_variance}}' => number_format($context['stock_variance'] ?? 0, 1) . '%',
        ];
        
        return str_replace(
            array_keys($tokens),
            array_values($tokens),
            $message
        );
    }

    /**
     * Determine optional animation hint
     * 
     * @param string $mascot
     * @param string $mood
     * @param array $context
     * @return string|null
     */
    private function getAnimation(string $mascot, string $mood, array $context): ?string
    {
        // Celebrate completions
        if (($context['orders_completed_today'] ?? 0) > 5) {
            return 'tail-flick';
        }
        
        // Gentle reassurance for challenges
        if (($context['recent_errors'] ?? 0) > 0) {
            return 'blink';
        }
        
        // Energetic moods get more animation
        if ($mood === 'energetic' && rand(1, 3) === 1) {
            return 'ear-twitch';
        }
        
        // Otherwise, subtle or no animation
        return null;
    }

    /**
     * Initialize the message library with House Personality voices
     * 
     * This is the "cards" model - lightweight, editor-friendly structure
     * where writers can add plain text lines per mascot and route
     */
    private function initializeLibrary(): void
    {
      if ($this->loader === null) {
        return;
      }

      $this->library = $this->loader->load();
      foreach ($this->library as $mascot => &$routes) {
        foreach ($routes as $route => &$moods) {
          foreach (self::MOODS as $mood) {
            if (!isset($moods[$mood])) {
              $moods[$mood] = $moods['gentle'] ?? ['You got this!'];
            }
          }
        }
      }
    }

  /**
   * Get mascot information
   * 
   * @param string $mascot
   * @return array{domain: string, tone: string, routes: array<string>}|null
   */
  public function getMascotInfo(string $mascot): ?array
  {
    return self::MASCOTS[$mascot] ?? null;
  }
  
  /**
   * Get all available mascots
   * 
   * @return array<string>
   */
  public function getAvailableMascots(): array
  {
    return array_keys(self::MASCOTS);
  }
  
  /**
   * Get all available moods
   * 
   * @return array<string>
   */
  public function getAvailableMoods(): array
  {
    return self::MOODS;
  }
}

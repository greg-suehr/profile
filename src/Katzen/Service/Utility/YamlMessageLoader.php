<?php

namespace App\Katzen\Service\Utility;

use Symfony\Component\Yaml\Yaml;

/**
 * YAML Message Library Loader
 * 
 * Usage:
 *   $loader = new YamlMessageLoader(__DIR__ . '/../../config/encouragement_messages.yaml');
 *   $library = $loader->load();
 *   
 * Then pass $library to EncouragementEngine constructor (requires modification)
 */
final class YamlMessageLoader
{
  public function __construct(private string $yamlPath)
  {
    if (!file_exists($yamlPath)) {
      throw new \RuntimeException("Message library file not found: {$yamlPath}");
    }
  }

  /**
   * Load message library from YAML file
   * 
   * @return array<string, array<string, array<string, array<string>>>>
   */
  public function load(): array
  {
    $data = Yaml::parseFile($this->yamlPath);
        
    $this->validate($data);
        
    $library = [];
    foreach ($data as $mascot => $routes) {
      if ($mascot === 'animations') {
        continue; // TODO: mascot animations
      }
      
      $library[$mascot] = $routes;
    }
    
    return $library;
  }

  /**
   * Load animation configuration
   * 
   * @return array
   */
  public function loadAnimations(): array
  {
    $data = Yaml::parseFile($this->yamlPath);
    return $data['animations'] ?? [];
  }

  /**
   * Validate YAML structure
   * 
   * @param array $data
   * @throws \RuntimeException
   */
  private function validate(array $data): void
  {
    $requiredMascots = ['sizzle', 'tabby', 'binx', 'ledger'];
        
    foreach ($requiredMascots as $mascot) {
      if (!isset($data[$mascot])) {
        throw new \RuntimeException(
          "Message library missing required mascot: {$mascot}"
        );
      }
            
      if (!is_array($data[$mascot])) {
        throw new \RuntimeException(
          "Mascot {$mascot} must be an array of routes"
        );
      }
      
      if (!isset($data[$mascot]['default'])) {
        throw new \RuntimeException(
          "Mascot {$mascot} missing required 'default' route"
        );
      }
    }
  }

  /**
   * Validate that all routes have all required moods
   * 
   * @param array $library
   * @param array<string> $requiredMoods
   * @throws \RuntimeException
   */
  public function validateMoods(array $library, array $requiredMoods): void
  {
    foreach ($library as $mascot => $routes) {
      foreach ($routes as $route => $moods) {
        foreach ($requiredMoods as $mood) {
          if (!isset($moods[$mood]) || empty($moods[$mood])) {
            throw new \RuntimeException(
              "Mascot '{$mascot}', route '{$route}' missing mood '{$mood}' or has no messages"
            );
          }
        }
      }
    }
  }
  
  /**
   * Get statistics about the message library
   * 
   * @return array{
   *   total_messages: int,
   *   mascots: int,
   *   routes: int,
   *   moods: int,
   *   breakdown: array
   * }
   */
  public function getStats(): array
  {
    $library = $this->load();
    $totalMessages = 0;
    $routes = [];
    $moods = [];
    $breakdown = [];
    
    foreach ($library as $mascot => $mascotRoutes) {
      $breakdown[$mascot] = [
        'routes' => count($mascotRoutes),
        'messages' => 0,
      ];
      
      foreach ($mascotRoutes as $route => $routeMoods) {
        $routes[$route] = true;
        
        foreach ($routeMoods as $mood => $messages) {
          $moods[$mood] = true;
          $messageCount = count($messages);
          $totalMessages += $messageCount;
          $breakdown[$mascot]['messages'] += $messageCount;
        }
      }
    }
    
    return [
      'total_messages' => $totalMessages,
      'mascots' => count($library),
      'routes' => count($routes),
      'moods' => count($moods),
      'breakdown' => $breakdown,
    ];
  }
}

<?php

namespace App\Katzen\Service\Utility;

use App\Shared\Repository\UserRepository;
use App\Katzen\ValueObject\LocationScope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * LocationContextService - Resolves the current location filtering context
 * 
 * Priority:
 * 1. URL parameters (?loc[]=1&loc[]=2 or ?loc=all)
 * 2. User preferences (per-dashboard: 'service' vs 'supply')
 * 3. Session fallback (workflow-specific, e.g., receiving wizard)
 * 4. Global default (all locations)
 */
final class LocationContextService
{
  public function __construct(
    private Security $security,
    private RequestStack $requestStack,
    private UserRepository $userRepo,
  ) {}
  
  /**
   * Resolve location scope for a specific dashboard context
   * 
   * @param Request $request Current request
   * @param string $dashboard Dashboard key ('service', 'supply', 'accounting', etc.)
   * @param string|null $workflowKey Optional workflow-specific key for session storage
   * @return LocationScope
   */
  public function resolveFor(Request $request, string $dashboard, ?string $workflowKey = null): LocationScope
  {
      if ($scope = $this->resolveFromUrl($request)) {
        return $scope;
      }

      if ($scope = $this->resolveFromUserPreference($dashboard)) {
        return $scope;
      }

      if ($workflowKey && $scope = $this->resolveFromSession($dashboard, $workflowKey)) {
        return $scope;
      }

      return new LocationScope('all');
    }

  /**
   * Persist the current scope to user preferences
   */
  public function persistScope(string $dashboard, LocationScope $scope): void
  {
    $user = $this->security->getUser();
    if (!$user) {
      return;
    }
    
    $prefs = []; # $user->getPreferences() ?? [];
    $prefs["location.{$dashboard}"] = $scope->toArray();
    
#    $user->setPreferences($prefs);
#    $this->userRepo->save($user);
  }
  
  /**
   * Store location in session for a specific workflow
   */
  public function setSessionLocation(string $dashboard, string $workflowKey, int $locationId): void
  {
    $session = $this->requestStack->getSession();
    $session->set("location.{$dashboard}.{$workflowKey}", $locationId);
  }

  /**
   * Clear session location for a workflow
   */
  public function clearSessionLocation(string $dashboard, string $workflowKey): void
  {
    $session = $this->requestStack->getSession();
    $session->remove("location.{$dashboard}.{$workflowKey}");
  }

  private function resolveFromUrl(Request $request): ?LocationScope
  {
    if ($ids = $request->query->all('loc')) {
      $ids = array_map('intval', array_filter($ids, 'is_numeric'));
      
      if (empty($ids)) {
        return null;
      }
      
      return count($ids) > 1
        ? new LocationScope('multi', $ids)
        : new LocationScope('single', $ids);
    }
    
    if ($id = $request->query->getInt('loc_id', 0)) {
      return new LocationScope('single', [$id]);
    }
    
    if ($request->query->get('loc') === 'all') {
      return new LocationScope('all');
    }
    
    return null;
  }

  private function resolveFromUserPreference(string $dashboard): ?LocationScope
  {
    $user = $this->security->getUser();
    if (!$user) {
      return null;
    }
    
    $prefs = []; # $user->getPreferences() ?? [];
    $key = "location.{$dashboard}";
    
    if (!isset($prefs[$key])) {
      return null;
    }
    
    try {
      return LocationScope::fromArray($prefs[$key]);
    } catch (\InvalidArgumentException) {
      // Invalid stored preference, ignore it
      return null;
    }
  }

  private function resolveFromSession(string $dashboard, string $workflowKey): ?LocationScope
  {
    $session = $this->requestStack->getSession();
    $key = "location.{$dashboard}.{$workflowKey}";
    
    if (!$session->has($key)) {
      return null;
    }
    
    $locationId = $session->get($key);
    
    if (!is_int($locationId) || $locationId <= 0) {
      return null;
    }
    
    return new LocationScope('single', [$locationId]);
  }
}

<?php

namespace App\Katzen\Service\Utility;

use App\Katzen\Service\Utility\AlertService;
use App\Katzen\Service\Utility\EncouragementEngine;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DashboardContextService
{
    public function __construct(
      private Security $security,
      private RequestStack $requestStack,
      private AlertService $alertService,
      private EncouragementEngine $encouragementEngine,
      private UrlGeneratorInterface $urlGenerator,
#        private TaskRepository $taskRepo,
#        private NotificationRepository $notificationRepo
#        private OrderRepository $orderRepo,
#        private StockRepository $stockRepo,
    ) {}

  /**
   * Check if user is authenticated, redirect to login if not
   * 
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  private function ensureAuthenticated(): void
  {
    if (!$this->security->getUser()) {
      $request = $this->requestStack->getCurrentRequest();
      $currentUrl = $request ? $request->getUri() : null;
      
      $loginUrl = $this->urlGenerator->generate('app_login');
      if ($currentUrl) {
        $loginUrl .= '?_target_path=' . urlencode($currentUrl);
      }
       
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(
        302,
        'Authentication required',
        null,
        ['Location' => $loginUrl]
      );
    }
  }
      
    public function getBaseContext(): array
    {        
        $this->ensureAuthenticated();
        $user = $this->security->getUser();

        if (!$user) {
          return [
            'user' => $this->buildUserContext(null),
            'encouragement' => ['header_message' => 'Please log in to continue'],
            'alerts' => [],
            'notifications' => [],
            'requires_authentication' => true,
          ];
        }
        
        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route', 'unknown');

        return [
            'user' => $this->buildUserContext($user),
            'encouragement' => $this->generateEncouragement($user, $route),
            'alerts' => $this->getAlertBanner($user),
            'notifications' => $this->getNotifications($user),
        ];
    }

    private function buildUserContext($user): array
    {
        return $user ? [
            'firstName' => explode(" ", $user->getName())[0],
            'lastName' => explode(" ", $user->getName())[1],
            'role' => 'Cook',
            'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg',
        ] : [
            'firstName' => 'Greg',
            'lastName' => 'Suehr',
            'role' => 'Captain',
            'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg',
        ];
    }

  private function generateEncouragement($user, string $route): array
  {
    $context = [
      'route' => $route,
      'time' => new \DateTime(),
      'day' => new \DateTime(),
      'user_name' => explode(" ", $user?->getName())[0] ?? 'friend',
    ];

    // $context['open_orders'] = $this->orderRepo->countOpen();
    // $context['orders_completed_today'] = $this->orderRepo->countCompletedToday();
    // $context['low_stock_items'] = $this->stockRepo->countLowStock();
    // $context['is_first_login_today'] = $this->isFirstLoginToday($user);
    
    // mock data
    $context['open_orders'] = 3;
    $context['orders_completed_today'] = 7;
    $context['low_stock_items'] = 2;
    $context['is_first_login_today'] = false;
    $context['recent_errors'] = 0;

    $result = $this->encouragementEngine->generate($context);

    return $result;

    return match ($route) {
      'menu_create_form' => ['header_message' => 'Build something delicious!'],
      'menu_index'       => ['header_message' => 'Review your menu legacy'],
      'dashboard_home'   => ['header_message' => 'Ready to crush it?'],
      default            => ['header_message' => 'Keep going — you got this!'],
    };
  }

    private function getAlertBanner($user): array
    {
      $alerts = $this->alertService->getAlertsForContext(
        user: $user,
        route: $this->requestStack->getCurrentRequest()
            ?->attributes->get('_route', 'unknown'),
        routeParams: $this->requestStack->getCurrentRequest()
            ?->attributes->get('_route_params', [])
      );

      // TODO: design behavior around multiple alert state
      return $alerts[0] ?? [];
    }

    private function getNotifications($user): array
    {
        // TODO: design notification service
        return [0, 1, 2];
    }

    private function isFirstLoginToday($user): bool
    {
        // TODO: check last login timestamp
        return false;
    }

    public function with(array $overrides): array
    {
        return array_merge($this->getBaseContext(), $overrides);
    }
}

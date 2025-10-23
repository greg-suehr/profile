<?php

namespace App\Katzen\Service\Utility;

use App\Katzen\Service\Utility\EncouragementEngine;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardContextService
{
    public function __construct(
      private Security $security,
      private RequestStack $requestStack,
      private EncouragementEngine $encouragementEngine,
#        private TaskRepository $taskRepo,
#        private NotificationRepository $notificationRepo
#        private OrderRepository $orderRepo,
#        private StockRepository $stockRepo,
    ) {}

    public function getBaseContext(): array
    {        
        # TODO: implement DashboardContextService
        $user = $this->security->getUser();
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
        // TODO: design alerting service
        return [
            'alert_text' => 'You have 2 orders waiting for approval and 3 items low in stock.',
        ];
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

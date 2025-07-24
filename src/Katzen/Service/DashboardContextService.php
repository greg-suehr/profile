<?php

namespace App\Katzen\Service;

#use App\Katzen\Repository\TaskRepository;
#use App\Katzen\Repository\NotificationRepository;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class DashboardContextService
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
#        private TaskRepository $taskRepo,
#        private NotificationRepository $notificationRepo
    ) {}

    public function getBaseContext(): array
    {        
        # TODO: implement DashboardContextService
        # $user = $this->security->getUser();
        $user = null;
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
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole(),
            'profileImage' => $user->getProfileImageUrl(),
        ] : [
            'firstName' => 'Greg',
            'lastName' => 'Suehr',
            'role' => 'Captain',
            'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg',
        ];
    }

    private function generateEncouragement($user, string $route): array
    {
        // TODO: design context and mood detection for Encouragement service
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

    public function with(array $overrides): array
    {
        return array_merge($this->getBaseContext(), $overrides);
    }
}

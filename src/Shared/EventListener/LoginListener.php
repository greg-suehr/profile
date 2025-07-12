<?php

namespace App\Shared\EventListener;

use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LoginListener
{
    public function __construct(private SessionInterface $session)
    {}

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        $sites = method_exists($user, 'getSite') ? $user->getSite() : null;
        if ($sites && count($sites) === 1) {
            $this->session->set('current_site_id', $sites[0]->getId());
        } else {
            $this->session->remove('current_site_id');
        }
    }
}

?>

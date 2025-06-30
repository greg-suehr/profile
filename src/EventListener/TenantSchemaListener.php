<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TenantSchemaListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private SiteContext          $siteContext;
    private LoggerInterface      $logger;

    public function __construct(
      EntityManagerInterface $em,
      SiteContext $siteContext,
      LoggerInterface $logger
    )
    {
        $this->em          = $em;
        $this->siteContext = $siteContext;
        $this->logger      = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 25], // High priority, before controllers
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->logger->debug('TenantSchemaListener::onKernelRequest fired', [
            'isMainRequest' => $event->isMainRequest(),
            'pathInfo'      => $event->getRequest()->getPathInfo(),
        ]);

        if (!$event->isMainRequest()) {
            $this->logger->debug('Skipping sub-request.');
            return;
        }
        
        $site = $this->siteContext->getCurrentSite();
        if (!$site) {
            $this->logger->debug('No site in context; skipping schema set.');
            return;
        }

        $schema = 'site_' . $site->getId();
        $this->logger->info("Setting search_path to \"$schema\", public");
        $conn   = $this->em->getConnection();
        $conn->executeStatement("SET search_path TO \"$schema\", public");
    }
}

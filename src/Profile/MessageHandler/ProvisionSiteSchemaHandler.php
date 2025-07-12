<?php

namespace App\Profile\MessageHandler;

use App\Profile\Message\ProvisionSiteSchema;
use App\Profile\Repository\SiteRepository;
use App\Profile\Service\SiteService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProvisionSiteSchemaHandler
{
    public function __construct(
        private SiteRepository  $sites,
        private SiteService     $siteService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProvisionSiteSchema $msg): void
    {
        $site = $this->sites->find($msg->getSiteId());
        if (!$site) {
            $this->logger->error("Site {$msg->getSiteId()} not found.");
            return;
        }

        $this->logger->info("Starting async provisioning for Site #{$site->getId()}");
        try {
            $this->siteService->provisionSchema($site);
            $this->logger->info("Finished provisioning for Site #{$site->getId()}");
        } catch (\Throwable $e) {
            $this->logger->error("Provisioning error for Site #{$site->getId()}: ".$e->getMessage());
        }
    }
}

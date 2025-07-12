<?php

namespace App\Profile\Middleware;

use App\Profile\Service\SiteContext;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Connection;

class TenantSchemaDriver extends AbstractDriverMiddleware
{
    private SiteContext $siteContext;

    public function __construct(Driver $driver, SiteContext $siteContext)
    {
        parent::__construct($driver);
        $this->siteContext = $siteContext;
    }

    public function connect(array $params): Connection
    {
        $connection = parent::connect($params);
        
        $site = $this->siteContext->getCurrentSite();
        if ($site) {
            $schema = 'site_' . $site->getId();
            $connection->exec(sprintf('SET search_path TO "%s", public', $schema));
        }

        return $connection;
    }
}

?>

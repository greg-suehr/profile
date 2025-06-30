<?php

namespace App\Middleware;

use App\Service\SiteContext;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\ServerVersionProvider;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class TenantSchemaMiddleware implements Middleware
{
  private SiteContext $siteContext;
  private bool $enabled;
  
  public function __construct(SiteContext $siteContext, bool $enabled = false)
    {
        $this->siteContext = $siteContext;
        $this->enabled = $enabled;
    }
  
  public function wrap(Driver $driver): Driver
    {
        if (!$this->enabled) {
            return $driver;
        }
        
        return new TenantSchemaDriver($driver, $this->siteContext);
    }
}

?>

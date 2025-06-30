<?php

namespace App\Message;

final class ProvisionSiteSchema
{
    private int $siteId;

    public function __construct(int $siteId)
    {
        $this->siteId = $siteId;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }
}

?>

<?php

namespace App\Profile\Entity;

use App\Profile\Entity\Site;
use App\Shared\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProfileUser extends User
{
    #[ORM\ManyToMany(targetEntity: Site::class, inversedBy: 'profile_users')]
  private ?Collection $sites;

  public function __construct()
    {
        parent::__construct();      // if User has a constructor
        $this->sites = new ArrayCollection();
    }

    public function getSite(): ?Site { return $this->sites[0]; }
  
    public function setSite(Site $site): static
    {
        $this->sites[0] = $site;
    }
}

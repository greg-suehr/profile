<?php
namespace App\Profile\Service;

use App\Profile\Entity\Site;
use App\Profile\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SiteContext
{
    private EntityManagerInterface $em;
    private RequestStack $requestStack;
    private SiteRepository $sites;

    public function __construct(
      EntityManagerInterface $em,
      RequestStack $requestStack,
      SiteRepository $sites,      
    )
    {
        $this->em           = $em;
        $this->requestStack = $requestStack;
        $this->sites        = $sites;
    }

    public function getCurrentSite(): ?Site
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->getSession()->has('current_site_id')) {
          $host = $this->requestStack
            ->getMainRequest()
                 ->getHost();

          [$subdomain] = explode('.', $host, 2);
          return $this->em
            ->getRepository(Site::class)
                ->findOneBy(['domain' => $subdomain]);
          return null;
        }
        return $this->sites->find($request->getSession()->get('current_site_id'));
    }
}

?>

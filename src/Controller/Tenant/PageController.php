<?php
  
namespace App\Controller\Tenant;

use App\Entity\Page;
use App\Entity\Site;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends AbstractController
{
    private SiteContext $siteContext;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(SiteContext $siteContext, EntityManagerInterface $em,
                                LoggerInterface $logger
    )
    {
        $this->siteContext = $siteContext;
        $this->em          = $em;
        $this->logger      = $logger;
    }

    //#[Route("/p/{slug}", name: "tenant_page_show", requirements: ["slug"=>".+"])] 
    #[Route("/p/{siteDomain}/{slug}", name: "tenant_page_show", requirements: ["siteDomain" => ".+", "slug"=>".+"])]
    public function show(Request $request, string $siteDomain, string $slug): Response
    {
          $current = $this->em->getConnection()->fetchOne('SELECT current_schema()');
          $this->logger->info('Before query in controller, current_schema = '.$current);

          $site = $this->em
                     ->getRepository(Site::class)
                     ->findOneBy([
                       'domain' => $siteDomain
                     ]);

          $this->logger->warning("Site is \"$site\"");
          if (!$site) {          
            return $this->render("profile/$slug.html.twig");
          }
          else {
            $schema = 'site_' . $site->getId();
            $this->em->getConnection()->executeStatement("SET search_path TO \"$schema\", public");
            $this->logger->warning("Controller enforced tenant scoping on site = \"$site\"");
          }

          $page = $this->em
                     ->getRepository(Page::class)
                     ->findOneBy([
                         'slug'         => $slug,
                         'is_published' => true,
                     ]);

        if (!$page) {
          $this->logger->info("Failed search for slug \"$slug\" on search_path for site \"$site\"");
            throw new NotFoundHttpException();
        }

        return $this->render('tenant/page.html.twig', [
            'page' => $page,
            'site' => $site,
        ]);
    }
}

?>

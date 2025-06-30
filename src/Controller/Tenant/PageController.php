<?php
  
namespace App\Controller\Tenant;

use App\Entity\Page;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends AbstractController
{
    private SiteContext $siteContext;
    private EntityManagerInterface $em;

    public function __construct(SiteContext $siteContext, EntityManagerInterface $em)
    {
        $this->siteContext = $siteContext;
        $this->em          = $em;
    }

    #[Route("/{slug}", name: "tenant_page_show", requirements: ["siteDomain"=>"[a-z0-9\-]+","slug"=>".+"])]
    public function show(Request $request, string $siteDomain): Response
    {
        $slug = ltrim($request->getPathInfo(), '/'); // slug from URL

        $site = $this->siteContext->getCurrentSite();
        if (!$site) {
            throw $this->createNotFoundException('Unknown site.');
        }

        $page = $this->em
                     ->getRepository(Page::class)
                     ->findOneBy([
                         'slug'         => $slug,
                         'is_published' => true,
                     ]);

        if (!$page) {
            throw new NotFoundHttpException();
        }

        return $this->render('tenant/page.html.twig', [
            'page' => $page,
            'site' => $site,
        ]);
    }
}

?>

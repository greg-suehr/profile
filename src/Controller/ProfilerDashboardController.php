<?php

namespace App\Controller;

use App\Controller\Admin\SiteCrudController;
use App\Entity\Category;
use App\Entity\Content;
use App\Entity\Page;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\SiteContext;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/profiler', routeName: 'profiler_admin')]
class ProfilerDashboardController extends AbstractDashboardController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private SiteContext      $siteContext,
        private RequestStack     $requestStack,
        private CategoryRepository $categoryRepo,
    ) {}
  
    #[Route("/profiler", name: "profiler_dashboard")]
    public function index(): Response
    {
        /** @var \Doctrine\Common\Collections\Collection|Site[] $sites */
        $sites = $this->getUser()->getSite();
        $session = $this->requestStack->getCurrentRequest()->getSession();        

        if (count($sites) === 0) {
            $routeBuilder = $this->container->get(AdminUrlGenerator::class);
            $url = $routeBuilder->setController(SiteCrudController::class)->generateUrl();
            return $this->redirect($url);
        }

        if (count($sites) === 1 && !$session->has('current_site_id')) {
            $session->set('current_site_id', $sites[0]->getId());
        }

        if (count($sites) > 1 && !$session->has('current_site_id')) {
            return $this->redirectToRoute('select_site');
        }

        // At this point we have current_site_id set
        $categories = $this->categoryRepo->findAll();
        return $this->render('instance.html.twig', [
          'categories' => $categories,
          'site_name' =>  $session->get('current_site_id')
                               ]
        );
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Sites', 'fa fa-globe', Site::class);
        yield MenuItem::linkToCrud('Categories', 'fa fa-sliders', Category::class);
        yield MenuItem::linkToCrud('Content', 'fa fa-database', Content::class);

        // dynamically add "New Content" links per category
        $categories = $this->categoryRepo->findAll(); // pulls all entries :contentReference[oaicite:0]{index=0}
        foreach ($categories as $category) {
          
          $url = $this->generateUrl('content_new', [
            'category' => $category->getId(),
          ]);
          
          yield MenuItem::linkToURL(
                sprintf('New %s!', $category->getName()),
                'fa fa-plus-circle',
                $url
            );
        }

        yield MenuItem::linkToCrud('Pages', 'fa fa-file', Page::class);
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);
    }
}

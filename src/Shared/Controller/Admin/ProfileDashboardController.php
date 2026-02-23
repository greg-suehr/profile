<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\BlogPost;
use App\Shared\Entity\ProfileReadingListItem;
use App\Shared\Entity\ProfileDocument;
use App\Shared\Entity\ProfileInfluence;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%gregishere_match%'")]
class ProfileDashboardController extends AbstractDashboardController
{
    #[Route('/greg/admin', name:'greg_admin')]
    public function index(): Response
    {
      $routeBuilder = $this->container->get(AdminUrlGenerator::class);
      $url = $routeBuilder->setController(BlogPostCrudController::class)->generateUrl();
      return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Greg Is Here');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linktoCrud('Blogs', 'fas fa-letter', BlogPost::class);
        yield MenuItem::linkToCrud('Research Docs', 'fas fa-file-pdf', ProfileDocument::class);
        yield MenuItem::linkToCrud('Reading List', 'fas fa-book', ProfileReadingListItem::class);
        yield MenuItem::linkToCrud('Influences', 'fas fa-user', ProfileInfluence::class);
    }
}

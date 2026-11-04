<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\CmsPage;
use App\Shared\Entity\RsvpLog;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(
    routePath: '/litmas/admin',
    routeName: 'litmas_admin',
    routeOptions: ['condition' => "request.getHost() matches '%litmas_match%'"],
    allowedControllers: [
        RsvpLogCrudController::class,
        CmsPageCrudController::class,
    ],
)]
#[IsGranted('ROLE_ADMIN')]
class LitmasDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
      $routeBuilder = $this->container->get(AdminUrlGenerator::class);
      $url = $routeBuilder->setController(RsvpLogCrudController::class)->generateUrl();
      return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Litmas');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linktoCrud('RSVPs', 'fas fa-letter', RsvpLog::class);        
        yield MenuItem::linktoCrud('Site Content', 'fas fa-page', CmsPage::class);
    }
}

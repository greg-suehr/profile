<?php

namespace App\Katzen\Controller\Admin;

use App\Katzen\Entity\KatzenUser;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Unit;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name:'admin')]
    public function index(): Response
    {
      $routeBuilder = $this->container->get(AdminUrlGenerator::class);
      $url = $routeBuilder->setController(RecipientCrudController::class)->generateUrl();
      return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Katzen');
    }

    public function configureMenuItems(): iterable
    {
      //yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
      yield MenuItem::linktoRoute('Back to the waitlist', 'fas fa-home', 'app_notify_list');
      yield MenuItem::linktoCrud('Items', 'fas fa-carrot', Item::class);
      yield MenuItem::linktoCrud('Units', 'fas fa-scale-balanced', Unit::class);
    }
}

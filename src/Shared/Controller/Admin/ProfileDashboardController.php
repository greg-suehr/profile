<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\Album;
use App\Shared\Entity\BlogPost;
use App\Shared\Entity\Essay;
use App\Shared\Entity\Play;
use App\Shared\Entity\Poem;
use App\Shared\Entity\ProfileReadingListItem;
use App\Shared\Entity\ProfileDocument;
use App\Shared\Entity\ProfileInfluence;
use App\Shared\Entity\Song;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(
    routePath: '/greg/admin',
    routeName: 'greg_admin',
    routeOptions: ['condition' => "request.getHost() matches '%gregishere_match%'"],
    allowedControllers: [
        BlogPostCrudController::class,
        ProfileDocumentCrudController::class,
        ProfileReadingListItemCrudController::class,
        ProfileInfluenceCrudController::class,
        PoemCrudController::class,
        PlayCrudController::class,
        EssayCrudController::class,
        AlbumCrudController::class,
        SongCrudController::class,
        UserCrudController::class,
    ],
)]
#[IsGranted('ROLE_ADMIN')]
class ProfileDashboardController extends AbstractDashboardController
{
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
        yield MenuItem::linkToCrud('Poems', 'fas fa-feather', Poem::class);
        yield MenuItem::linkToCrud('Plays', 'fas fa-masks-theater', Play::class);
        yield MenuItem::linkToCrud('Essays', 'fas fa-pen-nib', Essay::class);
        yield MenuItem::linkToCrud('Albums', 'fas fa-record-vinyl', Album::class);
        yield MenuItem::linkToCrud('Songs', 'fas fa-music', Song::class);
    }
}

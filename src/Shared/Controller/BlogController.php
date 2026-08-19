<?php

namespace App\Shared\Controller;

use App\Shared\Controller\Admin\BlogPostCrudController;
use App\Shared\Controller\Admin\ProfileDashboardController;
use App\Shared\Entity\BlogPost;
use App\Shared\Entity\User;
use App\Shared\Repository\BlogPostRepository;
use App\Shared\Service\DraftContentConverter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%gregishere_match%'")]
final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'profile_blog')]
    public function index(Request $request, BlogPostRepository $postRepository): Response
    {

        return $this->render('blog/index.html.twig', [
            'controller_name' => 'BlogController',
            'featured_post' => $postRepository->getFeature(),
            'posts' => $postRepository->findRecent(),
        ]);
    }

    #[Route('/blog/{id}', name: 'profile_post', requirements: ['id' => '\d+'])]
    public function post(Request $request, BlogPost $post, BlogPostRepository $postRepository): Response
    {
        return $this->render('blog/post.html.twig', [
            'controller_name' => 'BlogController',
            'post' => $post,
        ]);
    }

    #[Route('/blog-status', name: 'profile_blog_status')]
    public function status(BlogPostRepository $postRepository, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $posts = $postRepository->findAllForStatusBoard();

        $columns = [];
        foreach (BlogPost::STATUSES as $label => $value) {
            $columns[$value] = [
                'label' => $label,
                'posts' => [],
            ];
        }

        foreach ($posts as $post) {
            $columns[$post->getStatus()]['posts'][] = $post;
        }

        $editUrls = [];
        foreach ($posts as $post) {
            $editUrls[$post->getId()] = $adminUrlGenerator
                ->setDashboard(ProfileDashboardController::class)
                ->setController(BlogPostCrudController::class)
                ->setAction('edit')
                ->setEntityId($post->getId())
                ->generateUrl();
        }

        return $this->render('blog/status.html.twig', [
            'controller_name' => 'BlogController',
            'columns' => $columns,
            'edit_urls' => $editUrls,
        ]);
    }

    #[Route('/blog/draft', name: 'profile_blog_draft', methods: ['GET', 'POST'])]
    public function draft(
        Request $request,
        DraftContentConverter $converter,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator,
    ): Response {
        $title = trim((string) $request->request->get('title', ''));
        $draftText = (string) $request->request->get('draft', '');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_blog_draft', (string) $request->request->get('_csrf_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ($title === '' || trim($draftText) === '') {
                $this->addFlash('error', 'A title and some draft text are both required.');

                return $this->render('blog/draft.html.twig', [
                    'controller_name' => 'BlogController',
                    'title' => $title,
                    'draft' => $draftText,
                ]);
            }

            $author = $this->getUser();
            if (!$author instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $post = new BlogPost();
            $post->setTitle($title);
            $post->setSubtitle($converter->guessSubtitle($draftText));
            $post->setSummary($converter->guessSummary($draftText));
            $post->setContent($converter->toHtml($draftText));
            $post->setTextContent($converter->toPlainText($draftText));
            $post->setAuthor($author);
            $post->setIsPublished(false);
            $post->setStatus(BlogPost::STATUS_DRAFTING);
            $post->setCreatedAt(new \DateTimeImmutable());
            $post->setUpdatedAt(new \DateTime());

            $entityManager->persist($post);
            $entityManager->flush();

            $editUrl = $adminUrlGenerator
                ->setDashboard(ProfileDashboardController::class)
                ->setController(BlogPostCrudController::class)
                ->setAction('edit')
                ->setEntityId($post->getId())
                ->generateUrl();

            $this->addFlash('success', 'Draft saved — the subtitle and summary below are guesses, give them a look before publishing.');

            return $this->redirect($editUrl);
        }

        return $this->render('blog/draft.html.twig', [
            'controller_name' => 'BlogController',
            'title' => $title,
            'draft' => $draftText,
        ]);
    }
}

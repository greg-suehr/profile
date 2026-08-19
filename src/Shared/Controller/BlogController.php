<?php

namespace App\Shared\Controller;

use App\Shared\Controller\Admin\BlogPostCrudController;
use App\Shared\Entity\BlogPost;
use App\Shared\Repository\BlogPostRepository;
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

    #[Route('/blog/{id}', name: 'profile_post')]
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
}

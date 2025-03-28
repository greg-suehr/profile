<?php

namespace App\Controller;

use App\Entity\BlogPost;
use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'profile_blog')]
    public function index(Request $request, BlogPostRepository $postRepository): Response
    {

        return $this->render('blog/index.html.twig', [
            'controller_name' => 'BlogController',
            'featured_post' => $postRepository->getFeatures(1)[0],
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
}

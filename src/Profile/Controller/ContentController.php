<?php

namespace App\Profile\Controller;

use App\Profile\Entity\Content;
use App\Profile\Form\ContentFormType;
use App\Profile\Repository\CategoryRepository;
use App\Profile\Service\SiteContext;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%gregishere_match%'")]
final class ContentController extends AbstractController
{

  public function __construct(
        private SiteContext      $siteContext,
        private RequestStack     $requestStack,
        private CategoryRepository $categoryRepo,
    ) {}
  
  #[Route('/content', name: 'app_content')]
  public function index(): Response
  {
        return $this->render('content/index.html.twig', [
          'controller_name' => 'ContentController',
        ]);
  }

  #[Route('/content/new', name: 'content_new')]
  public function new(Request $request): Response
    {
      $catId = $request->query->getInt('category');
      if (!$catId) {
        throw $this->createNotFoundException('No category specified');
      }

      $category = $this->categoryRepo->find($catId);
      if (!$category) {
        throw $this->createNotFoundException("Category #{$catId} not found");
      }

      $content = new Content();
      $content->setCategory($category);

      dd($category->getSchemaDefinitions());
      
      $form = $this->createForm(ContentFormType::class, $content, [
        'field_schema' => $category->getSchemaDefinitions(),
      ]);
      $form->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid()) {
        $this->em->persist($content);
        $this->em->flush();

        $this->addFlash('success', 'Content created!');
        return $this->redirectToRoute('profiler_dashboard');
      }

      return $this->render('content_new.html.twig', [
        'form'     => $form->createView(),
        'category' => $category,
      ]);
    }
}

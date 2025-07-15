<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\Items;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Tag;
use App\Katzen\Form\MenuType;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\RecipeListRepository;
use App\Katzen\Service\RecipeImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Seld\JsonLint\JsonParser;

final class ManagerController extends AbstractController
{
  #[Route('/dashboard', name: 'dashboard_home')]
  public function index(): Response
  {

        return $this->render('katzen/base.html.twig', [
          'active'     => 'home',
          'activeItem' => 'home',                    
          'activeMenu' => 'home',
          'user' => array('firstName' => 'Greg', 'lastName' => 'Suehr', 'role' => 'Captain',
                          'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg'
          ),
          'encouragement' => array(
            'header_message' => 'Ready to crush it?',
          ),
          'alerts' => array(
            'alert_text' => 'You have 2 orders waiting for approval and 3 items low in stock.',
          ),
          'notifications' => [0, 1, 2],
          'widgets' => [array('title' => 'KPI', 'value' => '100%')],
        ]);
    }

  #[Route('/menus/create', name: 'menu_create_form')]
  public function menuCreate(Request $request, EntityManagerInterface $em, RecipeRepository $recipeRepo): Response
  {
    $menu = new RecipeList();
    $form = $this->createForm(MenuType::class, $menu);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($menu);
          
        $tag = new Tag();
        $tag
          ->setObj('recipe_list')
            ->setType('menu')
            ->setValue($form->get('current')->getData() ? 'current' : '')
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($tag);
        
        $mealType = $form->get('mealType')->getData();
        $mealTag = (new Tag())
            ->setObj('recipe_list')
            ->setType('meal_type')
            ->setValue($mealType)
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($mealTag);

        $status = $form->get('statusTag')->getData();
        $statusTag = (new Tag())
            ->setObj('recipe_list')
            ->setType('status')
            ->setValue($status)
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($statusTag);        
        
        $em->flush();
        
        $this->addFlash('success', 'Menu and tag created!');
        return $this->redirectToRoute('menu_index');
    }

    $recipes = $recipeRepo->findAll();

    # TODO: fix Ajax routing, smooth out page flashes
    if ($request->isXmlHttpRequest()) {
      //dd("Ajax :)");
      return $this->render('katzen/manager/_create_menu.html.twig', [
        'form'    => $form->createView(),
        'recipes' => $recipes,
      ]);
    }
    
    return $this->render('katzen/manager/create_menu.html.twig', [
        'active'     => 'menu-create',      
        'activeItem' => 'menu-create',
        'activeMenu' => 'menu',
        'user' => array('firstName' => 'Greg', 'lastName' => 'Suehr', 'role' => 'Captain',
                        'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg'
        ),
        'encouragement' => array(
          'header_message' => 'Ready to crush it?',
        ),
        'alerts' => array(
          'alert_text' => 'You have 2 orders waiting for approval and 3 items low in stock.',
        ),
        'notifications' => [0, 1, 2],
        'form'         => $form->createView(),
        'recipes'    => $recipes,        
    ]);
  }

  #[Route('/menus', name: 'menu_index')]
  public function menus(Request $request, RecipeListRepository $menuRepo): Response
  {
       $menus = $menuRepo->findAll(); # TODO: filter on Menu tag

       return $this->render('katzen/manager/list_menus.html.twig', [
         'active'     => 'menu-create',
         'activeItem' => 'menu-create',
         'activeMenu' => 'menu',
         'user' => array('firstName' => 'Greg', 'lastName' => 'Suehr', 'role' => 'Captain',
                         'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg'
         ),
         'encouragement' => array(
           'header_message' => 'Ready to crush it?',
         ),
         'alerts' => array(
           'alert_text' => 'You have 2 orders waiting for approval and 3 items low in stock.',
         ),
         'notifications' => [0, 1, 2],
         'menus' => $menus,
       ]);
       
    }

  #[Route('/stock', name: 'stock_index')]
  public function stock(Request $request, ItemRepository $itemRepo): Response
  {        
        return $this->render('katzen/recipe/list.html.twig', [
          'recipes'         => $itemRepo->findAll(),
        ]);
    }

  #[Route('/schedules', name: 'schedule_index')]
  public function schedules(Request $request): Response
    {
        return $this->render('katzen/manager/dashboard.html.twig', [
          'active' => 'coming_soon',
          'description' => 'Scheduling coming soon!'
        ]);
    }

  #[Route('/notifications', name: 'notifications')]
  public function notifications(Request $request): Response
  {
        return $this->render('katzen/manager/dashboard.html.twig', [
          'active' => 'coming_soon',
          'description' => 'Notifications coming soon!'
        ]);
    }
}

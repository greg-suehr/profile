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
use App\Katzen\Repository\TagRepository;
use App\Katzen\Service\DashboardContextService;
use App\Katzen\Service\RecipeImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Seld\JsonLint\JsonParser;

final class ManagerController extends AbstractController
{
  public function __construct(private DashboardContextService $dashboardContext) {}
  
  #[Route('/dashboard', name: 'dashboard_home')]
  public function index(): Response
  {

        return $this->render('katzen/base.html.twig', $this->dashboardContext->with([
          'active'     => 'home',
          'activeItem' => 'home',                    
          'activeMenu' => 'home',
          'widgets' => [array('title' => 'KPI', 'value' => '100%')],
        ]));
    }

  // TODO: port tag logic to a shared TagManager service
  private function setMenuTags(RecipeList $menu, string $tag_type, string $value, EntityManagerInterface $em, TagRepository $tagRepo): void
  {
    $tag = $tagRepo->findOneByType('recipe_list', $menu->getId(), $tag_type);
    if ($tag) {
      $tag->setValue($value);
    }
    else {
      $tag = (new Tag())
        ->setObj('recipe_list')
        ->setObjId($menu->getId())
        ->setType($tag_type)
        ->setValue($value)
        ->setCreatedAt(new \DateTimeImmutable());
      $em->persist($tag);      
    }
  }
  
  private function updateMenuTags(RecipeList $menu, Form $form, EntityManagerInterface $em, TagRepository $tagRepo): void
  {
      $menuTags = ['menu', 'meal_type', 'status' ];
      foreach ($menuTags as $tag_type) {
        if ( $tag_type === 'menu' ) {
          $value = $form->get('current')->getData() ? 'current' : '';
        }
        else {
          $value = $form->get($tag_type)->getData();
        }
        $this->setMenuTags($menu, $tag_type, $value, $em, $tagRepo);
      }
  }

  #[Route('/menus/create', name: 'menu_create_form')]
  public function menuCreate(Request $request, EntityManagerInterface $em, RecipeRepository $recipeRepo, TagRepository $tagRepo): Response
  {
    $menu = new RecipeList();
    $form = $this->createForm(MenuType::class, $menu);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($menu);
        $em->flush();        

        $this->updateMenuTags($menu, $form, $em, $tagRepo);        
        $em->flush();
        
        $this->addFlash('success', 'Menu and tag created!');
        return $this->redirectToRoute('menu_index');
    }

    $recipes = $recipeRepo->findAll();

    # TODO: fix Ajax routing, smooth out page flashes
    if ($request->isXmlHttpRequest()) {
      //dd("Ajax :)");
      return $this->render('katzen/manager/_menu_form.html.twig', [
        'form'    => $form->createView(),
        'recipes' => $recipes,
      ]);
    }
    
    return $this->render('katzen/manager/menu_form.html.twig', $this->dashboardContext->with([
        'active'     => 'menu-create',      
        'activeItem' => 'menu-create',
        'activeMenu' => 'menu',
        'form'         => $form->createView(),
        'recipes'    => $recipes,        
    ]));
  }

  #[Route('/menus', name: 'menu_index')]
  public function menus(Request $request, EntityManagerInterface $em, TagRepository $tagRepo): Response
  {
       $statusFilter = $request->query->get('status', 'active');
       $mealFilter = $request->query->get('meal_type');
       $search = $request->query->get('search');

       $query = $em->createQueryBuilder()
        ->select('r')
        ->from(RecipeList::class, 'r')
        ->leftJoin(Tag::class, 't', 'WITH', 't.obj = :obj AND t.obj_id = r.id')
        ->setParameter('obj', 'recipe_list');

       
       if ($statusFilter) {
         $query->andWhere('t.type = :type AND t.value IN (:status)')
              ->setParameter('type', 'status')
              ->setParameter('status', $statusFilter === 'active' ? ['active', 'current'] : [$statusFilter]);
       }
       
       if ($mealFilter) {
         dd("meal");
         $query->andWhere('EXISTS (
            SELECT 1 FROM App\Katzen\Entity\Tag mt 
            WHERE mt.obj = CONCAT(\'recipelist:\', r.id) 
              AND mt.type = \'meal_type\' 
              AND mt.value = :meal
        )')->setParameter('meal', $mealFilter);
       }
       
       if ($search) {
         dd("search");
         $query->andWhere('r.name LIKE :search')->setParameter('search', "%$search%");
       }
       
       $menus = $query->getQuery()->getResult();
       $tags = $tagRepo->findBy([
         'obj' => 'recipe_list',
         'obj_id' => array_map(fn($menu) => $menu->getId(), $menus),
       ]);

       $tagMap = [];
       foreach ($tags as $tag) {
         $tagMap[$tag->getObjId()][$tag->getType()] = $tag->getValue();
       } 

       return $this->render('katzen/manager/list_menus.html.twig',  $this->dashboardContext->with([
         'active'     => 'menu-create',
         'activeItem' => 'menu-create',
         'activeMenu' => 'menu',
         'menus'  => $menus,
         'tagMap' => $tagMap
       ]));
    }

  #[Route('/menus/edit/{id}', name: 'menu_edit_form')]
  public function menuEdit(int $id, Request $request, EntityManagerInterface $em, RecipeRepository $recipeRepo, RecipeListRepository $menuRepo, TagRepository $tagRepo): Response
  {
    $menu = $menuRepo->find($id);
    if (!$menu) {
      throw $this->createNotFoundException('Menu not found.');
    }
    
    $tags = $tagRepo->findByObj('recipe_list', $menu->getId());
    foreach ($tags as $tag) {
      switch ($tag->getType()) {
      case 'meal_type':
        $menu->setMealType($tag->getValue());
        break;
      case 'status':
        $menu->setStatus($tag->getValue());
        break;
      case 'menu':
        $menu->setCurrent($tag->getValue() === 'current');
        break;
      }
    }
    
    $form = $this->createForm(MenuType::class, $menu);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $this->updateMenuTags($menu, $form, $em, $tagRepo);
        $em->flush();

        $this->addFlash('success', 'Menu updated!');
        return $this->redirectToRoute('menu_index');
    }

    $recipes = $recipeRepo->findAll();

    if ($request->isXmlHttpRequest()) {
        return $this->render('katzen/manager/_menu_form.html.twig', [
          'form'    => $form->createView(),
          'recipes' => $recipes,
          'menu'    => $menu,
        ]);
    }

    return $this->render('katzen/manager/menu_form.html.twig', $this->dashboardContext->with([
      'active'     => 'menu-edit',
      'activeItem' => 'menu-create',
      'activeMenu' => null,
      'form'    => $form->createView(),
      'recipes' => $recipes,
      'menu'    => $menu,
    ]));
  }                

  #[Route('/menus/view/{id}', name: 'menu_view')]
  public function menuView(Request $request, RecipeList $menu): Response
  {
    return $this->render('katzen/manager/view_menu.html.twig',  $this->dashboardContext->with([
      'active'     => 'home',
      'activeItem' => 'home',
      'activeMenu' => 'home',
      'menu' => $menu,
      'items' => $menu->getRecipes(),
	]));
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

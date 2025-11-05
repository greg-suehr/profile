<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\DashboardContextService;

use App\Katzen\Component\TableView\{TableAction, TableField, TableFilter, TableRow, TableView};

use App\Katzen\Entity\Items;
use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Tag;
use App\Katzen\Form\MenuType;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Repository\RecipeListRepository;
use App\Katzen\Repository\TagRepository;
use App\Katzen\Service\Cook\RecipeImportService;
use App\Katzen\Service\Order\DefaultMenuPlanner;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Seld\JsonLint\JsonParser;

#[Route('/menu', name: 'menu_')]
final class MenuController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private DefaultMenuPlanner $menus,
  ) {}

  #[Route('/', name: 'index')]
  #[DashboardLayout('service', 'menu', 'menu-index')]
  public function menuCurrent(Request $request): Response
  {
    $currentMenu = $this->menus->getActiveMenu();

    return $this->render('katzen/menu/view_menu.html.twig',  $this->dashboardContext->with([
      'menu' => $currentMenu,
      'items' => $currentMenu->getRecipes(),
    ]));     
  }

  #[Route('/create', name: 'create')]
  #[DashboardLayout('prep', 'menu', 'menu-create')]
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
        return $this->redirectToRoute('menu_table');
    }

    $recipes = $recipeRepo->findAll();

    # TODO: fix Ajax routing, smooth out page flashes
    if ($request->isXmlHttpRequest()) {
      return $this->render('katzen/menu/_menu_form.html.twig', [
        'form'    => $form->createView(),
        'recipes' => $recipes,
      ]);
    }
    
    return $this->render('katzen/menu/menu_form.html.twig', $this->dashboardContext->with([
        'form'         => $form->createView(),
        'recipes'    => $recipes,        
    ]));
  }

  #[Route('/list', name: 'table')]
  #[DashboardLayout('prep', 'menu', 'menu-table')]
  public function menu_table(Request $request, EntityManagerInterface $em, TagRepository $tagRepo): Response
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
         $query->andWhere('EXISTS (
            SELECT 1 FROM App\Katzen\Entity\Tag mt 
            WHERE mt.obj = CONCAT(\'recipelist:\', r.id) 
              AND mt.type = \'meal_type\' 
              AND mt.value = :meal
        )')->setParameter('meal', $mealFilter);
       }
       
       if ($search) {
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

       $rows = [];
       foreach ($menus as $menu) {
         $menuTags = $tagMap[$menu->getId()] ?? [];
         $status = $menuTags['status'] ?? '-';
         $mealType = $menuTags['meal_type'] ?? 'all day';
         
         $row = TableRow::create([
           'name' => $menu->getName(),
           'status' => $status,
           'meal_type' => $mealType,
           'last_used' => $menu->getUpdatedAt() ? $menu->getUpdatedAt()->format('Y-m-d') : 'never',
         ])
          ->setId($menu->getId())
          ->setLink('menu_view', ['id' => $menu->getId()]);
         
         if ($status === 'archived') {
           $row->setStyleClass('text-muted');
          }
            
         $rows[] = $row;
      }

       $table = TableView::create('menus-table')
            ->addField(
              TableField::link('name', 'Name', 'menu_view')
                    ->sortable()
            )
            ->addField(
              TableField::badge('status', 'Status')
                    ->badgeMap([
                      'active' => 'success',
                      'current' => 'primary',
                      'draft' => 'warning',
                      'archived' => 'secondary',
                    ])
                    ->sortable()
            )
            ->addField(
              TableField::text('meal_type', 'Meal Type')
                    ->sortable()
                    ->hiddenMobile()
            )
            ->addField(
              TableField::date('last_used', 'Last Used', 'Y-m-d')
                    ->sortable()
                    ->hiddenMobile()
            )
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(TableAction::edit('menu_edit'))
            ->addQuickAction(TableAction::view('menu_view'))
            ->addBulkAction(
              TableAction::create('archive', 'Archive Selected')
                    ->setIcon('bi-archive')
                    ->setVariant('outline-warning')
                    ->setConfirmMessage('Are you sure you want to archive the selected menus?')
            )
            ->addBulkAction(
              TableAction::create('delete', 'Delete Selected')
                    ->setIcon('bi-trash')
                    ->setVariant('outline-danger')
                    ->setConfirmMessage('Are you sure you want to delete the selected menus? This cannot be undone.')
            )
            ->setSearchPlaceholder('Search menus by name...')
            ->setEmptyState('No menus found. Create your first menu to get started!')
            ->build();
       
       return $this->render('katzen/component/table_view.html.twig',  $this->dashboardContext->with([
         'table' => $table,
         'bulkRoute' => 'menu_bulk',
         'csrfSlug' => 'menu_bulk'
       ]));
    }

  #[Route('/edit/{id}', name: 'edit')]
  #[DashboardLayout('prep', 'menu', 'menu-edit')]
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
        return $this->redirectToRoute('menu_table');
    }

    $recipes = $recipeRepo->findAll();

    if ($request->isXmlHttpRequest()) {
        return $this->render('katzen/menu/_menu_form.html.twig', [
          'form'    => $form->createView(),
          'recipes' => $recipes,
          'menu'    => $menu,
        ]);
    }

    return $this->render('katzen/menu/menu_form.html.twig', $this->dashboardContext->with([
      'form'    => $form->createView(),
      'recipes' => $recipes,
      'menu'    => $menu,
    ]));
  }                

  #[Route('/view/{id}', name: 'view')]
  #[DashboardLayout('prep', 'menu', 'menu-view')]
  public function menuView(Request $request, RecipeList $menu): Response
  {
    return $this->render('katzen/menu/view_menu.html.twig',  $this->dashboardContext->with([
      'menu' => $menu,
      'items' => $menu->getRecipes(),
	]));
  }

  #[Route('/bulk', name: 'bulk', methods: ['POST'])]
  public function menuBulk(Request $request, EntityManagerInterface $em, TagRepository $tagRepo): Response
  {
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('menu_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }
    
    $action = $payload['action'] ?? null;
    $ids    = array_map('intval', $payload['ids'] ?? []);

    if (!$action || empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
    }
    
    switch ($action) {
    case 'archive':
      foreach ($ids as $id) {
        $menu = $em->getRepository(\App\Katzen\Entity\RecipeList::class)->find($id);
        if (!$menu) continue;
        // reuse your tag helper
        $this->setMenuTags($menu, 'status', 'archived', $em, $tagRepo);
      }
      $em->flush();
      break;
      
    case 'delete':
      foreach ($ids as $id) {
        $menu = $em->getRepository(\App\Katzen\Entity\RecipeList::class)->find($id);
        if ($menu) $em->remove($menu);
      }
      $em->flush();
      break;
      
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
    
    return $this->json(['ok' => true]);
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
}

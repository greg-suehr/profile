<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\{TableAction, TableField, TableFilter, TableRow, TableView};
use App\Katzen\Entity\Recipe;
use App\Katzen\Form\ImportRecipeType;
use App\Katzen\Form\RecipeBuilderType;
use App\Katzen\Form\CreateRecipeFlow;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Service\Cook\RecipeImportService;
use App\Katzen\Service\Delete\DeleteMode;
use App\Katzen\Service\Delete\RecipeDeletionPolicy;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Seld\JsonLint\JsonParser;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class RecipeController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private ItemRepository $itemRepo,
    private RecipeRepository $recipeRepo,
    private RecipeDeletionPolicy $deletionPolicy,
  ){}
  
  #[Route('/recipe', name: 'recipe_index')]
  #[DashboardLayout('prep', 'recipe', 'recipe-index')]
  public function index(): Response
  {
    $recipes = $this->recipeRepo->findBy([]);
    return $this->render('katzen/recipe/index.html.twig',  $this->dashboardContext->with([
      'recipes'    => $recipes,
    ]));
  }  
  
  #[Route('/recipe/bulk', name: 'recipe_bulk', methods: ['POST'])]
  public function recipeBulk(Request $request): Response
  {    
    $payload = json_decode($request->getContent(), true) ?? [];
    if (!$this->isCsrfTokenValid('recipe_bulk', $payload['_token'] ?? '')) {
      return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
    }

    $action = $payload['action'] ?? null;
    $ids    = array_map('intval', $payload['ids'] ?? []);
    $mode   = DeleteMode::from($payload['mode'] ?? DeleteMode::BLOCK_IF_REFERENCED->value);
    
    if (!$action || empty($ids)) {
      return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
    }

    switch ($action) {
    case 'delete':
      $deleted = 0;
      $blocked = [];

      foreach ($ids as $id) {
        $recipe = $this->recipeRepo->find($id);
        if (!$recipe) {
          continue;
        }

        $report = $this->deletionPolicy->preflight($recipe, $mode);
                
        if (!$report->ok) {
          $blocked[] = [
            'id' => $id,
            'title' => $recipe->getTitle(),
            'reasons' => $report->reasons,
            'facts' => $report->facts,
          ];
          continue;
        }

        try {
          $this->deletionPolicy->execute($recipe, $mode);
          $deleted++;
        } catch (\Exception $e) {
          $blocked[] = [
            'id' => $id,
            'title' => $recipe->getTitle(),
            'reasons' => [$e->getMessage()],
          ];
        }
      }
      
      if (count($blocked) > 0 && $deleted === 0) {
        error_log('About to return blocked response: ' . json_encode($blocked));
        return $this->json([
          'ok' => false,
          'error' => 'All deletions blocked',
          'blocked' => $blocked,
        ], 409);
      }
            
      return $this->json([
        'ok' => true,
        'message' => sprintf(
          '%d recipe(s) deleted%s',
          $deleted,
          count($blocked) > 0 ? sprintf(', %d blocked', count($blocked)) : ''
        ),
        'deleted' => $deleted,
        'blocked' => $blocked,
      ]);

    case 'archive':
      $count = 0;
      foreach ($ids as $id) {
        $recipe = $this->recipeRepo->find($id);
        if ($recipe) {
          $recipe->setStatus('archived');
          $count++;
        }
      }
      $this->em->flush();
      return $this->json(['ok' => true, 'message' => "$count recipe(s) archived"]);
      
    default:
      return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
    }
    
    return $this->json(['ok' => true]);
  }

  #[Route('/recipe/manage', name: 'recipe_table')]
  #[DashboardLayout('prep', 'recipe', 'recipe-table')]
  public function list(Request $request): Response
  {
    $recipes = $this->recipeRepo->findBy([]);
    $rows = [];
    foreach ($recipes as $recipe) {
      $numIngredients = $recipe->getRecipeIngredients()->count();
      
      $row = TableRow::create([
        'name' => $recipe->getTitle(),
        'item_count' => $numIngredients,
        'prep_time' => $recipe->getPrepTime(),
        'wait_time' => $recipe->getWaitTime(),
        'cook_time' => $recipe->getCookTime(),
        'status' => $recipe->getStatus(),
      ])
        ->setId($recipe->getId())
        ->setLink('recipe_view', ['id' => $recipe->getId()]);

      $rows[] = $row;
    }

    $table = TableView::create('recipe-table')
      ->addField(
        TableField::text('name', 'Recipe Name')
        ->sortable()
      )
      ->addField(
    	TableField::text('item_count', 'Ingredients')
        ->sortable()
      )
      ->addField(
        TableField::duration('prep_time', 'Prep')
        ->sortable()
      )
      ->addField(
        TableField::duration('wait_time', 'Wait')
        ->sortable()
      )
      ->addField(
        TableField::duration('cook_time', 'Cook')
        ->sortable()
      )
      ->addField(
        TableField::text('status', 'Status')
        ->align('right')
        ->sortable()
      )
      ->setRows($rows)
      ->setSelectable(true)
      ->addQuickAction(TableAction::view('recipe_view'))
      ->addBulkAction(
        TableAction::create('archive', 'Archive Selected')
          ->setIcon('bi-box')
          ->setVariant('outline-danger')
          ->setConfirmMessage('Are you sure you want to archive the selected recipes?')
      )
      ->addBulkAction(
        TableAction::create('delete', 'Delete Selected')
            ->setIcon('bi-trash')
            ->setVariant('outline-danger')
            ->setConfirmMessage('Are you sure you want to delete the selected recipes? This cannot be undone.')
      )
      ->setSearchPlaceholder('Search recipes by name...')
      ->setEmptyState('No recipes found. Create your first recipe to get started!')
      ->build();
    
    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table'      => $table,
      'bulkRoute'  => 'recipe_bulk',
      'csrfSlug'   => 'recipe_bulk',
    ]));
  }
  
  #[Route('/recipe/view/{id}', name: 'recipe_view')]
  #[DashboardLayout('prep', 'recipe', 'recipe-table')]
  public function view(Recipe $recipe, Request $request): Response
    {
        $recipe_ingredients = array();

        foreach ($recipe->getRecipeIngredients() as $ingredient) {
          if ($ingredient->getSupplyType() === 'item') {
            $item = $this->itemRepo->findItemById($ingredient->getSupplyId());
            $ingredient->supplyLabel = $item ? $item->getName() : 'Unknown ingredient';
          } else {
            $ingredient->supplyLabel = 'Unknown ingredient';
          }
          array_push($recipe_ingredients, $ingredient);
        }
          
        return $this->render('katzen/recipe/view.html.twig', $this->dashboardContext->with([
          'activeItem' => 'recipe-list',
          'activeMenu' => 'menu',
          'recipe' => $recipe,
          'recipe_ingredients' => $recipe_ingredients,
        ]));
    }

  #[Route('/recipe/build', name: 'recipe_build')]
  #[DashboardLayout('prep', 'recipe', 'recipe-build')]
  public function build(Request $request): Response
    {

    $recipe = new Recipe();
    $form   = $this->createForm(RecipeBuilderType::class, $recipe);

    $form->handleRequest($request);

    if ($form->isSubmitted() && !$form->isValid()) {
      $messages = [];
      foreach ($form->getErrors(true) as $err) { $messages[] = $err->getMessage(); }
      $this->addFlash('danger', implode("\n", array_unique($messages)));
      return $this->render('katzen/recipe/build.html.twig', $this->dashboardContext->with([
        'activeItem' => 'recipe-list',
        'activeMenu' => 'menu',
        'recipe_form' => $form,
      ]));
    }

    if ($form->isSubmitted() && $form->isValid()) {
        $recipe->setAuthor($this->getUser());
        $recipe->setStatus('draft');
        $recipe->setVersion(1); // TODO: getLatestVersion

        $this->recipeRepo->save($recipe, true);

        return $this->redirectToRoute('recipe_view', ['id' => $recipe->getId()]);
    }
    
	return $this->render('katzen/recipe/build.html.twig', $this->dashboardContext->with([
      'recipe_form'     => $form,
	]));
    }

  #[Route('/recipe/create', name: 'recipe_create')]
  #[DashboardLayout('prep', 'recipe', 'recipe-create')]
  public function create(Request $request, CreateRecipeFlow $flow): Response
  {
    $recipe = new Recipe();

	$flow->bind($recipe);    
	$form = $flow->createForm();

	if ($flow->isValid($form)) {
      $flow->saveCurrentStepData($form);
      
      if ($flow->nextStep()) {
        $form = $flow->createForm();
      } else {
        $recipe->setAuthor($this->getUser());
        $recipe->setStatus('draft');
        $recipe->setVersion(1); // TODO: getLatestVersion
        
        $this->recipeRepo->save($recipe);
        
        $flow->reset();        
        return $this->redirectToRoute('recipe_table');
      }
	}

    return $this->render('katzen/recipe/create_recipe_flow.html.twig', $this->dashboardContext->with([
      'form' => $form->createView(),
      'flow' => $flow,
    ]));
  }
  
  #[Route('/recipe/import', name: 'recipe_import', methods: ['GET', 'POST'])]
  #[DashboardLayout('prep', 'recipe', 'recipe-import')]
  public function import(Request $request, RecipeImportService $recipeImportService): Response
  {
        $form = $this->createForm(ImportRecipeType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
          $file = $form->get('file')->getData();
          $jsonText = $form->get('json_text')->getData();
          try {
            if ($file) {
              $recipeImportService->importFile($file);
              $this->addFlash('success', 'Recipe imported successfully from file!');
              
                return $this->redirectToRoute('app_recipe');                
            }
            elseif ($jsonText) {
              $jsonData = json_decode($jsonText, true);
              if (!$jsonData) {
                $parser = new JsonParser();
                $parsingException = $parser->lint($jsonText);
                throw new \Exception($parsingException->getMessage());
              }
              $recipeImportService->importData($jsonData);
              $this->addFlash('success', 'Recipe imported successfully from pasted JSON!');
              
              return $this->redirectToRoute('app_recipe');
            }
            else {
              throw new \Exception("Please upload a file or paste JSON.");
            }
          } catch (\Exception $e) {
            // TODO: better error messages for formatting vs content errors
            $this->addFlash('error', 'Error importing recipe: ' . $e->getMessage());
          }
          // Don't redirect on import errors
        }
        
        return $this->render('katzen/recipe/import.html.twig', $this->dashboardContext->with([
          'activeItem' => 'recipe-list',
          'activeMenu' => 'menu',
          'form' => $form->createView(),
        ]));
    }

  #[Route('/recipe/delete/{id}', name: 'recipe_delete', methods: ['POST'])]
  public function delete(Request $request, Recipe $recipe): Response
  {
    $this->denyAccessUnlessGranted('ROLE_USER');
    
    $token = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('delete_recipe_' . $recipe->getId(), $token)) {
      throw $this->createAccessDeniedException('Invalid CSRF token.');
    }
    
    $mode = DeleteMode::from($request->request->get('mode', DeleteMode::BLOCK_IF_REFERENCED->value));

    try {
      $report = $this->deletionPolicy->preflight($recipe, $mode);
      
      if (!$report->ok) {
        $this->addFlash('danger', sprintf(
          "Can't delete: %s",
          implode(' ', $report->reasons)
        ));
        return $this->redirectToRoute('recipe_view', ['id' => $recipe->getId()]);
      }

      $this->deletionPolicy->execute($recipe, $mode);
      
      $this->addFlash('success', sprintf(
        'Recipe "%s" deleted successfully.',
        $recipe->getTitle()
      ));
      
      return $this->redirectToRoute('recipe_table');
      
    } catch (\Throwable $e) {
      $this->addFlash('danger', 'Delete failed: ' . $e->getMessage());
      return $this->redirectToRoute('recipe_view', ['id' => $recipe->getId()]);
    }
  }
}

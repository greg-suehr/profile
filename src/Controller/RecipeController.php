<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Form\ImportRecipeType;
use App\Form\RecipeBuilderType;
use App\Form\CreateRecipeFlow;
use App\Repository\ItemRepository;
use App\Repository\RecipeRepository;
use App\Service\RecipeImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeController extends AbstractController
{
  #[Route('/recipe', name: 'app_recipe')]
  public function index(): Response
  {
        return $this->render('recipe/index.html.twig', [
          'controller_name' => 'RecipeController',
        ]);
    }

  #[Route('/katzen', name: 'app_landing')]
  public function landing_page(): Response
  {
        return $this->render('recipe/index.html.twig', [
          'controller_name' => 'RecipeController',
        ]);
    }

  #[Route('/recipe/list', name: 'app_recipe_list')]
    public function list(Request $request, RecipeRepository $recipeRepo): Response
    {
        
        return $this->render('recipe/list.html.twig', [
          'controller_name' => 'RecipeController',
          'recipes'         => $recipeRepo->findAll(),
        ]);
    }

  #[Route('/recipe/show/{id}', name: 'app_recipe_show')]
  public function show(Request $request, Recipe $recipe, ItemRepository $itemRepo): Response
    {
        $recipe_ingredients = array();

        foreach ($recipe->getRecipeIngredients() as $ingredient) {
          if ($ingredient->getSupplyType() === 'item') {
            $item = $itemRepo->findItemById($ingredient->getSupplyId());
            $ingredient->supplyLabel = $item ? $item->getName() : 'Unknown ingredient';
          } else {
            $ingredient->supplyLabel = 'Unknown ingredient';
          }
          array_push($recipe_ingredients, $ingredient);
        }
          
        return $this->render('recipe/show.html.twig', [
          'controller_name' => 'RecipeController',
          'recipe' => $recipe,
          'recipe_ingredients' => $recipe_ingredients,
        ]);
    }

  #[Route('/recipe/build', name: 'app_recipe_build')]
    public function build(Request $request, RecipeRepository $recipeRepo): Response
    {

    $recipe = new Recipe();
    $form   = $this->createForm(RecipeBuilderType::class, $recipe);

    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $recipe->setAuthor($this->getUser());
        $recipe->setStatus('draft');
        $recipe->setVersion(1); // TODO: getLatestVersion
        $recipe->setCreatedAt(new \DateTimeImmutable());
        $recipe->setUpdatedAt(new \DateTime());

        $recipeRepo->save($recipe, true);

        return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
    }
    
	return $this->render('recipe/build.html.twig', [
          'controller_name' => 'RecipeController',
          'recipe_form'     => $form,
	]);
    }

   #[Route('/recipe/create', name: 'app_recipe_create')]
  public function create(Request $request, RecipeRepository $recipeRepo, CreateRecipeFlow $flow): Response
  {
    $recipe = new Recipe();

	$flow->bind($recipe);    
	$form = $flow->createForm();

	if ($flow->isValid($form)) {
      $flow->saveCurrentStepData($form);
      
      if ($flow->nextStep()) {
        //        dd($flow->getCurrentStepNumber());
        $form = $flow->createForm();
      } else {
        // flow finished
        $recipeRepo->save($recipe);
        
        $flow->reset(); // remove step data from the session
        
        return $this->redirectToRoute('recipe'); // redirect when done
      }
	}

  return $this->render('recipe/create_recipe_flow.html.twig', [
		'form' => $form->createView(),
		'flow' => $flow,
	]);
}
  
  #[Route('/recipe/import', name: 'app_recipe_import', methods: ['GET', 'POST'])]
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
              } elseif ($jsonText) {
                $jsonData = json_decode($jsonText, true);
                if (!$jsonData) {
                  throw new \Exception("Invalid JSON format.");
                }
                $recipeImportService->importData($jsonData);
                $this->addFlash('success', 'Recipe imported successfully from pasted JSON!');
              } else {
                throw new \Exception("Please upload a file or paste JSON.");
              }
            } catch (\Exception $e) {
              $this->addFlash('error', 'Error importing recipe: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_recipe');
        }

        return $this->render('recipe/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

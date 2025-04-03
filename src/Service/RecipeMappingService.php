<?php
namespace App\Service;

use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeInstruction;
use App\Entity\Item;
use App\Entity\Unit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RecipeMappingService
{
    private EntityManagerInterface $entityManager;
  
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    private function mapAndStoreOneIngredient(array $ingredientData, ?Recipe $recipe) {
        if (empty($ingredientData['name'])) {
          throw new \Exception("Ingredient name cannot be empty." . "\n" . $ingredientData);
        }
        
        $ingredient = new RecipeIngredient();
        $ingredient->setRecipe($recipe);
        $ingredient->setSupplyType('item');
        $item = $this->entityManager->getRepository(Item::class)->findOneBy(['name' => $ingredientData['name']]);
        if (!$item) {
          throw new \Exception("Ingredient '{$ingredientData['name']}' not found in database.");
        }
        $ingredient->setSupplyId($item->getId()); // TODO: clean up relations like this
        $ingredient->setQuantity($ingredientData['quantity']);
        $unit = $this->entityManager->getRepository(Unit::class)->findOneBy(['name' => $ingredientData['unit']]);
        if (!$unit) {
          throw new \Exception("Unit '{$ingredientData['unit']}' not found in database.");
        }
        $ingredient->setUnit($unit);
        $ingredient->setNote($ingredientData['note'] ?? '');
        
        $this->entityManager->persist($ingredient);
    }

    public function mapAndStore(array $data)
    {
        $recipe = new Recipe();
        $recipe->setTitle($data['title']);
        $recipe->setSummary($data['summary'] ?? '');
        $recipe->setServingMinQty($data['servings']['min'] ?? 1);
        $recipe->setServingMaxQty($data['servings']['max'] ?? null);
        $recipe->setPrepTime($data['prep_time'] ?? 0);
        $recipe->setCookTime($data['cook_time'] ?? 0);
        $recipe->setWaitTime($data['wait_time'] ?? 0);
        $recipe->setCreatedAt(new \DateTimeImmutable());
        $recipe->setUpdatedAt(new \DateTime());
        // TODO: implement a getLatestVersion
        $recipe->setVersion(1);
        // TODO: build import validation workflow
        $recipe->setStatus('imported');
        // TODO: build publishing and saving workflow
        $recipe->setIsPublic(false);
        // TODO: define "servings" key for RecipeImporter data object
        $unit = $this->entityManager->getRepository(Unit::class)->findOneBy(['name' => $data['servings']['unit']]);
        $recipe->setServingUnit($unit);
        

        if (isset($data['author'])) {
            $author = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $data['author']]);
            if (!$author) {
                throw new \Exception("Author '{$data['author']}' not found in database.");
            }
            $recipe->setAuthor($author);
        } else {
            $author = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'greg']);
            $recipe->setAuthor($author);   
        }

        $this->entityManager->persist($recipe);
        $this->entityManager->flush(); // generates the recipe_id
        
        foreach ($data['ingredients'] as $ingredientData) {
          if ( array_key_exists('section', $ingredientData) ) {
            foreach ($ingredientData['items'] as $nestedIngredientData) {
              $this->mapAndStoreOneIngredient($nestedIngredientData, $recipe);
            }
          }
          else {
            $this->mapAndStoreOneIngredient($ingredientData, $recipe);
          }
        }

        foreach ($data['instructions'] as $instructionData) {
            $instruction = new RecipeInstruction();
            $instruction->setRecipe($recipe);
            $instruction->setSectionNumber($instructionData['section']);
            $instruction->setStepNumber($instructionData['step']);
            $instruction->setDescription($instructionData['description']);
            // TODO: define "times" key for RecipeImporter\Instruction data object  
            $instruction->setPrepTime(1.0);
            $instruction->setCookTime(1.0);
            $instruction->setWaitTime(1.0);

            $this->entityManager->persist($instruction);
        }

        $this->entityManager->flush();

        return $recipe;
    }
}

?>

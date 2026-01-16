<?php
namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;

class IngredientValidator
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Validate ingredients against the Item table.
     * @param string[] $ingredientNames
     * @param bool $autoInsert - if true, insert unknowns
     * @return string[] - list of unknown ingredient names (or newly inserted)
     */
    public function validate(array $ingredientNames, bool $autoInsert = false): array
    {
        $unknownIngredients = [];

        // Normalize and de-duplicate
        $ingredientNames = array_unique(array_map('trim', $ingredientNames));
        if (empty($ingredientNames)) return [];

        // Fetch all known items at once
        $knownItems = $this->entityManager->getRepository(Item::class)
            ->createQueryBuilder('i')
            ->where('i.name IN (:names)')
            ->setParameter('names', $ingredientNames)
            ->getQuery()
            ->getResult();

        $knownNames = array_map(fn($item) => strtolower($item->getName()), $knownItems);

        foreach ($ingredientNames as $name) {
            if (!in_array(strtolower($name), $knownNames, true)) {
                $unknownIngredients[] = $name;

                if ($autoInsert) {
                    $item = new Item();
                    $item->setName($name);
                    // TODO: make mapping unknown ingredients a Workflow, then make it smart
                    $item->setCategory('imported');
                    $this->entityManager->persist($item);
                }
            }
        }

        if ($autoInsert && count($unknownIngredients)) {
            $this->entityManager->flush();
        }

        return $unknownIngredients;
    }
}

<?php
namespace App\Katzen\Service\Inventory;

use App\Katzen\Entity\Unit;
use Doctrine\ORM\EntityManagerInterface;

class UnitValidator
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Validate units against the Unit table.
     * @param string[] $unitNames
     * @param bool $autoInsert - if true, insert unknowns
     * @return string[] - list of unknown unit names (or newly inserted)
     */
    public function validate(array $unitNames, bool $autoInsert = false): array
    {
        $unknownUnits = [];
        $eachUnit = $this->entityManager->getRepository(Unit::class)->findOneBy(['name' => 'each']);

        // Normalize and de-duplicate
        $unitNames = array_unique(array_map('trim', $unitNames));
        if (empty($unitNames)) return [];

        // Fetch all known units at once
        $knownUnits = $this->entityManager->getRepository(Unit::class)
            ->createQueryBuilder('i')
            ->where('i.name IN (:names)')
            ->setParameter('names', $unitNames)
            ->getQuery()
            ->getResult();

        $knownNames = array_map(fn($unit) => strtolower($unit->getName()), $knownUnits);

        foreach ($unitNames as $name) {
            if (!in_array(strtolower($name), $knownNames, true)) {
                $unknownUnits[] = $name;

                if ($autoInsert) {
                    $unit = new Unit();
                    $unit->setName($name);
                    $unit->setAbbreviation('');
                    $unit->setCategory('imported');
                    $unit->setBaseUnitId($eachUnit->getId());
                    $unit->setConversionFactor(1.00);
                    $this->entityManager->persist($unit);
                }
            }
        }

        if ($autoInsert && count($unknownUnits)) {
            $this->entityManager->flush();
        }

        return $unknownUnits;
    }
}

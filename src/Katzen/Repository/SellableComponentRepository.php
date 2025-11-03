<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\SellableComponent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SellableComponent>
 */
class SellableComponentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SellableComponent::class);
    }

    public function save(SellableComponent $component, bool $flush = true): void
    {
        $this->getEntityManager()->persist($component);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
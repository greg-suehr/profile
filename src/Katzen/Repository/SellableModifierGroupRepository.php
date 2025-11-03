<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\SellableModifierGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SellableModifierGroup>
 */
class SellableModifierGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SellableModifierGroup::class);
    }

    public function save(SellableModifierGroup $group, bool $flush = true): void
    {
        $this->getEntityManager()->persist($group);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
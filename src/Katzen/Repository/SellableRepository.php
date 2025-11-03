<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Sellable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sellable>
 */
class SellableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sellable::class);
    }

    public function save(Sellable $sellable, bool $flush = true): void
    {
        $this->getEntityManager()->persist($sellable);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Sellable $sellable, bool $flush = true): void
    {
        $this->getEntityManager()->remove($sellable);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveBySku(string $sku): ?Sellable
    {
        return $this->findOneBy(['sku' => $sku, 'status' => 'active']);
    }

    public function findActiveByCategory(string $category): array
    {
        return $this->findBy(['category' => $category, 'status' => 'active'], ['name' => 'ASC']);
    }

    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type, 'status' => 'active'], ['name' => 'ASC']);
    }
}
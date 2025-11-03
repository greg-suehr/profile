<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\SellableVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SellableVariant>
 */
class SellableVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SellableVariant::class);
    }

    public function save(SellableVariant $variant, bool $flush = true): void
    {
        $this->getEntityManager()->persist($variant);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SellableVariant $variant, bool $flush = true): void
    {
        $this->getEntityManager()->remove($variant);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveBySku(string $sku): ?SellableVariant
    {
        return $this->findOneBy(['sku' => $sku, 'status' => 'active']);
    }
}

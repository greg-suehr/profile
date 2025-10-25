<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockReceiptItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockReceiptItem>
 */
class StockReceiptItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockReceiptItem::class);
  }

  public function save(StockReceiptItem $entity, bool $flush = true): void
  {
    $this->getEntityManager()->persist($entity);

    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }

  public function remove(StockReceiptItem $entity, bool $flush = true): void
  {
    $this->getEntityManager()->remove($entity);

    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }
}

<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockCountItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockCountItem>
 */
class StockCountItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockCountItem::class);
  }

  public function add(StockCountItem $item): void
  {
    $this->getEntityManager()->persist($item);
  }

  public function save(StockCountItem $item): void
  {
    $this->getEntityManager()->persist($item);
    $this->getEntityManager()->flush();
  }

//    /**
//     * @return StockCountItem[] Returns an array of StockCountItem objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?StockCountItem
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

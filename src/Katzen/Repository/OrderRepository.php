<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

  public function findByStatus($value): array
  {
    return $this->createQueryBuilder('o')
                 ->andWhere('o.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('o.id', 'ASC')
                 ->setMaxResults(10)
                 ->getQuery()
                 ->getResult()
            ;
        }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    //    /**
    //     * @return Item[] Returns an array of Item objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }
  
    //    public function findOneBySomeField($value): ?Item
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
  
    public function findItemById(?int $id): ?Item
    {
        return $this->createQueryBuilder('me')
          ->andWhere('me.id = :val')
          ->setParameter('val', $id)
          ->getQuery()
          ->getOneOrNullResult();
    }

    /**
     * @return Item[]
     */
    public function searchByName(string $name): array
    {
      return $this->createQueryBuilder('i')
        ->where('LOWER(i.name) LIKE LOWER(:val)')
        ->setParameter('val', '%' . $name . '%')
        ->orderBy('i.name', 'ASC')
        ->setMaxResults(10)
        ->getQuery()
        ->getResult();
    }
}

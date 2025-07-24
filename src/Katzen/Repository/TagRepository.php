<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

  /**
   * @return Tag Returns a single Tag object or null
   */
  public function findOneByType($obj, $id, $type): ?Tag
  {
        return $this->createQueryBuilder('t')
            ->andWhere('t.obj = :obj')
            ->setParameter('obj', $obj)
            ->andWhere('t.obj_id = :id')
            ->setParameter('id', $id)
            ->andWhere('t.type = :type')
            ->setParameter('type', $type)          
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

  /**
   * @return Tag[] Returns an array of Tag objects
   */
  public function findByObj($obj, $id): array
  {
        return $this->createQueryBuilder('t')
            ->andWhere('t.obj = :obj')
            ->setParameter('obj', $obj)
            ->andWhere('t.obj_id = :id')
            ->setParameter('id', $id)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?Tag
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

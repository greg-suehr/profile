<?php

namespace App\Shared\Repository;

use App\Shared\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }
  
  public function findRecent(): array
  {
      return $this->createQueryBuilder('me')
        ->andWhere('me.is_published = :published')
        ->setParameter('published', true)        
        ->orderBy('me.created_at', 'DESC')
        ->setMaxResults(10)
        ->getQuery()
        ->getResult()
        ;
  }

  public function getFeature(): ?BlogPost
  {
      return $this->createQueryBuilder('me')
        ->andWhere('me.is_published = :published')
        ->setParameter('published', true)
        ->orderBy('me.created_at', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult()
        ;
  }


//    /**
//     * @return BlogPost[] Returns an array of BlogPost objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BlogPost
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

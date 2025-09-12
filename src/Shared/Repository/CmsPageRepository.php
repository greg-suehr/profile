<?php

namespace App\Shared\Repository;

use App\Shared\Entity\CmsPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CmsPage>
 */
class CmsPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CmsPage::class);
    }

  public function findOneBySlug($value): ?CmsPage
  {
    return $this->createQueryBuilder('c')
      ->andWhere('c.slug = :val')
      ->setParameter('val', $value)
      ->getQuery()
      ->getOneOrNullResult()
      ;
    }
}

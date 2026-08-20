<?php

namespace App\Shared\Repository;

use App\Shared\Entity\Essay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Essay>
 */
class EssayRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Essay::class);
  }

  /** @return Essay[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('e.year', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  public function findOnePublishedBySlug(string $slug): ?Essay
  {
    return $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->andWhere('e.isPublished = :pub')
            ->setParameter('slug', $slug)
            ->setParameter('pub', true)
            ->getQuery()
            ->getOneOrNullResult();
  }
}

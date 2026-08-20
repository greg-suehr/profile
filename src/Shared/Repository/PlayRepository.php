<?php

namespace App\Shared\Repository;

use App\Shared\Entity\Play;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Play>
 */
class PlayRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Play::class);
  }

  /** @return Play[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('p.premiereYear', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  public function findOnePublishedBySlug(string $slug): ?Play
  {
    return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isPublished = :pub')
            ->setParameter('slug', $slug)
            ->setParameter('pub', true)
            ->getQuery()
            ->getOneOrNullResult();
  }
}

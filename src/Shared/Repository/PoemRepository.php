<?php

namespace App\Shared\Repository;

use App\Shared\Entity\Poem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poem>
 */
class PoemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Poem::class);
  }

  /** @return Poem[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  public function findOnePublishedBySlug(string $slug): ?Poem
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

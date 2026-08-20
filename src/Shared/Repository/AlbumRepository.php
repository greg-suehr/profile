<?php

namespace App\Shared\Repository;

use App\Shared\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Album::class);
  }

  /** @return Album[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('a')
            ->andWhere('a.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('a.releaseYear', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  public function findOnePublishedBySlug(string $slug): ?Album
  {
    return $this->createQueryBuilder('a')
            ->andWhere('a.slug = :slug')
            ->andWhere('a.isPublished = :pub')
            ->setParameter('slug', $slug)
            ->setParameter('pub', true)
            ->getQuery()
            ->getOneOrNullResult();
  }
}

<?php

namespace App\Shared\Repository;

use App\Shared\Entity\Song;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Song>
 */
class SongRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Song::class);
  }

  /** @return Song[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('s')
            ->andWhere('s.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  public function findOnePublishedBySlug(string $slug): ?Song
  {
    return $this->createQueryBuilder('s')
            ->andWhere('s.slug = :slug')
            ->andWhere('s.isPublished = :pub')
            ->setParameter('slug', $slug)
            ->setParameter('pub', true)
            ->getQuery()
            ->getOneOrNullResult();
  }
}

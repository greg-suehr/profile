<?php

namespace App\Shared\Repository;

use App\Shared\Entity\ProfileInfluence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileInfluence>
 */
class ProfileInfluenceRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ProfileInfluence::class);
  }

  /**
   * @return ProfileInfluence[] published entries, ordered by sortOrder then name
   */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('i')
            ->andWhere('i.isPublished = :pub')
            ->setParameter('pub', true)
            ->addOrderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
  }

  /** @return string[] distinct tags across all published entries */
  public function findAllTags(): array
  {
    $items = $this->findPublished();
    $tags  = [];
    foreach ($items as $item) {
      foreach ($item->getTags() as $tag) {
        $tags[$tag] = true;
      }
    }
    ksort($tags);
    return array_keys($tags);
  }

  /** @return string[] distinct domains across all published entries */
  public function findAllDomains(): array
  {
    $items = $this->findPublished();
    $domains = [];
    foreach ($items as $item) {
      $d = $item->getDomain();
      if ($d) {
        $domains[$d] = true;
      }
    }
    ksort($domains);
    return array_keys($domains);
  }
}

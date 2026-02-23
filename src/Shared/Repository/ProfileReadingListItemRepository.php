<?php

namespace App\Shared\Repository;

use App\Shared\Entity\ProfileReadingListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileReadingListItem>
 */
class ProfileReadingListItemRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ProfileReadingListItem::class);
  }

  /** @return ProfileReadingListItem[] all items, most-recently-added first */
  public function findAllOrdered(): array
  {
    return $this->createQueryBuilder('r')
            ->orderBy('r.status', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }

  /** @return string[] distinct tags across all items */
  public function findAllTags(): array
  {
    $items = $this->findAll();
    $tags  = [];
    foreach ($items as $item) {
      foreach ($item->getTags() as $tag) {
        $tags[$tag] = true;
      }
    }
    ksort($tags);
    return array_keys($tags);
  }
}

<?php

namespace App\Shared\Repository;

use App\Shared\Entity\ProfileDocumentView;
use App\Shared\Entity\ProfileDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileDocumentView>
 */
class ProfileDocumentViewRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ProfileDocumentView::class);
  }

  public function countByDocument(ProfileDocument $document): int
  {
    return (int) $this->createQueryBuilder('v')
      ->select('COUNT(v.id)')
      ->andWhere('v.document = :doc')
      ->setParameter('doc', $document)
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Returns view counts keyed by document ID.
   * @return array<int, int>
   */
  public function countAllGrouped(): array
  {
    $rows = $this->createQueryBuilder('v')
      ->select('IDENTITY(v.document) AS docId, COUNT(v.id) AS viewCount')
      ->groupBy('v.document')
      ->getQuery()
      ->getResult();

    $map = [];
    foreach ($rows as $row) {
      $map[(int) $row['docId']] = (int) $row['viewCount'];
    }
    return $map;
  }
}

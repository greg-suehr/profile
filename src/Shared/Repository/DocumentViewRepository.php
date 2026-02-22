<?php

namespace App\Shared\Repository;

use App\Shared\Entity\DocumentView;
use App\Shared\Entity\ResearchDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentView>
 */
class DocumentViewRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, DocumentView::class);
  }

  public function countByDocument(ResearchDocument $document): int
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
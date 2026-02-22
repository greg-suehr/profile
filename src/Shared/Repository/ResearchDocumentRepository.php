<?php

namespace App\Shared\Repository;

use App\Shared\Entity\ResearchDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResearchDocument>
 */
class ResearchDocumentRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ResearchDocument::class);
  }

  /** @return ResearchDocument[] */
  public function findPublished(): array
  {
    return $this->createQueryBuilder('d')
            ->andWhere('d.isPublished = :pub')
            ->setParameter('pub', true)
            ->orderBy('d.year', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
  }
}

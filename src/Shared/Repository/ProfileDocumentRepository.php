<?php

namespace App\Shared\Repository;

use App\Shared\Entity\ProfileDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileDocument>
 */
class ProfileDocumentRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, ProfileDocument::class);
  }

  /** @return ProfileDocument[] */
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

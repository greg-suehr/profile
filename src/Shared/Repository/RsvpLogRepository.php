<?php

namespace App\Shared\Repository;

use App\Shared\Entity\RsvpLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RsvpLog>
 */
class RsvpLogRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
      parent::__construct($registry, RsvpLog::class);
  }

  public function add(RsvpLog $entity, bool $flush = false): void
  {
    $this->getEntityManager()->persist($entity);
    
    if ($flush) {
      $this->getEntityManager()->flush();
    }
  }
}

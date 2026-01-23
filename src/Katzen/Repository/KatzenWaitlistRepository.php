<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\KatzenWaitlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KatzenWaitlist>
 */
class KatzenWaitlistRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, KatzenWaitlist::class);
  }
}

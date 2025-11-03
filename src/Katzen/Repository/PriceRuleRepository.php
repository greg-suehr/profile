<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\PriceRule;
use App\Katzen\Entity\Sellable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceRule>
 */
class PriceRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceRule::class);
    }

    public function save(PriceRule $rule, bool $flush = true): void
    {
        $this->getEntityManager()->persist($rule);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active rules applicable to a sellable, ordered by priority
     */
    public function findApplicableRules(Sellable $sellable, \DateTimeInterface $effectiveDate): array
    {
        return $this->createQueryBuilder('pr')
            ->leftJoin('pr.applicableSellables', 's')
            ->where('pr.status = :status')
            ->andWhere('(pr.validFrom IS NULL OR pr.validFrom <= :date)')
            ->andWhere('(pr.validTo IS NULL OR pr.validTo >= :date)')
            ->andWhere('(s.id = :sellableId OR SIZE(pr.applicableSellables) = 0)')
            ->setParameter('status', 'active')
            ->setParameter('date', $effectiveDate)
            ->setParameter('sellableId', $sellable->getId())
            ->orderBy('pr.priority', 'DESC')
            ->addOrderBy('pr.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
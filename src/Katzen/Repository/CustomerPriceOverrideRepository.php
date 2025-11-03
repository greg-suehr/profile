<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\CustomerPriceOverride;
use App\Katzen\Entity\Sellable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPriceOverride>
 */
class CustomerPriceOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPriceOverride::class);
    }

    public function save(CustomerPriceOverride $override, bool $flush = true): void
    {
        $this->getEntityManager()->persist($override);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active override for a customer and sellable at a specific date
     */
    public function findActiveOverride(
        Customer $customer,
        Sellable $sellable,
        \DateTimeInterface $effectiveDate
    ): ?CustomerPriceOverride {
        return $this->createQueryBuilder('cpo')
            ->where('cpo.customer = :customer')
            ->andWhere('cpo.sellable = :sellable')
            ->andWhere('cpo.validFrom <= :date')
            ->andWhere('(cpo.validTo IS NULL OR cpo.validTo >= :date)')
            ->setParameter('customer', $customer)
            ->setParameter('sellable', $sellable)
            ->setParameter('date', $effectiveDate)
            ->orderBy('cpo.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
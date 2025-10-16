<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Customer::class);
  }

  public function flush(): void
  {
    $this->getEntityManager()->flush();
  }

  public function save(Customer $customer): void
  {
    $this->getEntityManager()->persist($customer);
    $this->getEntityManager()->flush();
  }

  public function findByStatus($value): array
  {
    return $this->createQueryBuilder('c')
                 ->andWhere('c.status = :val')
                 ->setParameter('val', $value)
                 ->orderBy('c.id', 'ASC')
                 ->getQuery()
                 ->getResult();
  }

  public function findActive(): array
  {
    return $this->findByStatus('active');
  }
}

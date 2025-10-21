<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\VendorInvoiceItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VendorInvoiceItem>
 */
class VendorInvoiceItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VendorInvoiceItem::class);
    }
}

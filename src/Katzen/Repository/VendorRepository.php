<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\Vendor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * @extends ServiceEntityRepository<Vendor>
 */
class VendorRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Vendor::class);
  }

  public function save(Vendor $vendor): void
  {
    $this->getEntityManager()->persist($vendor);
    $this->getEntityManager()->flush();
  }

  /**
   * Find vendors by phone number suffix (last 10 digits)
   * 
   * @param string $e164ish Phone number suffix (e.g., "4125550100")
   * @return Vendor[]
   */
  public function findByE164Suffix(string $e164ish): array
  {
    $suffix = preg_replace('/\D/', '', $e164ish);
      
    return $this->createQueryBuilder('v')
      ->where('v.phone_digits IS NOT NULL')
      ->andWhere('SUBSTRING(v.phone_digits, LENGTH(v.phone_digits)-9,10) = :suffix')
      ->setParameter('suffix', substr($suffix, -10))
      ->getQuery()
      ->getResult();
  }

  /**
   * Find vendors by domain
   * Uses JSON search onE164Suffix indexed vendor_domains field
   * 
   * @param string $domain Domain to search (e.g., "sysco.com")
   * @return Vendor[]
   */
  public function findByDomain(string $domain): array
  {
    $domain = strtolower(trim($domain));

    # TODO: not this?
    $sql = <<<SQL
SELECT v.*
FROM vendor v
WHERE EXISTS (
  SELECT 1
  FROM jsonb_array_elements_text((v.vendor_domains)::jsonb) AS d(dom)
  WHERE lower(d.dom) = lower(:domain)
)
SQL;

    $em  = $this->getEntityManager();
    $rsm = new ResultSetMappingBuilder($em);
    $rsm->addRootEntityFromClassMetadata(Vendor::class, 'v');

    $query = $em->createNativeQuery($sql, $rsm);
    $query->setParameter('domain', $domain);

    return $query->getResult();
  }

  /**
   * Find vendors by tax ID
   * Direct indexed lookup
   * 
   * @param string $taxId Tax ID to search
   * @return Vendor[]
   */
  public function findByTaxId(string $taxId): array
  {
    $normalized = preg_replace('/[\s\-]/', '', $taxId);

    $sql = <<<SQL
SELECT v.*
FROM vendor v
WHERE v.tax_id = :taxId
   OR REPLACE(REPLACE(COALESCE(v.tax_id, ''), ' ', ''), '-', '') LIKE :normalized
SQL;

    $em = $this->getEntityManager();

    $rsm = new ResultSetMappingBuilder($em);
    $rsm->addRootEntityFromClassMetadata(Vendor::class, 'v');

    $query = $em->createNativeQuery($sql, $rsm);
    $query->setParameter('taxId', $taxId);
    $query->setParameter('normalized', '%'.$normalized.'%');

    return $query->getResult();
  }

  /**
   * Find vendors by address hash
   * Uses indexed address_hash field for instant lookup
   * 
   * @param string $hash SHA256 hash of normalized address
   * @return Vendor[]
   */
  public function findByHash(string $hash): array
  {
    return $this->createQueryBuilder('v')
      ->where('v.address_hash = :hash')
      ->setParameter('hash', $hash)
      ->getQuery()
      ->getResult();
  }

  /**
   * Find vendors by postal code
   * Uses indexed postal_code field for fast lookup
   * 
   * @param string $postal Postal code (e.g., "15220")
   * @return Vendor[]
   */
  public function findByPostal(string $postal): array
  {
    // Remove spaces for consistent matching
    $postal = preg_replace('/\s/', '', $postal);

    return $this->createQueryBuilder('v')
      ->where('v.postal_code = :postal')
      ->setParameter('postal', $postal)
      ->getQuery()
      ->getResult();
  }

  
  /**
   * Find vendors by alias/brand name
   * Uses JSON search on vendor_aliases field
   * 
   * @param string $alias Brand name or alias
   * @return Vendor[]
   */
  public function findByAlias(string $alias): array
  {
    $alias = strtolower(trim($alias));

    $sql = <<<SQL
SELECT v.*
FROM vendor v
WHERE EXISTS (
  SELECT 1
  FROM jsonb_array_elements_text((v.vendor_aliases)::jsonb) AS d(dom)
  WHERE lower(d.dom) = lower(:alias)
    )
SQL;

    $em = $this->getEntityManager();

    $rsm = new ResultSetMappingBuilder($em);
    $rsm->addRootEntityFromClassMetadata(Vendor::class, 'v');

    $query = $em->createNativeQuery($sql, $rsm);
    $query->setParameter('alias', $alias);

    return $query->getResult();
  }


  /**
   * Don't use this. But if you need to refresh OCR data for a populated VendorRepository
   * it can be useful.
   * 
   * Usage: bin/console doctrine:query:sql "SELECT 1" (or create command)
   */
  public function populateOCRFieldsForAll(): int
  {
    $vendors = $this->findAll();
    $count = 0;

    foreach ($vendors as $vendor) {
      $vendor->populateOCRFields();
      $this->getEntityManager()->persist($vendor);
      $count++;

      # floooooosh
      if ($count % 50 === 0) {
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
      }
    }

    $this->getEntityManager()->flush();
    $this->getEntityManager()->clear();

    return $count;
  }
}

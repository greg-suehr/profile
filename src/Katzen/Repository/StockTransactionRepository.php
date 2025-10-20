<?php

namespace App\Katzen\Repository;

use App\Katzen\Entity\StockTarget;
use App\Katzen\Entity\StockTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockTransaction>
 */
class StockTransactionRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, StockTransaction::class);
  }

  public function add(StockTransaction $txn): void
  {
    $this->getEntityManager()->persist($txn);
  }

  public function save(StockTransaction $txn): void
  {
    $this->getEntityManager()->persist($txn);
    $this->getEntityManager()->flush();
  }

  public function sumConsumedSince(StockTarget $target, \DateTimeImmutable $since): ?float
  {
    return (float) $this->createQueryBuilder('t')
        ->select('ABS(SUM(t.qty))')
        ->andWhere('t.stockTarget = :st')
        ->andWhere('t.useType = :ut')
        ->andWhere('t.createdAt >= :since')
        ->setParameter('st', $target)
        ->setParameter('ut', 'consumption')
        ->setParameter('since', $since)
        ->getQuery()
        ->getSingleScalarResult();
  }

  /**
   * Find stock movements for a target with flexible filtering and ordering
   * 
   * Useful for FIFO/LIFO costing, inventory reconciliation, and consumption analysis.
   *
   * @param StockTarget $target The stock item to query
   * @param 'inbound'|'outbound'|'consumption'|'adjustment'|null $direction Filter by movement direction/type
   * @param \DateTimeInterface|null $from Earliest transaction date (inclusive)
   * @param \DateTimeInterface|null $to Latest transaction date (inclusive)
   * @param 'ASC'|'DESC' $ordered Sort order by created date
   * @param int|null $limit Maximum results to return
   * @param int $offset Pagination offset
   * @return StockTransaction[] Array of transactions in requested order
   * 
   * @example
   *   // Get all inbound stock for FIFO costing (oldest first)
   *   $movements = $repo->findStockMovements($target, 'inbound', ordered: 'ASC');
   *   
   *   // Get consumption last month
   *   $from = (new \DateTime('first day of last month'))->setTime(0, 0);
   *   $to = (new \DateTime('last day of last month'))->setTime(23, 59, 59);
   *   $movements = $repo->findStockMovements($target, 'consumption', $from, $to);
   */
  public function findStockMovements(
    StockTarget $target,
    ?string $direction = null,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null,
    string $ordered = 'ASC',
    ?int $limit = null,
    int $offset = 0
  ): array
  {
    $qb = $this->createQueryBuilder('t')
        ->where('t.stockTarget = :target')
        ->setParameter('target', $target);

    // Filter by movement type/direction
    if ($direction !== null) {
      $qb->andWhere('t.useType = :direction')
         ->setParameter('direction', $direction);
    }

    // Filter by date range
    if ($from !== null) {
      $qb->andWhere('t.createdAt >= :from')
         ->setParameter('from', $from);
    }

    if ($to !== null) {
      $qb->andWhere('t.createdAt <= :to')
         ->setParameter('to', $to);
    }

    // Order for FIFO/LIFO cost layering
    $order = strtoupper($ordered) === 'DESC' ? 'DESC' : 'ASC';
    $qb->orderBy('t.createdAt', $order)
       ->addOrderBy('t.id', $order); // Tiebreaker for same-second transactions

    // Pagination
    if ($limit !== null) {
      $qb->setMaxResults($limit);
    }
    $qb->setFirstResult($offset);

    return $qb->getQuery()->getResult();
  }

  /**
   * Find movements within a specific date range for reconciliation or analysis
   *
   * @param StockTarget $target
   * @param \DateTimeInterface $from Inclusive start date
   * @param \DateTimeInterface $to Inclusive end date
   * @param string $ordered 'ASC' or 'DESC'
   * @return StockTransaction[]
   */
  public function findMovementsBetween(
    StockTarget $target,
    \DateTimeInterface $from,
    \DateTimeInterface $to,
    string $ordered = 'ASC'
  ): array
  {
    return $this->findStockMovements($target, null, $from, $to, $ordered);
  }

  /**
   * Get all inbound (purchase/receipt) transactions for an item
   * Ordered oldest-first for FIFO costing analysis
   *
   * @param StockTarget $target
   * @param \DateTimeInterface|null $since Filter to transactions after this date
   * @return StockTransaction[]
   */
  public function findInboundMovements(
    StockTarget $target,
    ?\DateTimeInterface $since = null
  ): array
  {
    return $this->findStockMovements($target, 'inbound', $since, ordered: 'ASC');
  }

  /**
   * Get all outbound (consumption/usage) transactions for an item
   * Useful for understanding consumption patterns
   *
   * @param StockTarget $target
   * @param \DateTimeInterface|null $from
   * @param \DateTimeInterface|null $to
   * @return StockTransaction[] Ordered newest-first
   */
  public function findOutboundMovements(
    StockTarget $target,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null
  ): array
  {
    return $this->findStockMovements($target, 'outbound', $from, $to, ordered: 'DESC');
  }

  /**
   * Calculate running balance (quantity on hand) at a specific point in time
   * Includes all movements up to and including the date provided
   *
   * @param StockTarget $target
   * @param \DateTimeInterface $asOf Date to calculate balance as of
   * @return float Signed quantity (inbound positive, outbound negative)
   */
  public function getBalanceAsOf(
    StockTarget $target,
    \DateTimeInterface $asOf
  ): float
  {
    $result = $this->createQueryBuilder('t')
        ->select('COALESCE(SUM(CAST(t.qty AS decimal)), 0)')
        ->where('t.stockTarget = :target')
        ->andWhere('t.createdAt <= :asOf')
        ->setParameter('target', $target)
        ->setParameter('asOf', $asOf)
        ->getQuery()
        ->getSingleScalarResult();

    return (float) $result;
  }

  /**
   * Get transaction history with cumulative balance
   * Useful for aging analysis and inventory reconciliation reports
   *
   * @param StockTarget $target
   * @param \DateTimeInterface|null $from
   * @param \DateTimeInterface|null $to
   * @return array<array{transaction: StockTransaction, running_balance: float}>
   */
  public function findMovementsWithRunningBalance(
    StockTarget $target,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null
  ): array
  {
    // Get all movements in chronological order
    $movements = $this->findStockMovements($target, null, $from, $to, 'ASC');

    // Calculate running balance
    $balance = 0;
    $result = [];

    foreach ($movements as $txn) {
      $balance += (float) $txn->getQty();
      $result[] = [
        'transaction' => $txn,
        'running_balance' => $balance,
      ];
    }

    return $result;
  }

  /**
   * Aggregate movements by type for a period (e.g., "how many units consumed in Q3?")
   *
   * @param StockTarget $target
   * @param \DateTimeInterface|null $from
   * @param \DateTimeInterface|null $to
   * @return array<string, float> Map of use_type => total_qty
   */
  public function sumByType(
    StockTarget $target,
    ?\DateTimeInterface $from = null,
    ?\DateTimeInterface $to = null
  ): array
  {
    $qb = $this->createQueryBuilder('t')
        ->select('t.useType, SUM(CAST(t.qty AS decimal)) as total_qty')
        ->where('t.stockTarget = :target')
        ->setParameter('target', $target)
        ->groupBy('t.useType');

    if ($from !== null) {
      $qb->andWhere('t.createdAt >= :from')
         ->setParameter('from', $from);
    }

    if ($to !== null) {
      $qb->andWhere('t.createdAt <= :to')
         ->setParameter('to', $to);
    }

    $results = $qb->getQuery()->getResult();

    // Convert to associative array
    $summary = [];
    foreach ($results as $row) {
      $summary[$row['useType'] ?? 'unknown'] = (float) $row['total_qty'];
    }

    return $summary;
  }
}

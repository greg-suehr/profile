<?php

namespace App\Katzen\Service\Accounting;

use App\Katzen\Entity\PriceAlert;
use App\Katzen\Entity\PriceHistory;
use App\Katzen\Entity\StockTarget;
use App\Katzen\Repository\PriceAlertRepository;
use App\Katzen\Repository\PriceHistoryRepository;

use App\Katzen\Service\Response\ServiceResponse;

use Doctrine\ORM\EntityManagerInterface;


final class PriceAlertService
{
  public function __construct(
    private EntityManagerInterface $em,
    private PriceAlertRepository $alertRepo,
    private PriceHistoryRepository $priceHistoryRepo,
    private CostingService $costing,
  ) {}
  
  /**
   * Create a new price alert
   */
  public function createAlert(
    StockTarget $stockTarget,
    string $alertType,
    ?float $thresholdValue = null,
    ?array $notifyUsers = null,
    ?string $notifyEmail = null
  ): ServiceResponse
  {
    try {
      $validTypes = ['threshold_pct', 'threshold_abs', 'trend_increase', 'all_time_high'];
      
      if (!in_array($alertType, $validTypes)) {
        return ServiceResponse::failure(
          errors: ['Invalid alert type'],
          message: 'Alert type must be one of: ' . implode(', ', $validTypes)
                );
      }

      if (in_array($alertType, ['threshold_pct', 'threshold_abs']) && $thresholdValue === null) {
        return ServiceResponse::failure(
          errors: ['Threshold value required for this alert type'],
          message: 'Missing threshold value'
        );
      }

      $alert = new PriceAlert();
      $alert->setStockTarget($stockTarget);
      $alert->setAlertType($alertType);
      $alert->setThresholdValue($thresholdValue ? (string)$thresholdValue : null);
      $alert->setEnabled(true);
      
      if ($notifyUsers) {
        $alert->setNotifyUsers(json_encode($notifyUsers));
      }
      
      if ($notifyEmail) {
        $alert->setNotifyEmail($notifyEmail);
      }

      $alert->setLastPrice($this->costing->getUnitCost($stockTarget));
      
      $this->em->persist($alert);
      $this->em->flush();
      
      return ServiceResponse::success(
        data: ['alert_id' => $alert->getId()],
        message: 'Price alert created successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to create price alert'
      );
    }
  }

  /**
   * Check a new price against all active alerts for a stock target
   * 
   * @return array List of triggered alerts
   */
  public function checkPrice(
    StockTarget $stockTarget,
    float $newPrice,
      ?PriceHistory $priceRecord = null
  ): array
  {
    $alerts = $this->alertRepo->findBy([
      'stock_target' => $stockTarget,
      'enabled' => true,
    ]);

    $triggered = [];

    foreach ($alerts as $alert) {
      if ($this->shouldTrigger($alert, $newPrice, $stockTarget)) {
        $this->triggerAlert($alert, $newPrice);
        $triggered[] = [
          'alert_id' => $alert->getId(),
          'alert_type' => $alert->getAlertType(),
          'price' => $newPrice,
          'threshold' => $alert->getThresholdValue(),
        ];
      }
    }
    
    return $triggered;
  }

  /**
   * Process all price alerts (run on schedule)
   */
  public function processAllAlerts(): ServiceResponse
  {
    try {
      $alerts = $this->alertRepo->findBy(['enabled' => true]);
      $triggered = [];
      
      foreach ($alerts as $alert) {
        $stockTarget = $alert->getStockTarget();
        
        $latestPrice = $this->priceHistoryRepo->findLatestPrice($stockTarget, null);
        
        if ($latestPrice) {
          $price = (float)$latestPrice->getUnitPrice();
          
          if ($this->shouldTrigger($alert, $price, $stockTarget)) {
            $this->triggerAlert($alert, $price);
            $triggered[] = $alert;
          }
        }
      }

      return ServiceResponse::success(
        data: [
          'total_alerts' => count($alerts),
          'triggered' => count($triggered),
        ],
        message: sprintf('Processed %d alerts, %d triggered', count($alerts), count($triggered))
            );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to process alerts'
      );
    }
  }

  /**
   * Determine if an alert should trigger
   */
  private function shouldTrigger(
    PriceAlert $alert,
    float $currentPrice,
    StockTarget $stockTarget
  ): bool
  {
    $type = $alert->getAlertType();
        
    switch ($type) {
    case 'threshold_pct':
      return $this->checkThresholdPercentage($alert, $currentPrice, $stockTarget);
      
    case 'threshold_abs':
      return $this->checkThresholdAbsolute($alert, $currentPrice);
      
    case 'trend_increase':
      return $this->checkTrendIncrease($stockTarget, $currentPrice);
      
    case 'all_time_high':
      return $this->checkAllTimeHigh($stockTarget, $currentPrice);
      
    default:
      return false;
    }
  }

  /**
   * Check if price exceeds percentage threshold
   */
  private function checkThresholdPercentage(
    PriceAlert $alert,
    float $currentPrice,
    StockTarget $stockTarget
  ): bool
  {
    $lastPrice = $alert->getLastPrice() ? (float)$alert->getLastPrice() : null;
        
    if ($lastPrice === null) {
      // Get baseline price (30-day average)
      $lastPrice = $this->costing->getAveragePrice($stockTarget, null, new \DateTime()->modify('-30 days'), new \DateTime());
    }

    if ($lastPrice <= 0) {
      return false;
    }
    
    $threshold = (float)$alert->getThresholdValue();
    $changePct = (($currentPrice - $lastPrice) / $lastPrice) * 100;
    
    return abs($changePct) >= $threshold;
  }

  /**
   * Check if price exceeds absolute threshold
   */
  private function checkThresholdAbsolute(
    PriceAlert $alert,
    float $currentPrice
  ): bool
  {
    $threshold = (float)$alert->getThresholdValue();
    return $currentPrice >= $threshold;
  }
  
  /**
   * Check for consistent upward trend
   */
  private function checkTrendIncrease(
    StockTarget $stockTarget,
    float $currentPrice
  ): bool
  {
    $variance = $this->costing->getPriceVariance($stockTarget, $currentPrice, null, 30);
        
    return $variance['price_trend'] === 'increasing' 
      && $variance['variance_pct'] > 10; // More than 10% above average
  }

  /**
   * Check if this is an all-time high price
   */
  private function checkAllTimeHigh(
    StockTarget $stockTarget,
    float $currentPrice
  ): bool
  {
    // Get historical max price
    $stats = $this->priceHistoryRepo->getPriceStatistics($stockTarget, null, 365);
        
    if (!$stats) {
      return false;
    }
    
    return $currentPrice > $stats['max'];
  }

  /**
   * Trigger an alert and update its state
   */
  private function triggerAlert(PriceAlert $alert, float $price): void
  {
    $alert->setLastTriggeredAt(new \DateTime());
    $alert->setLastPrice((string)$price);
    $alert->setTriggerCount($alert->getTriggerCount() + 1);
    
    $this->em->flush();
    
    // TODO: Send notifications (email, Slack, etc.)
    $this->sendNotification($alert, $price);
  }

  /**
   * Send notification for triggered alert
   */
  private function sendNotification(PriceAlert $alert, float $price): void
  {
    // TODO: implement multi-channel notifications through the NotificationService
        // - Notify users via in-app notifications
        // - Check a `notify_email` flag, drop messages to a mail service
        // - Check a `webhooks` attribute to oost to Slack/Teams
        // - Check automation rules and create a task/ticket 
        
    $stockTarget = $alert->getStockTarget();
    $message = sprintf(
      'Price alert triggered for %s: Current price $%.2f (Alert type: %s)',
      $stockTarget->getName(),
      $price,
      $alert->getAlertType()
    );
    
    // DEBUG: print price alerts to the error log in dev
    error_log($message);
  }

  /**
   * Disable an alert
   */
  public function disableAlert(PriceAlert $alert): ServiceResponse
  {
    try {
      $alert->setEnabled(false);
      $this->em->flush();
      
      return ServiceResponse::success(
        message: 'Alert disabled successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to disable alert'
      );
    }
  }

  /**
   * Enable an alert
   */
  public function enableAlert(PriceAlert $alert): ServiceResponse
  {
    try {
      $alert->setEnabled(true);
      $this->em->flush();
      
      return ServiceResponse::success(
        message: 'Alert enabled successfully'
      );
    } catch (\Throwable $e) {
      return ServiceResponse::failure(
        errors: [$e->getMessage()],
        message: 'Failed to enable alert'
      );
    }
  }

  /**
   * Get alert history for a stock target
   * 
   * @return array
   */
  public function getAlertHistory(StockTarget $stockTarget, int $days = 90): array
  {
    $alerts = $this->alertRepo->findBy(['stock_target' => $stockTarget]);
    $since = (new \DateTime())->modify("-{$days} days");
    
    $history = [];
    foreach ($alerts as $alert) {
      if ($alert->getLastTriggeredAt() && $alert->getLastTriggeredAt() >= $since) {
        $history[] = [
          'alert_type' => $alert->getAlertType(),
          'triggered_at' => $alert->getLastTriggeredAt(),
          'price' => $alert->getLastPrice(),
          'trigger_count' => $alert->getTriggerCount(),
        ];
      }
    }
    
    // Sort by most recent first
    usort($history, fn($a, $b) => $b['triggered_at'] <=> $a['triggered_at']);
    
    return $history;
  }

  /**
   * Get summary of all active alerts
   */
  public function getAlertSummary(): array
  {
    $alerts = $this->alertRepo->findBy(['enabled' => true]);
        
    $summary = [
      'total_active' => count($alerts),
      'by_type' => [],
      'recently_triggered' => 0,
    ];
    
    $oneWeekAgo = (new \DateTime())->modify('-7 days');
    
    foreach ($alerts as $alert) {
      $type = $alert->getAlertType();
      $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
      
      if ($alert->getLastTriggeredAt() && $alert->getLastTriggeredAt() >= $oneWeekAgo) {
        $summary['recently_triggered']++;
      }
    }
    
    return $summary;
  }
}

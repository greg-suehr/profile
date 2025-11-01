<?php

namespace App\Katzen\Service\Order;

use App\Katzen\Entity\Order;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * OrderActionProvider
 * 
 * Centralizes the logic for determining which actions are available for an order.
 * Uses the state machine to query capabilities and returns structured action data.
 * 
 * This keeps UI logic out of templates while avoiding "magic" auto-discovery.
 */
final class OrderActionProvider
{
  public function __construct(
    private CsrfTokenManagerInterface $csrfTokenManager
  ) {}
  
  /**
   * Get all available actions for this order
   * 
   * @return array<string, array{
   *   label: string,
   *   route: string,
   *   method: string,
   *   icon: string,
   *   variant: string,
   *   csrf_token: ?string,
   *   confirm: ?string,
   *   group: string
   * }>
   */
  public function getAvailableActions(Order $order): array
  {
    $actions = [];
        
    if ($this->canEdit($order)) {
      $actions['edit'] = [
        'label' => 'Edit Order',
        'route' => 'order_edit',
        'method' => 'GET',
        'icon' => 'pencil',
        'variant' => 'primary',
        'csrf_token' => null,
        'confirm' => null,
        'group' => 'modify',
      ];
    }
    
    if ($this->canOpen($order)) {
      $actions['open'] = [
        'label' => 'Open Order',
        'route' => 'order_open',
        'method' => 'POST',
        'icon' => 'check-circle',
        'variant' => 'success',
        'csrf_token' => $this->generateToken('order_open_' . $order->getId()),
        'confirm' => 'Open this order?',
        'group' => 'workflow',
      ];
    }

    if ($this->canStartPrep($order)) {
      $actions['start_prep'] = [
        'label' => 'Start Preparation',
        'route' => 'order_start_prep',
        'method' => 'POST',
        'icon' => 'play-circle',
        'variant' => 'info',
        'csrf_token' => $this->generateToken('order_start_prep_' . $order->getId()),
        'confirm' => null,
        'group' => 'workflow',
      ];
    }
    
    if ($this->canMarkReady($order)) {
      $actions['mark_ready'] = [
        'label' => 'Mark Ready',
        'route' => 'order_mark_ready',
        'method' => 'POST',
        'icon' => 'flag',
        'variant' => 'success',
        'csrf_token' => $this->generateToken('order_mark_ready_' . $order->getId()),
        'confirm' => null,
        'group' => 'workflow',
      ];
    }

    if ($this->canFulfillAll($order)) {
      $actions['fulfill_all'] = [
        'label' => 'Fulfill All Items',
        'route' => 'order_fulfill_all',
        'method' => 'POST',
        'icon' => 'check-circle-fill',
        'variant' => 'success',
        'csrf_token' => $this->generateToken('order_fulfill_all_' . $order->getId()),
        'confirm' => 'Mark all items as fulfilled?',
        'group' => 'workflow',
      ];
    }

    if ($this->canCreateInvoice($order)) {
      $actions['create_invoice'] = [
        'label' => 'Create Invoice',
        'route' => 'invoice_create_from_order',
        'method' => 'GET',
        'icon' => 'file-earmark-plus',
        'variant' => 'info',
        'csrf_token' => null,
        'confirm' => null,
        'group' => 'billing',
      ];
    }

    if ($this->canClose($order)) {
      $actions['close'] = [
        'label' => 'Close Order',
        'route' => 'order_close',
        'method' => 'POST',
        'icon' => 'lock',
        'variant' => 'secondary',
        'csrf_token' => $this->generateToken('order_close_' . $order->getId()),
        'confirm' => 'Close this order? This cannot be undone.',
        'group' => 'workflow',
      ];
    }

    if ($this->canVoid($order)) {
      $actions['void'] = [
        'label' => 'Void Order',
        'route' => 'order_void',
        'method' => 'POST',
        'icon' => 'x-circle',
        'variant' => 'danger',
        'csrf_token' => $this->generateToken('order_void_' . $order->getId()),
        'confirm' => null, // Uses modal for reason
        'group' => 'danger',
      ];
    }
    
    return $actions;
  }

  /**
   * Get actions filtered by group
   */
  public function getActionsByGroup(Order $order, string $group): array
  {
    $allActions = $this->getAvailableActions($order);      
    return array_filter($allActions, fn($action) => $action['group'] === $group);
  }

  /**
   * Check if a specific action is available
   */
  public function hasAction(Order $order, string $actionName): bool
  {
    $actions = $this->getAvailableActions($order);
    return isset($actions[$actionName]);
  }

  // ============================================
  // CAPABILITY CHECKS
  // ============================================

  /**
   * Can the order be edited?
   */
  public function canEdit(Order $order): bool
  {
    return $order->canBeModified();
  }

  /**
   * Can the order be opened?
   */
  public function canOpen(Order $order): bool
  {
    return $order->isPending();
  }

  /**
   * Can prep be started?
   */
  public function canStartPrep(Order $order): bool
  {
    return $order->isOpen() && !$order->getOrderItems()->isEmpty();
  }

  /**
   * Can the order be marked ready?
   */
  public function canMarkReady(Order $order): bool
  {
    return $order->isInPrep();
  }

  /**
   * Can all items be fulfilled?
   */
  public function canFulfillAll(Order $order): bool
  {
    return !$order->isFullyFulfilled() && 
      !$order->isTerminal() &&
      !$order->getOrderItems()->isEmpty();
  }

  /**
   * Can an invoice be created?
   */
  public function canCreateInvoice(Order $order): bool
  {
    return $order->isReady() &&
      $order->isFullyFulfilled() &&
      $order->getBillingStatus() === 'unbilled' &&
      $order->getInvoices()->isEmpty();
  }

  /**
   * Can the order be closed?
   */
  public function canClose(Order $order): bool
  {
    return $order->isReady() &&
      $order->isFullyFulfilled() &&
      $order->isFullyPaid();
  }

  /**
   * Can the order be voided?
   */
  public function canVoid(Order $order): bool
  {
    return !$order->isTerminal() && 
      !$order->isFullyPaid();
  }

  // ============================================
  // PRIVATE HELPERS
  // ============================================
  
  private function generateToken(string $tokenId): string
  {
    return $this->csrfTokenManager->getToken($tokenId)->getValue();
  }
}

<?php

namespace App\Katzen\Tests\Entity;

use App\Katzen\Entity\Order;
use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\StateMachine\OrderStateException;
use App\Katzen\Entity\StateMachine\OrderStateMachine;
use PHPUnit\Framework\TestCase;

class OrderStateMachineTest extends TestCase
{
    private Order $order;

    protected function setUp(): void
    {
        $this->order = new Order();
    }

    // ============================================
    // STATUS TRANSITION TESTS
    // ============================================

    public function testOrderStartsInPendingStatus(): void
    {
        $this->assertEquals(OrderStateMachine::STATUS_PENDING, $this->order->getStatus());
        $this->assertTrue($this->order->isPending());
    }

    public function testCanTransitionFromPendingToOpen(): void
    {
        $this->order->open();
        $this->assertEquals(OrderStateMachine::STATUS_OPEN, $this->order->getStatus());
        $this->assertTrue($this->order->isOpen());
    }

    public function testCanTransitionFromOpenToPrep(): void
    {
        $this->order->open();
        
        // Add an item first (required for prep)
        $item = new OrderItem();
        $item->setQuantity(1);
        $item->setUnitPrice('10.00');
        $this->order->addOrderItem($item);
        
        $this->order->startPrep();
        $this->assertEquals(OrderStateMachine::STATUS_PREP, $this->order->getStatus());
        $this->assertTrue($this->order->isInPrep());
    }

    public function testCannotTransitionToPrepWithoutItems(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot move order to prep status without order items');
        
        $this->order->open();
        $this->order->startPrep(); // Should fail - no items
    }

    public function testCanTransitionFromPrepToReady(): void
    {
        $this->order->open();
        $this->addMockItem();
        $this->order->startPrep();
        
        $this->order->markReady();
        $this->assertEquals(OrderStateMachine::STATUS_READY, $this->order->getStatus());
        $this->assertTrue($this->order->isReady());
    }

    public function testCannotCloseWithoutFulfillment(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot close order until fulfillment is complete');
        
        $this->order->open();
        $this->addMockItem();
        $this->order->startPrep();
        $this->order->markReady();
        
        // Try to close without fulfilling
        $this->order->close(); // Should fail
    }

    public function testCannotCloseWithoutBilling(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot close order until it has been billed');
        
        $this->order->open();
        $item = $this->addMockItem();
        $this->order->startPrep();
        $this->order->markReady();
        
        // Fulfill but don't bill
        $item->fulfill();
        
        $this->order->close(); // Should fail
    }

    public function testCanCloseWhenFullyFulfilledAndBilled(): void
    {
        $this->order->open();
        $item = $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->startPrep();
        $this->order->markReady();
        
        // Fulfill and bill
        $item->fulfill();
        $this->order->recordInvoice(10.00);
        
        $this->order->close();
        $this->assertTrue($this->order->isClosed());
    }

    public function testCannotTransitionFromTerminalState(): void
    {
        $this->expectException(OrderStateException::class);
        
        $this->order->cancel();
        $this->assertTrue($this->order->isTerminal());
        
        // Try to transition from cancelled
        $this->order->open(); // Should fail
    }

    public function testCanVoidOrderWithReason(): void
    {
        $this->order->open();
        
        $this->order->void('Customer requested cancellation');
        
        $this->assertTrue($this->order->isVoided());
        $this->assertEquals('Customer requested cancellation', $this->order->getVoidReason());
        $this->assertNotNull($this->order->getVoidedAt());
    }

    public function testCannotVoidPaidOrder(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot void or cancel a paid order');
        
        $this->order->open();
        $this->addMockItem();
        $this->order->calculateTotals();
        
        // Mark as paid
        $this->order->recordPayment(10.00);
        
        $this->order->void('Attempting to void paid order'); // Should fail
    }

    public function testGetAvailableTransitions(): void
    {
        $this->order->open();
        
        $transitions = $this->order->getAvailableTransitions();
        
        $this->assertContains(OrderStateMachine::STATUS_PREP, $transitions);
        $this->assertContains(OrderStateMachine::STATUS_CANCELLED, $transitions);
        $this->assertContains(OrderStateMachine::STATUS_VOIDED, $transitions);
    }

    // ============================================
    // FULFILLMENT STATUS TESTS
    // ============================================

    public function testOrderStartsAsUnfulfilled(): void
    {
        $this->assertEquals(
            OrderStateMachine::FULFILLMENT_UNFULFILLED,
            $this->order->getFulfillmentStatus()
        );
    }

    public function testFulfillmentStatusUpdatesWhenItemFulfilled(): void
    {
        $item1 = $this->addMockItem();
        $item2 = $this->addMockItem();
        
        // Fulfill first item
        $item1->fulfill();
        
        $this->assertEquals(
            OrderStateMachine::FULFILLMENT_PARTIAL,
            $this->order->getFulfillmentStatus()
        );
        
        // Fulfill second item
        $item2->fulfill();
        
        $this->assertEquals(
            OrderStateMachine::FULFILLMENT_COMPLETE,
            $this->order->getFulfillmentStatus()
        );
        $this->assertTrue($this->order->isFullyFulfilled());
        $this->assertNotNull($this->order->getFulfilledAt());
    }

    public function testFulfillAllMarksAllItemsAsFulfilled(): void
    {
        $item1 = $this->addMockItem();
        $item2 = $this->addMockItem();
        
        $this->order->fulfillAll();
        
        $this->assertTrue($item1->isFulfilled());
        $this->assertTrue($item2->isFulfilled());
        $this->assertTrue($this->order->isFullyFulfilled());
    }

    public function testUnfulfillItemUpdatesOrderStatus(): void
    {
        $item1 = $this->addMockItem();
        $item2 = $this->addMockItem();
        
        $this->order->fulfillAll();
        $this->assertTrue($this->order->isFullyFulfilled());
        
        // Unfulfill one item
        $item1->unfulfill();
        
        $this->assertEquals(
            OrderStateMachine::FULFILLMENT_PARTIAL,
            $this->order->getFulfillmentStatus()
        );
    }

    // ============================================
    // BILLING STATUS TESTS
    // ============================================

    public function testOrderStartsAsUnbilled(): void
    {
        $this->assertEquals(
            OrderStateMachine::BILLING_UNBILLED,
            $this->order->getBillingStatus()
        );
    }

    public function testRecordInvoiceUpdatesBillingStatus(): void
    {
        $this->addMockItem();
        $this->order->calculateTotals();
        
        $this->order->recordInvoice(10.00);
        
        $this->assertEquals(
            OrderStateMachine::BILLING_INVOICED,
            $this->order->getBillingStatus()
        );
        $this->assertEquals('10.00', $this->order->getInvoicedAmount());
    }

    public function testPartialPaymentUpdatesBillingStatus(): void
    {
        $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->recordInvoice(10.00);
        
        $this->order->recordPayment(5.00);
        
        $this->assertEquals(
            OrderStateMachine::BILLING_PARTIAL,
            $this->order->getBillingStatus()
        );
        $this->assertEquals('5.00', $this->order->getPaidAmount());
        $this->assertEquals(5.00, $this->order->getRemainingBalance());
    }

    public function testFullPaymentUpdatesBillingStatus(): void
    {
        $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->recordInvoice(10.00);
        
        $this->order->recordPayment(10.00);
        
        $this->assertEquals(
            OrderStateMachine::BILLING_PAID,
            $this->order->getBillingStatus()
        );
        $this->assertTrue($this->order->isFullyPaid());
        $this->assertEquals(0.0, $this->order->getRemainingBalance());
    }

    public function testRefundUpdatesBillingStatus(): void
    {
        $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->recordPayment(10.00);
        
        $this->order->refund(5.00);
        
        $this->assertEquals(
            OrderStateMachine::BILLING_PARTIAL,
            $this->order->getBillingStatus()
        );
        $this->assertEquals('5.00', $this->order->getPaidAmount());
    }

    public function testFullRefundUpdatesBillingStatusToRefunded(): void
    {
        $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->recordPayment(10.00);
        
        $this->order->refund(10.00);
        
        $this->assertEquals(
            OrderStateMachine::BILLING_REFUNDED,
            $this->order->getBillingStatus()
        );
        $this->assertEquals('0.00', $this->order->getPaidAmount());
    }

    public function testCannotRefundMoreThanPaid(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot refund');
        
        $this->addMockItem();
        $this->order->calculateTotals();
        $this->order->recordPayment(10.00);
        
        $this->order->refund(15.00); // Should fail
    }

    // ============================================
    // ORDER MODIFICATION TESTS
    // ============================================

    public function testCanModifyUnfulfilledOrder(): void
    {
        $this->order->open();
        
        $this->assertTrue($this->order->canBeModified());
        
        $item = new OrderItem();
        $item->setQuantity(1);
        $item->setUnitPrice('10.00');
        $this->order->addOrderItem($item);
        
        $this->assertCount(1, $this->order->getOrderItems());
    }

    public function testCannotModifyFulfilledOrder(): void
    {
        $this->order->open();
        $item = $this->addMockItem();
        $item->fulfill();
        
        $this->assertFalse($this->order->canBeModified());
    }

    public function testCannotAddItemsToFulfilledOrder(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot add items');
        
        $this->order->open();
        $item = $this->addMockItem();
        $item->fulfill();
        
        // Try to add another item
        $newItem = new OrderItem();
        $newItem->setQuantity(1);
        $newItem->setUnitPrice('5.00');
        $this->order->addOrderItem($newItem); // Should fail
    }

    public function testCannotRemoveItemsFromFulfilledOrder(): void
    {
        $this->expectException(OrderStateException::class);
        $this->expectExceptionMessage('Cannot remove items');
        
        $this->order->open();
        $item = $this->addMockItem();
        $item->fulfill();
        
        $this->order->removeOrderItem($item); // Should fail
    }

    // ============================================
    // FINANCIAL CALCULATION TESTS
    // ============================================

    public function testCalculateTotals(): void
    {
        $item1 = new OrderItem();
        $item1->setQuantity(2);
        $item1->setUnitPrice('10.00');
        $this->order->addOrderItem($item1);
        
        $item2 = new OrderItem();
        $item2->setQuantity(3);
        $item2->setUnitPrice('5.00');
        $this->order->addOrderItem($item2);
        
        $this->order->setTaxAmount('3.50');
        
        $this->assertEquals('35.00', $this->order->getSubtotal()); // (2*10) + (3*5)
        $this->assertEquals('3.50', $this->order->getTaxAmount());
        $this->assertEquals('38.50', $this->order->getTotalAmount()); // 35 + 3.5
    }

    // ============================================
    // DISPLAY HELPERS TESTS
    // ============================================

    public function testGetStatusLabel(): void
    {
        $this->assertEquals('Pending', $this->order->getStatusLabel());
        
        $this->order->open();
        $this->assertEquals('Open', $this->order->getStatusLabel());
    }

    public function testGetStatusBadgeClass(): void
    {
        $this->assertEquals('badge-warning', $this->order->getStatusBadgeClass());
        
        $this->order->open();
        $this->assertEquals('badge-info', $this->order->getStatusBadgeClass());
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function addMockItem(): OrderItem
    {
        $item = new OrderItem();
        $item->setQuantity(1);
        $item->setUnitPrice('10.00');
        $item->setCogs('5.00');
        $this->order->addOrderItem($item);
        
        return $item;
    }
}

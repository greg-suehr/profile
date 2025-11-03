<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CustomerFixture extends Fixture
{
  private const CUSTOMERS = [
    // Individuals
    [
      'name' => 'Sarah Mitchell',
      'email' => 'sarah.mitchell@email.com',
      'phone' => '412-555-0123',
      'type' => 'individual',
      'billing_address' => '123 Main Street, Pittsburgh, PA 15213',
      'shipping_address' => '123 Main Street, Pittsburgh, PA 15213',
      'notes' => 'Regular customer, prefers oat milk',
    ],
    [
      'name' => 'David Chen',
      'email' => 'david.chen@email.com',
      'phone' => '412-555-0456',
      'type' => 'individual',
      'billing_address' => '456 Forbes Avenue, Pittsburgh, PA 15213',
      'shipping_address' => '456 Forbes Avenue, Pittsburgh, PA 15213',
      'notes' => 'Likes extra hot drinks',
    ],
    [
      'name' => 'Emily Rodriguez',
      'email' => 'emily.r@email.com',
      'phone' => '412-555-0234',
      'type' => 'individual',
      'billing_address' => '234 Oakland Avenue, Pittsburgh, PA 15213',
      'shipping_address' => '234 Oakland Avenue, Pittsburgh, PA 15213',
      'notes' => 'Rewards member since 2023',
    ],    
    
    // Business accounts
    [
      'name' => 'TechStart Inc',
      'email' => 'orders@techstart.com',
      'phone' => '412-555-0789',
      'type' => 'business',
      'billing_address' => '789 Liberty Avenue, Pittsburgh, PA 15222',
      'shipping_address' => '789 Liberty Avenue, Suite 500, Pittsburgh, PA 15222',
      'notes' => 'Weekly office orders, 20+ drinks. Contact: Jennifer',
    ],
          
    // Wholesale
    [
      'name' => 'Green Valley Cafe',
      'email' => 'wholesale@greenvalley.com',
      'phone' => '412-555-0567',
      'type' => 'wholesale',
      'billing_address' => '567 Penn Avenue, Pittsburgh, PA 15222',
      'shipping_address' => '567 Penn Avenue, Pittsburgh, PA 15222',
      'notes' => 'Wholesale customer, buys pastries for resale',
    ],
  ];

  public function load(ObjectManager $manager): void
  {
    foreach (self::CUSTOMERS as $customerData) {
      $existing = $manager->getRepository(Customer::class)
                ->findOneBy(['phone' => $customerData['phone']]);
      
      if ($existing) {
        continue;
      } else {
        $customer = new Customer();
        $customer->setName($customerData['name']);
        $customer->setType($customerData['type']);
        $customer->setEmail($customerData['email']);
        $customer->setPhone($customerData['phone']);
        $customer->setBillingAddress($customerData['billing_address']);
        $customer->setShippingAddress($customerData['shipping_address']);
        $customer->setNotes($customerData['notes']);
        $customer->setStatus('active');
        $customer->setAccountBalance('0.00');
        $customer->setArBalance('0.00');
        $customer->setPaymentTerms('Net 15');
        $manager->persist($customer);
      }
    }
    
    $manager->flush();
  }
}


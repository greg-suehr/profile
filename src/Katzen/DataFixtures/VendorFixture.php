<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\Vendor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VendorFixture extends Fixture
{
  private const VENDORS = [
    [
      'name' => 'Premium Coffee Roasters',
      'email' => 'sales@premiumcoffee.com',
      'phone' => '800-555-0100',
      'type' => 'supplier',
      'billing_address' => '1000 Roaster Way, Seattle, WA 98101',
      'shipping_address' => '1000 Roaster Way, Seattle, WA 98101',
      'notes' => 'Primary coffee bean supplier, weekly deliveries',
      'payment_terms' => 'Net 30',
      'tax_id' => '12-3456789',
    ],
    [
      'name' => 'Dairy Fresh Distributors',
      'email' => 'orders@dairyfresh.com',
      'phone' => '412-555-0800',
      'type' => 'supplier',
      'billing_address' => '2500 Milk Road, Pittsburgh, PA 15220',
      'shipping_address' => '2500 Milk Road, Pittsburgh, PA 15220',
      'notes' => 'Dairy and alternative milk supplier, twice weekly delivery',
      'payment_terms' => 'Net 15',
      'tax_id' => '23-4567890',
    ],
    [
      'name' => 'Sweet Supplies Co',
      'email' => 'info@sweetsupplies.com',
      'phone' => '412-555-0900',
      'type' => 'supplier',
      'billing_address' => '3000 Sugar Lane, Pittsburgh, PA 15212',
      'shipping_address' => '3000 Sugar Lane, Pittsburgh, PA 15212',
      'notes' => 'Syrups, chocolate, and baking supplies',
      'payment_terms' => 'Net 30',
      'tax_id' => '34-5678901',
    ],
    [
      'name' => 'EcoPack Solutions',
      'email' => 'sales@ecopack.com',
      'phone' => '800-555-0200',
      'type' => 'supplier',
      'billing_address' => '4000 Green Street, Portland, OR 97201',
      'shipping_address' => '4000 Green Street, Portland, OR 97201',
      'notes' => 'Sustainable packaging supplier, monthly bulk orders',
      'payment_terms' => 'Net 45',
      'tax_id' => '45-6789012',
    ],
    [
      'name' => 'Local Flour Mill',
      'email' => 'orders@localflourmill.com',
      'phone' => '412-555-1000',
      'type' => 'supplier',
      'billing_address' => '5000 Mill Road, Pittsburgh, PA 15235',
      'shipping_address' => '5000 Mill Road, Pittsburgh, PA 15235',
      'notes' => 'Local supplier for flour and baking ingredients',
      'payment_terms' => 'Net 30',
      'tax_id' => '56-7890123',
    ],
  ];

  public function load(ObjectManager $manager): void
  {
    foreach (self::VENDORS as $vendorData) {
      $existing = $manager->getRepository(Vendor::class)
                ->findOneBy(['phone' => $vendorData['phone']]);
      
      if ($existing) {
        continue;
      } else {
        $vendor = new Vendor();

        $vendor->setName($vendorData['name']);
        $vendor->setEmail($vendorData['email']);
        $vendor->setPhone($vendorData['phone']);
        $vendor->setBillingAddress($vendorData['billing_address']);
        $vendor->setShippingAddress($vendorData['shipping_address']);
        $vendor->setPaymentTerms($vendorData['payment_terms']);
        $vendor->setTaxId($vendorData['tax_id']);
        $vendor->setNotes($vendorData['notes']);
        $vendor->setStatus('active');
        $vendor->setCurrentBalance('0.00');
        $manager->persist($vendor);
      }
    }
    
    $manager->flush();
  }
}


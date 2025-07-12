<?php
namespace App\Katzen\EventListener;

use App\Katzen\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, entity: Item::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Item::class)]
class ItemEntityListener
{
  public function prePersist(Item $entity): void
  {
    $entity->setCreatedAt(new \DateTimeImmutable());
    $entity->setUpdatedAt(new \DateTime());
  }
  
  public function preUpdate(Item $entity): void
  {
    $entity->setUpdatedAt(new \DateTime());
  }
}
?>

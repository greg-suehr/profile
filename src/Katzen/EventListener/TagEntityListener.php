<?php
namespace App\Katzen\EventListener;

use App\Katzen\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, entity: Tag::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Tag::class)]
class TagEntityListener
{
  public function prePersist(Tag $entity): void
  {
    $entity->setCreatedAt(new \DateTimeImmutable());
    $entity->setUpdatedAt(new \DateTime());
  }
  
  public function preUpdate(Tag $entity): void
  {
    $entity->setUpdatedAt(new \DateTime());
  }
}
?>

<?php
namespace App\Katzen\EventListener;

use App\Katzen\Entity\RecipeList;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, entity: RecipeList::class)]
#[AsEntityListener(event: Events::preUpdate, entity: RecipeList::class)]
class RecipeListEntityListener
{
  public function prePersist(RecipeList $entity): void
  {
    $entity->setCreatedAt(new \DateTimeImmutable());
    $entity->setUpdatedAt(new \DateTime());
  }
  
  public function preUpdate(RecipeList $entity): void
  {
    $entity->setUpdatedAt(new \DateTime());
  }
}
?>

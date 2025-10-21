<?php
namespace App\Shared\EventListener;

use App\Shared\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
class UserEntityListener
{
  public function prePersist(User $user): void
  {
    $user->setCreatedAt(new \DateTimeImmutable());
    $user->setUpdatedAt(new \DateTime());
  }

  public function preUpdate(User $user, PreUpdateEventArgs $args): void
  {
    $user->setUpdatedAt(new \DateTime());

    $em = $args->getObjectManager();
    $em->getUnitOfWork()->recomputeSingleEntityChangeSet(
      $em->getClassMetadata(User::class),
      $user
    );
  }
}
?>

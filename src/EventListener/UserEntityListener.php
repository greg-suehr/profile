namespace App\EntityListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
class TimestampableListener
{
  public function prePersist(LifecycleEventArgs $args): void
  {
    $entity = $args->getEntity();
    if (!$entity instanceof User) {
      return;
    }
    
    $now = new \DateTime();
    $entity->setCreatedAt($now);
    $entity->setUpdatedAt($now);
  }
  
  public function preUpdate(LifecycleEventArgs $args): void
  {
    $entity = $args->getEntity();
    if (!$entity instanceof User) {
      return;
    }
    
    $entity->setUpdatedAt(new \DateTime());
  }
}

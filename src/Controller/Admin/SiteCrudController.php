<?php

namespace App\Controller\Admin;

use App\Entity\Site;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use App\Message\ProvisionSiteSchema;
use Symfony\Component\Messenger\MessageBusInterface;

class SiteCrudController extends AbstractCrudController
{
    public function __construct(
        private SiteService $siteService,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}
  
    public static function getEntityFqcn(): string
    {
        return Site::class;
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if (!($entity instanceof Site)) {
            parent::persistEntity($em, $entity);
            return;
        }

        $em->persist($entity);
        $em->flush();

        $this->bus->dispatch(new ProvisionSiteSchema($entity->getId()));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            TextField::new('domain'),
        ];
    }
}

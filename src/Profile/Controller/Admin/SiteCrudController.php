<?php

namespace App\Profile\Controller\Admin;

use App\Profile\Entity\Site;
use App\Profile\Message\ProvisionSiteSchema;
use App\Profile\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
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

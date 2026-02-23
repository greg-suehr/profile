<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\ProfileInfluence;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ProfileInfluenceCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return ProfileInfluence::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('name'),
      TextField::new('epithet')
                ->setHelp('Short descriptor, e.g. "physicist", "playwright & essayist"'),
      TextField::new('domain')
                ->setHelp('Primary domain: Physics, Literature, Software, Music, Philosophy, etc.'),
      TextField::new('era')
                ->setHelp('Lifespan or era, e.g. "1879–1955", "5th c. BCE", "contemporary"')
                ->hideOnIndex(),
      TextareaField::new('blurb')
                ->setHelp('How this person influenced your thinking or work.')
                ->hideOnIndex(),
      UrlField::new('url')
                ->setLabel('External URL')
                ->setHelp('Wikipedia, personal site, etc.')
                ->hideOnIndex(),
      ArrayField::new('tags'),
      IntegerField::new('sortOrder')
                ->setHelp('Lower values appear first. Leave blank for alphabetical.')
                ->hideOnIndex(),
      BooleanField::new('isPublished'),
      DateTimeField::new('createdAt')->hideOnForm(),
      DateTimeField::new('updatedAt')->hideOnForm(),
    ];
  }
}

<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\Album;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AlbumCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return Album::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('title'),
      SlugField::new('slug')->setTargetFieldName('title'),
      TextareaField::new('description')->hideOnIndex(),
      IntegerField::new('releaseYear'),
      ImageField::new('image')
            ->setLabel('Cover Art')
            ->setUploadDir('public/uploads/albums')
            ->setBasePath('uploads/albums')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex(),
      ArrayField::new('tags'),
      BooleanField::new('isPublished'),
      AssociationField::new('songs')
            ->setCrudController(SongCrudController::class)
            ->hideOnForm(),
      DateTimeField::new('createdAt')->hideOnForm(),
      DateTimeField::new('updatedAt')->hideOnForm(),
    ];
  }
}

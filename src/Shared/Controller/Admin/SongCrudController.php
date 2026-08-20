<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\Song;
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

class SongCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return Song::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      AssociationField::new('album')
            ->setCrudController(AlbumCrudController::class),
      TextField::new('title'),
      SlugField::new('slug')->setTargetFieldName('title'),
      IntegerField::new('trackNumber'),
      IntegerField::new('durationSeconds')->setLabel('Duration (sec)')->hideOnIndex(),
      TextareaField::new('lyrics')->hideOnIndex(),
      ImageField::new('image')
            ->setLabel('Cover Image')
            ->setUploadDir('public/uploads/songs')
            ->setBasePath('uploads/songs')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex(),
      ImageField::new('audioFilePath')
            ->setLabel('Audio File')
            ->setUploadDir('public/uploads/songs')
            ->setBasePath('uploads/songs')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex(),
      TextField::new('audioOriginalFileName')
                ->setLabel('Original Filename')
                ->hideOnIndex()
                ->hideOnForm(),
      ArrayField::new('tags'),
      BooleanField::new('isPublished'),
      DateTimeField::new('createdAt')->hideOnForm(),
      DateTimeField::new('updatedAt')->hideOnForm(),
    ];
  }
}

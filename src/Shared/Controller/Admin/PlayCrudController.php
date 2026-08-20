<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\Play;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File;

class PlayCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return Play::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('title'),
      SlugField::new('slug')->setTargetFieldName('title'),
      TextareaField::new('blurb')->setHelp('Short teaser shown on the plays index.'),
      TextareaField::new('description')->setHelp('Fuller synopsis shown on the play detail page.')->hideOnIndex(),
      TextField::new('genre')->hideOnIndex(),
      IntegerField::new('runtimeMinutes')->setLabel('Runtime (min)')->hideOnIndex(),
      IntegerField::new('premiereYear'),
      ImageField::new('image')
            ->setLabel('Cover Image')
            ->setUploadDir('public/uploads/plays')
            ->setBasePath('uploads/plays')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex(),
      ImageField::new('filePath')
            ->setLabel('Script PDF')
            ->setUploadDir('public/uploads/plays')
            ->setBasePath('uploads/plays')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->setFileConstraints(new File(
              maxSize: '20M',
              mimeTypes: ['application/pdf'],
              mimeTypesMessage: 'Please upload a valid PDF file.',
            ))
            ->hideOnIndex(),
      TextField::new('originalFileName')
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

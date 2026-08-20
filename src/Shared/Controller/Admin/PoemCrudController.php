<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\Poem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File;

class PoemCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return Poem::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('title'),
      SlugField::new('slug')->setTargetFieldName('title'),
      TextField::new('collectionName')->setLabel('Collection'),
      TextareaField::new('blurb')->setHelp('Short teaser shown on the poems index.'),
      TextareaField::new('body')->setHelp('Full text of the poem, if publishing it directly.')->hideOnIndex(),
      ImageField::new('image')
            ->setLabel('Cover Image')
            ->setUploadDir('public/uploads/poems')
            ->setBasePath('uploads/poems')
            ->setUploadedFileNamePattern('[slug]-[randomhash].[extension]')
            ->setRequired(false)
            ->hideOnIndex(),
      ImageField::new('filePath')
            ->setLabel('PDF File')
            ->setUploadDir('public/uploads/poems')
            ->setBasePath('uploads/poems')
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

<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\ProfileDocument;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File;

class ProfileDocumentCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return ProfileDocument::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('title'),
      TextField::new('authors'),
      IntegerField::new('year'),
      TextField::new('source')->setHelp('Journal, conference, URL, etc.'),
      TextareaField::new('abstract')->hideOnIndex(),
      TextareaField::new('notes')->setHelp('Your personal annotations.')->hideOnIndex(),
      ArrayField::new('tags'),
      ImageField::new('filePath')
            ->setLabel('PDF File')
            ->setUploadDir('public/uploads/documents')
            ->setBasePath('uploads/documents')
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
      BooleanField::new('isPublished'),
      DateTimeField::new('createdAt')->hideOnForm(),
      DateTimeField::new('updatedAt')->hideOnForm(),
    ];
  }
}

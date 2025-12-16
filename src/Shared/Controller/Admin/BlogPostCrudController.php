<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\BlogPost;
use App\Shared\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use FOS\CKEditorBundle\Form\Type\CKEditorType;

class BlogPostCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BlogPost::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [            
            TextField::new('title'),
            TextField::new('subtitle'),
            /*
            ImageField::new('featuredImage')
                ->setBasePath('/uploads/blog')          // Path for displaying images
                ->setUploadDir('public/uploads/blog')   // Directory for uploaded files
                ->setUploadedFileNamePattern('[randomhash].[extension]') // Prevent name collisions
                ->setRequired(false),
            */
            TextEditorField::new('summary'),
            TextEditorField::new('content'),
            // ->setFormType(CKEditorType::class),
            TextEditorField::new('text_content'),            
            DateTimeField::new('created_at'),
            DateTimeField::new('updated_at'),
            AssociationField::new('author')
              ->setCrudController(UserCrudController::class),
            BooleanField::new('is_published')
        ];
    }
}

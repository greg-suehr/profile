<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use FOS\CKEditorBundle\Form\Type\CKEditorType;

class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title'), 
            TextField::new('slug'),
            BooleanField::new('is_published'),
            TextField::new('htmlContent')
            ->setFormType(CKEditorType::class)
            ->onlyOnForms(),
            TextField::new('htmlContent')
            ->renderAsHtml()
            ->onlyOnIndex()
        ];
    }
}

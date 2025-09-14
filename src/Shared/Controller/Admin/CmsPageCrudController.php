<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\CmsPage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CmsPageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CmsPage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
          TextField::new('slug'),
          TextEditorField::new('html'),          
        ];
    }
}

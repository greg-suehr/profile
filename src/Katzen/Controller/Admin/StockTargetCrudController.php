<?php

namespace App\Katzen\Controller\Admin;

use App\Katzen\Entity\StockTarget;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class StockTargetCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return StockTarget::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      yield TextField::new('name'),
      yield AssociationField::new('base_unit')->autocomplete(),
    ];
  }

}

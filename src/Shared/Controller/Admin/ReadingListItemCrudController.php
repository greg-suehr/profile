<?php

namespace App\Shared\Controller\Admin;

use App\Shared\Entity\ReadingListItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ReadingListItemCrudController extends AbstractCrudController
{
  public static function getEntityFqcn(): string
  {
    return ReadingListItem::class;
  }

  public function configureFields(string $pageName): iterable
  {
    return [
      TextField::new('title'),
      TextField::new('author'),
      IntegerField::new('year'),
      TextField::new('isbn')->hideOnIndex(),
      ChoiceField::new('status')
                ->setChoices(ReadingListItem::STATUSES),
      IntegerField::new('rating')
                ->setHelp('1–5, leave blank if unrated.')
                ->hideOnIndex(),
      TextareaField::new('notes')->hideOnIndex(),
      DateField::new('dateFinished')
                ->setHelp('Leave blank if not yet finished.')
                ->hideOnIndex(),
      ArrayField::new('tags'),
      UrlField::new('coverUrl')
                ->setLabel('Cover Image URL')
                ->setHelp('Optional external image URL.')
                ->hideOnIndex(),
      DateTimeField::new('createdAt')->hideOnForm(),
    ];
  }
}

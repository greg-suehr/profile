<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\FieldDefinitionType;
use App\Model\FieldDefinition;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield TextField::new('description');

        if ($pageName === Crud::PAGE_EDIT) {
          $category = $this->getContext()->getEntity()->getInstance();

          if (0 === \count($category->getSchemaDefinitions())) {
            $dtos = [];
            foreach ($category->getSchema() as $key => $def) {
              $dto = new FieldDefinition();
              $dto->name    = $key;
              $dto->label   = $def['label']   ?? '';
              $dto->type    = $def['type']    ?? 'text';
              $dto->options = json_encode($def['options'] ?? []);
              $dtos[] = $dto;
            }
            $category->setSchemaDefinitions($dtos);
          }
        }
          
        yield CollectionField::new('schemaDefinitions', 'Attributes')
            ->setEntryType(FieldDefinitionType::class)
            ->setFormTypeOption('by_reference', false)
            ->onlyOnForms()
        ;
    }

    public function createEntity(string $entityFqcn)
    {
        $category = new Category();
        $category->setSchemaDefinitions([new FieldDefinition()]);
        return $category;
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->syncSchema($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->syncSchema($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function syncSchema(Category $category): void
    {
        $definitions = $category->getSchemaDefinitions();

        $schema = [];
        foreach ($definitions as $def) {
            $opts = json_decode($def->options ?: '{}', true);
            $schema[$def->name] = [
                'label'   => $def->label,
                'type'    => $def->type,
                'options' => $opts,
            ];
        }
        $category->setSchema($schema);
    }
}

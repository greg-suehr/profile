<?php

namespace App\Profile\Controller\Admin;

use App\Profile\Entity\Content;
use App\Profile\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }
  
    public static function getEntityFqcn(): string
    {
        return Content::class;
    }

    public function configureFields(string $pageName): iterable
    {
        // Always let the admin pick which Category this Content belongs to:
        yield AssociationField::new('category');

        // Only on NEW or EDIT do we render the dynamic fields:
        if (\in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            $content = $this->getContext()->getEntity()->getInstance();
            $category = $content->getCategory();
            if ($category) {
                foreach ($category->getSchema() as $key => $def) {
                    // pick the right field type
                    switch ($def['type']) {
                        case 'integer':
                            $field = IntegerField::new($key);
                            break;
                        case 'checkbox':
                        case 'boolean':
                            $field = BooleanField::new($key);
                            break;
                        case 'choice':
                            $field = ChoiceField::new($key)
                                ->setChoices($def['options']['choices'] ?? []);
                            break;
                        case 'date':
                            $field = DateField::new($key);
                            break;
                        case 'text':
                        default:
                            $field = TextField::new($key);
                    }

                    // mark it "unmapped" so EasyAdmin won't try to call $content->set<key>() on it
                    $field->setFormTypeOption('mapped', false)
                          ->setLabel($def['label'] ?? ucfirst($key));

                    yield $field;
                }
            }
        }
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->syncData($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->syncData($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function syncData(Content $content): void
    {
        $req = $this->requestStack->getCurrentRequest();
        if (null === $req) {
            return;
        }

        $data = [];
        $schema = $content->getCategory()?->getSchema() ?? [];
        $formData = $req->request->all()[self::getEntityFqcn()] ?? [];

        foreach ($schema as $key => $def) {
            $data[$key] = $formData[$key] ?? null;
        }

        $content->setData($data);
    }
}

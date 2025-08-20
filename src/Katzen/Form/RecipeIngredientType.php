<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Unit;
use App\Katzen\Form\QuantityUnitType;
use App\Katzen\Repository\ItemRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RecipeIngredientType extends AbstractType
{
    public function __construct(private readonly ItemRepository $items) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
          ->add('supply', EntityType::class, [
            'class'        => Item::class,
            'choice_label' => 'name',
            'placeholder'  => 'Choose…',
            'mapped'       => false,
            'required'     => true,
            'query_builder' => fn(ItemRepository $r) =>
              $r->createQueryBuilder('i')->andWhere('i.archived_at IS NULL')->orderBy('i.name','ASC'),
          ])
          ->add('quantityUnit', QuantityUnitType::class, [
            'label' => false,
          ])
          ->add('note', null, [
            'required' => false
          ]);
        
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $e) {
            $ri = $e->getData();
            $form = $e->getForm();
            if (!$ri instanceof RecipeIngredient) return;

            if ($ri->getSupplyType() === 'item' && $ri->getSupplyId()) {
                if ($item = $this->items->find($ri->getSupplyId())) {
                    $form->get('supply')->setData($item);
                }
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $e) {
            $ri = $e->getData();
            $form = $e->getForm();
            if (!$ri instanceof RecipeIngredient) return;

            $item = $form->get('supply')->getData();
            if ($item) {
              $ri->setSupplyType('item');
              $ri->setSupplyId($item->getId());
            } else {
              $ri->setSupplyType(null);
              $ri->setSupplyId(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
          'data_class' => RecipeIngredient::class,
        ]);
    }
}

<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\PurchaseItem;
use App\Katzen\Entity\StockTarget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stockTarget', EntityType::class, [
                'class' => StockTarget::class,
                'choice_label' => 'name',
                'placeholder' => 'Select Item',
                'required' => true,
                'label' => 'Item',
            ])
            ->add('qty_ordered', NumberType::class, [
                'label' => 'Quantity',
                'scale' => 2,
                'required' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
            ])
            ->add('unit_price', NumberType::class, [
                'label' => 'Unit Price',
                'scale' => 2,
                'required' => true,
                'attr' => ['step' => '0.01', 'min' => '0'],
            ])
            ->add('submit', SubmitType::class, [
              'label' => 'Add Item',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PurchaseItem::class,
        ]);
    }
}

<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\Vendor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('vendor', EntityType::class, [
                'class' => Vendor::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a vendor',
                'required' => true,
                'label' => 'Vendor',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('po_number', TextType::class, [
                'label' => 'PO Number',
                'required' => false,
                'help' => 'Leave blank to auto-generate',
            ])
            ->add('order_date', DateType::class, [
                'label' => 'Order Date',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('expected_delivery', DateType::class, [
                'label' => 'Expected Delivery',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('tax_amount', NumberType::class, [
                'label' => 'Tax Amount',
                'scale' => 2,
                'required' => false,
                'attr' => ['step' => '0.01'],
            ])
            ->add('purchaseItems', CollectionType::class, [
                'entry_type' => PurchaseItemType::class,
                'entry_options' => [
                    'label' => false,
                    'row_attr' => ['data-collection-item' => ''],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
                'attr' => ['class' => 'purchase-items-collection'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create Purchase Order',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Purchase::class,
        ]);
    }
}

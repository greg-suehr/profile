<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\StockReceiptItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockReceiptItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('qty_received', NumberType::class, [
                'label' => 'Qty Received',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
                'required' => true,
            ])
            ->add('lot_number', TextType::class, [
                'label' => 'Lot Number',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Leave blank to auto-generate',
                ],
            ])
            ->add('expiration_date', DateType::class, [
                'label' => 'Expiration Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('production_date', DateType::class, [
                'label' => 'Production Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockReceiptItem::class,
        ]);
    }
}
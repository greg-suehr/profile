<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\StockTarget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockAdjustType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
          ->add('stock_target', EntityType::class, [
            'class' => StockTarget::class,
            'choice_label' => 'name',
            'label' => 'Stock Target',
          ])
          ->add('qty', NumberType::class, [
            'label' => 'Quantity',
            'scale' => 2,
          ])
          ->add('reason', TextareaType::class, [
            'required' => false,
            'label' => 'Note (optional)',
          ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
          'data_class' => null,
        ]);
    }
}

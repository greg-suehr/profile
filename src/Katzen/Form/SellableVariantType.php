<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\SellableVariant;
use App\Katzen\Entity\StockTarget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SellableVariantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('variantName', TextType::class, [
                'label' => 'Variant Name',
                'attr' => [
                    'placeholder' => 'e.g., Small, Medium, Large, or Red, Blue, Green',
                    'class' => 'form-control',
                ],
                'help' => 'The specific name for this variant (e.g., "Large" or "Extra Cheese")',
            ])
            ->add('priceAdjustment', MoneyType::class, [
                'label' => 'Price Adjustment',
                'currency' => 'USD',
                'required' => false,
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control',
                ],
                'help' => 'Amount to add/subtract from base price (e.g., +2.00 for extra size)',
            ])
            ->add('portionMultiplier', NumberType::class, [
                'label' => 'Portion Multiplier',
                'required' => false,
                'attr' => [
                    'placeholder' => '1.00',
                    'class' => 'form-control',
                ],
                'help' => 'Multiplicative factor to apply to base portion (e.g., 0.50 for half price portion)',
            ])
            ->add('sortOrder', NumberType::class, [
                'label' => 'Sort Order',
                'required' => false,
                'attr' => [
                    'placeholder' => '0',
                    'class' => 'form-control',
                ],
                'help' => 'Lower numbers appear first',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SellableVariant::class,
        ]);
    }
}

<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\StockTarget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SellableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Item Name',
                'attr' => [
                    'placeholder' => 'e.g., Caesar Salad, Burger, Coffee',
                    'class' => 'form-control',
                ],
            ])
            ->add('sku', TextType::class, [
                'label' => 'SKU',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Optional unique identifier',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Brief description for menu display',
                    'class' => 'form-control',
                ],
            ])
            ->add('category', TextType::class, [
                'label' => 'Category',
                'required' => false,
                'attr' => [
                    'placeholder' => 'e.g., Entrée, Beverage, Dessert',
                    'class' => 'form-control',
                    'list' => 'category-suggestions',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Item Type',
                'choices' => [
                    'Simple Item' => 'simple',
                    'Configurable (with variants)' => 'configurable',
                    'Bundle (combo)' => 'bundle',
                    'Modifier/Add-on' => 'modifier',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('portionMultiplier', NumberType::class, [
                'label' => 'Portion Multiplier',
                'required' => false,
                'attr' => [
                    'placeholder' => '1.0',
                    'step' => '0.1',
                    'min' => '0',
                    'class' => 'form-control',
                ],
                'help' => 'For size variations (e.g., 1.5 for large, 0.5 for small)',
            ])
            ->add('parent', EntityType::class, [
                'class' => Sellable::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Parent Item',
                'placeholder' => 'None (this is a parent item)',
                'attr' => ['class' => 'form-select'],
                'help' => 'Select if this is a size variant of another item',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sellable::class,
        ]);
    }
}

<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Recipe;
use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\RecipeInstruction;
use App\Katzen\Entity\Unit;
use App\Katzen\Entity\User;
use App\Katzen\Form\RecipeIngredientType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeBuilderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder          
            ->add('title')
            ->add('summary')
            ->add('serving_min_qty')
            ->add('serving_max_qty')
            ->add('serving_unit', EntityType::class, [
                'class' => Unit::class,
                'choice_label' => 'name',
            ])
            ->add('prep_time')          
            ->add('cook_time')
            ->add('wait_time')          
            ->add('recipeIngredients', CollectionType::class, [
              'entry_type' => RecipeIngredientType::class,
              'entry_options' => [
                'label' => false,
                'row_attr' => ['data-collection-item' => ''],
              ],
              'allow_add' => true,
              'allow_delete' => true,
              'by_reference' => false,
              'prototype' => true,
              'label' => false,
              'attr' => ['class' => 'ingredient-collection'],
            ])
            ->add('save', SubmitType::class, ['label' => 'Continue']);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}

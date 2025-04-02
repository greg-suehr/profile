<?php

namespace App\Form;

use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\RecipeInstruction;
use App\Entity\Unit;
use App\Entity\User;
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
              'entry_type' => IngredientSelectorType::class,
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

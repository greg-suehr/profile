<?php

namespace App\Form;

use App\Entity\RecipeIngredient;
use App\Entity\Recipe;
use App\Entity\Unit;
use App\Entity\Item;
use App\Form\RecipeIngredientType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IngredientSelectorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
          ->add('recipeIngredients', CollectionType::class, [
            'entry_type' => RecipeIngredientType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
            'label' => false,
            'attr' => ['class' => 'ingredient-collection'],
          ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
          'data_class' => Recipe::class,
        ]);
    }
}

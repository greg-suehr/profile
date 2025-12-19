<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\RecipeInstruction;
use App\Katzen\Entity\Recipe;
use App\Katzen\Form\RecipeInstructionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InstructionSelectorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder          
          ->add('recipeInstructions', CollectionType::class, [
            'entry_type' => RecipeInstructionType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
            'label' => false,
            'attr' => ['class' => 'instruction-collection'],
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

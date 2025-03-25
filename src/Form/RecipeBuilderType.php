<?php

namespace App\Form;

use App\Entity\Recipe;
use App\Entity\Unit;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeBuilderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder          
            ->add('save', SubmitType::class, [
              'attr' => ['class' => 'save'],
            ])
            ->add('title')
            ->add('summary')
            ->add('serving_min_qty')
            ->add('serving_max_qty')
            ->add('serving_unit', EntityType::class, [
                'class' => Unit::class,
                'choice_label' => 'name',
            ])
            ->add('cook_time')
            ->add('version')
            ->add('status')
            ->add('is_public')
            ->add('author', EntityType::class, [                
              'class' => User::class,
              'choice_label' => 'id',
              'hidden'  => true
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

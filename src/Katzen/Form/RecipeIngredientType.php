<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\RecipeIngredient;
use App\Katzen\Entity\Item;
use App\Katzen\Entity\Unit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class RecipeIngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('supply', EntityType::class, [
                'class' => Item::class,
                'choice_label' => 'name',
                'mapped' => false,
            ])
            ->add('quantity')
            ->add('unit', EntityType::class, [
                'class' => Unit::class,
                'choice_label' => 'name',
            ])
            ->add('note');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecipeIngredient::class,
        ]);
    }
}

?>

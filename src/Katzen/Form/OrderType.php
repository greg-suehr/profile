<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Order;
use App\Katzen\Form\AddOrderItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
  /**
   * @return void
   */
  public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('customer', TextType::class, ['required' => false])          
            ->add('notes', TextareaType::class, ['required' => false])
            ->add('orderItems', CollectionType::class, [
                'entry_type' => AddOrderItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => false,
            ])
            ->add('recipeIds', HiddenType::class, [ # TODO: re-implement as recipeQts
              'mapped' => false,
              'required' => false,
              'attr' => ['id' => 'recipe_ids_field'],
            ])
            ->add('submit', SubmitType::class);
    }
}

?>

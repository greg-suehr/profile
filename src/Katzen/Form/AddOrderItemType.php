<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\OrderItem;
use App\Katzen\Entity\Recipe;
use App\Katzen\Form\AddOrderItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextAreaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddOrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('recipe', EntityType::class, [
                'class' => Recipe::class,
                'choice_label' => 'title',
                'placeholder' => 'Choose a dish',
                'property_path' => 'recipe_list_recipe_id'
            ])
            ->add('quantity', IntegerType::class, [
              'property_path' => 'quantity'
            ])
            ->add('submit', SubmitType::class);
    }
}

?>

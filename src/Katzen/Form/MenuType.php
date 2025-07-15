<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\RecipeList;
use App\Katzen\Entity\Recipe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Menu Name',
            ])
            ->add('mealType', ChoiceType::class, [
              'label'   => 'Meal Type',
              'mapped'  => false,
              'choices' => [
                'All Day'    => 'All Day',
                'Breakfast'  => 'Breakfast',
                'Brunch'     => 'Brunch',
                'Lunch'      => 'Lunch',
                'Dinner'     => 'Dinner',
                'Late Night' => 'Late Night',
              ],
              'placeholder' => '— Select Meal Type —',
            ])
            ->add('statusTag', ChoiceType::class, [
              'label'   => 'Status',
              'mapped'  => false,
              'choices' => [
                'Active'   => 'Active',
                'Archived' => 'Archived',                
                'Draft'    => 'Draft',
                'Seasonal' => 'Seasonal',
              ],
              'data' => 'Draft',
            ])
            ->add('current', CheckboxType::class, [
              'label'    => 'Publish',
              'mapped'   => false,
              'required' => false,
            ])
            ->add('recipes', EntityType::class, [
              'class'        => Recipe::class,
              'choice_label' => 'title',
              'multiple'     => true,
              'expanded'     => false,
              'label'        => 'Add Recipes',
              'required'     => false,
            ])
            ->add('submit', SubmitType::class, [
              'label'        => "Publish",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => RecipeList::class,
        ]);
    }
}

?>

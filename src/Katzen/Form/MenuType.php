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
            ->add('meal_type', ChoiceType::class, [
              'label'   => 'Meal Type',
              'mapped'  => true,
              'choices' => [
                'All Day'    => 'all_day',
                'Breakfast'  => 'breakfast',
                'Brunch'     => 'brunch',
                'Lunch'      => 'lunch',
                'Dinner'     => 'dinner',
                'Late Night' => 'late_night',
              ],
              'placeholder' => '— Select Meal Type —',
            ])
            ->add('status', ChoiceType::class, [
              'label'   => 'Status',
              'mapped'  => true,
              'choices' => [
                'Active'   => 'active',
                'Archived' => 'archived',                
                'Draft'    => 'draft',
                'Seasonal' => 'seasonal',
              ],
              'data' => 'Draft',
            ])
            ->add('current', CheckboxType::class, [
              'label'    => 'Publish',
              'mapped'   => true,
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

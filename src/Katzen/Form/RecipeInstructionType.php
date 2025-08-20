<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\RecipeInstruction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class RecipeInstructionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
#          ->add('section_number')
#          ->add('step_number')
          ->add('description')
#          ->add('prep_time')
#          ->add('cook_time')
#          ->add('wait_time')
          ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecipeInstruction::class,
            'allow_extra_fields' => true,
        ]);
    }
}

?>

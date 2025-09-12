<?php

namespace App\Shared\Form;

use App\Shared\Entity\RsvpLog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RsvpType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
              'attr' => [
                'placeholder' => '',
              ]
            ]
            )
            ->add('guestCount', ChoiceType::class, [
              'choices' => [
                'Just Me'  => 1,
                '2 People' => 2,
                '3 People' => 3,
                '4 People' => 4,
                '5+ People' => 5,
              ],
              'label' => 'How many guests?',
            ])
            ->add('note', TextareaType::class, [
              'label' => 'Anything else?',
              'attr' => [
                'placeholder' => 'Special requests, dietary restrictions, etc...',
              ]
            ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RsvpLog::class,
        ]);
    }
}

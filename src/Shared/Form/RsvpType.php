<?php

namespace App\Shared\Form;

use App\Shared\Entity\RsvpLog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
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
                '5 People' => 5,
                'More Than That' => 6,
              ],
              'label' => 'How many guests?',
              'attr' => ['data-guest-select' => '1'], 
            ])
            ->add('guestCountOther', IntegerType::class, [
                'label' => "That's crazy fam. How many you got?",
                'required' => false,
                'mapped' => false,
                'attr' => ['min' => 6, 'data-guest-other' => '1'],
            ])
            ->add('note', TextareaType::class, [
              'label' => 'Anything else?',
              'attr' => [
                'placeholder' => 'Special requests, dietary restrictions, etc...',
              ]
            ]
            )
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();

            $guestCountChoice = $form->get('guestCount')->getData();
            $guestCountOther = $form->get('guestCountOther')->getData();

            if ($guestCountChoice === 6) {
              if (!$guestCountOther || !is_numeric($guestCountOther) || (int)$guestCountOther < 6) {
                $form->get('guestCountOther')->addError(new FormError('Please enter a number 6 or greater.'));
                return;
              }
              
              $rsvpLog = $form->getData();
              if ($rsvpLog instanceof RsvpLog) {
                $rsvpLog->setGuestCount((int)$guestCountOther);
              }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RsvpLog::class,
        ]);
    }
}

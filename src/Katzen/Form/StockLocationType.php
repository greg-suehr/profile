<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\StockLocation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockLocationType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
      ->add('code', TextType::class, [
        'label' => 'Location Code',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'e.g., PREP-01, BAR-MAIN',
        ],
        'help' => 'Short code for quick reference (e.g., PREP-01)',
      ])
      ->add('name', TextType::class, [
        'label' => 'Location Name',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'e.g., Prep Kitchen, Main Bar',
        ],
      ])
      ->add('parent_location', EntityType::class, [
        'class' => StockLocation::class,
        'choice_label' => function (StockLocation $location) {
                    return sprintf('%s (%s)', $location->getName(), $location->getCode());
                },
        'label' => 'Parent Location',
        'placeholder' => 'None (Top-level location)',
        'required' => false,
        'attr' => [
          'class' => 'form-select',
        ],
        'help' => 'Optional: Organize locations hierarchically',
      ])
      ->add('address', TextareaType::class, [
        'label' => 'Address / Description',
        'required' => false,
        'attr' => [
          'class' => 'form-control',
          'rows' => 3,
          'placeholder' => 'Physical address or location description...',
        ],
      ])
      ->add('submit', SubmitType::class, [
        'label' => 'Save Location',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ]);
    }
  
  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'data_class' => StockLocation::class,
    ]);
  }
}

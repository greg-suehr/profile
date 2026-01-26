<?php

namespace App\Katzen\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Import Default Value Entry
 * 
 * Embedded form type for specifying default values for unmapped fields.
 * Used within ImportMappingType as a CollectionType entry.
 */
class ImportDefaultValueType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
      ->add('field', ChoiceType::class, [
        'label' => 'Field',
        'placeholder' => '-- Select field --',
        'choices' => $options['available_fields'] ?? $this->getCommonFields(),
        'attr' => [
          'class' => 'form-select',
        ],
      ])      
      ->add('value', TextType::class, [
        'label' => 'Default Value',
        'attr' => [
          'placeholder' => 'Enter default value...',
          'class' => 'form-control',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'available_fields' => [],
    ]);
    
    $resolver->setAllowedTypes('available_fields', 'array');
  }

  /**
   * Common fields that might need default values
   */
  private function getCommonFields(): array
  {
    return [
      'Status' => 'status',
      'Category' => 'category',
      'Location' => 'location',
      'Vendor' => 'vendor',
      'Unit of Measure' => 'unit_of_measure',
      'Currency' => 'currency',
      'Tax Rate' => 'tax_rate',
    ];
  }
}

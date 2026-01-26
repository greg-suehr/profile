<?php

namespace App\Katzen\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Import Upload Form
 * 
 * Step 1 of the import wizard - file upload and entity type selection.
 * Accepts CSV/Excel files and determines what type of entity to import.
 */
class ImportUploadType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $builder
      ->add('entity_type', ChoiceType::class, [
        'label' => 'What are you importing?',
        'choices' => [
          'Orders' => 'order',
          'Items (Inventory)' => 'item',
          'Sellables (Menu Items)' => 'sellable',
          'Customers' => 'customer',
          'Vendors' => 'vendor',
          'Vendor Invoices' => 'vendor_invoice',
          'Recipes' => 'recipe',
        ],
        'attr' => [
          'class' => 'form-select form-select-lg',
        ],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Please select an entity type to import',
          ]),
        ],
        'help' => 'Choose the type of data contained in your import file',
      ])
      ->add('file', FileType::class, [
        'label' => 'Upload File',
        'attr' => [
          'accept' => '.csv,.xlsx,.xls',
          'class' => 'form-control form-control-lg',
        ],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Please select a file to upload',
          ]),
          new Assert\File([
            'maxSize' => '10M',
            'mimeTypes' => [
              'text/csv',
              'text/plain',
              'application/vnd.ms-excel',
              'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'mimeTypesMessage' => 'Please upload a valid CSV or Excel file',
          ]),
        ],
        'help' => 'Accepts CSV and Excel files up to 10MB',
      ])
      ->add('name', TextType::class, [
        'label' => 'Import Name',
        'required' => false,
        'attr' => [
          'placeholder' => 'e.g., "Q4 2024 Order Backlog" (optional)',
          'class' => 'form-control',
        ],
        'help' => 'Optional name to help identify this import later',
      ])      
      ->add('use_existing_mapping', ChoiceType::class, [
        'label' => 'Use Existing Mapping Template',
        'required' => false,
        'placeholder' => 'Let me configure mappings manually',
        'choices' => $options['available_mappings'] ?? [],
        'attr' => [
          'class' => 'form-select',
        ],
        'help' => 'Skip manual mapping by using a saved template',
      ])
      ->add('submit', SubmitType::class, [
        'label' => 'Continue to Mapping',
        'attr' => [
          'class' => 'btn btn-primary btn-lg',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'available_mappings' => [],
    ]);
    
    $resolver->setAllowedTypes('available_mappings', 'array');
  }
}

<?php

namespace App\Katzen\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Import Mapping Form
 * 
 * Step 2 of the import wizard - map CSV columns to entity fields.
 * Dynamically builds field mappings based on detected headers.
 */
class ImportMappingType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $headers = $options['headers'] ?? [];
    $suggested = $options['suggested'] ?? [];
    $entityType = $options['entity_type'] ?? 'item';
        
    $builder
        ->add('mapping_name', TextType::class, [
          'label' => 'Template Name',
          'required' => false,
          'attr' => [
            'placeholder' => 'e.g., "Sysco Order Export Format"',
            'class' => 'form-control',
          ],
          'help' => 'Save this mapping as a template for future imports',
        ])
        ;

    foreach ($headers as $index => $header) {
      $suggestedField = $suggested[$header] ?? null;
      
      $builder->add('mapping_' . $index, ChoiceType::class, [
        'label' => $header,
        'required' => false,
        'placeholder' => '-- Skip this column --',
        'choices' => $this->getFieldChoicesForEntityType($entityType),
        'data' => $suggestedField,
        'attr' => [
          'class' => 'form-select mapping-field',
          'data-header' => $header,
          'data-suggested' => $suggestedField ? 'true' : 'false',
        ],
        'help' => $suggestedField 
          ? "Suggested: {$suggestedField}" 
          : null,
      ]);
    }
    
    $builder
      ->add('skip_duplicates', CheckboxType::class, [
        'label' => 'Skip Duplicate Records',
        'required' => false,
        'data' => true,
        'help' => 'Check for existing records and skip them during import',
      ])      
      ->add('update_existing', CheckboxType::class, [
        'label' => 'Update Existing Records',
        'required' => false,
        'data' => false,
        'help' => 'If duplicate found, update the existing record instead of skipping',
      ])      
      ->add('create_missing_references', CheckboxType::class, [
        'label' => 'Create Missing References',
        'required' => false,
        'data' => true,
        'help' => 'Automatically create referenced entities (vendors, categories, etc.) if they don\'t exist',
      ])      
      ->add('default_values', CollectionType::class, [
        'label' => 'Default Values',
        'entry_type' => ImportDefaultValueType::class,
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'required' => false,
        'attr' => [
          'class' => 'default-values-collection',
        ],
        'help' => 'Set default values for fields not present in the import file',
      ])
      ->add('save_as_template', CheckboxType::class, [
        'label' => 'Save as Template',
        'required' => false,
        'data' => false,
        'help' => 'Save this mapping configuration for reuse in future imports',
      ])      
      ->add('is_system_template', CheckboxType::class, [
        'label' => 'Make System Template',
        'required' => false,
        'data' => false,
        'help' => 'Available to all users (requires admin privileges)',
      ])
      ->add('submit', SubmitType::class, [
        'label' => 'Continue to Validation',
        'attr' => [
          'class' => 'btn btn-primary btn-lg',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
    $resolver->setDefaults([
      'headers' => [],
      'suggested' => [],
      'entity_type' => 'item',
    ]);
    
    $resolver->setAllowedTypes('headers', 'array');
    $resolver->setAllowedTypes('suggested', 'array');
    $resolver->setAllowedTypes('entity_type', 'string');
  }

  /**
   * Get available field choices based on entity type
   */
  private function getFieldChoicesForEntityType(string $entityType): array
  {
    // This would ideally be generated from entity metadata
    // For now, hardcode common mappings per entity type
        
    $commonFields = [
      'Core Fields' => [
        'ID' => 'id',
        'Name' => 'name',
        'Status' => 'status',
        'Notes' => 'notes',
        'Created At' => 'created_at',
        'Updated At' => 'updated_at',
      ],
    ];
    
    $entitySpecificFields = match ($entityType) {
      'item' => [
        'Item Details' => [
          'SKU' => 'sku',
          'UPC/Barcode' => 'upc',
          'Item Code' => 'item_code',
          'Unit of Measure' => 'unit_of_measure',
          'Package Size' => 'package_size',
          'Category' => 'category',
        ],
        'Pricing' => [
          'Cost per Unit' => 'cost_per_unit',
          'Last Cost' => 'last_cost',
        ],
        'Inventory' => [
          'Current Quantity' => 'quantity',
          'Minimum Stock' => 'min_stock',
          'Maximum Stock' => 'max_stock',
          'Reorder Point' => 'reorder_point',
        ],
        'Vendor Info' => [
          'Vendor' => 'vendor',
          'Vendor SKU' => 'vendor_sku',
        ],
      ],
      'order' => [
        'Order Details' => [
          'Order Number' => 'order_number',
          'Customer' => 'customer',
          'Order Date' => 'order_date',
          'Delivery Date' => 'delivery_date',
          'Total Amount' => 'total_amount',
        ],
      ],
      'vendor' => [
        'Vendor Details' => [
          'Vendor Code' => 'vendor_code',
          'Email' => 'email',
          'Phone' => 'phone',
          'Website' => 'website',
          'Payment Terms' => 'payment_terms',
        ],
      ],
      default => [],
    };
    
    return array_merge($commonFields, $entitySpecificFields);
  }
}

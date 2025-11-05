<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Vendor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Manual Vendor entry.
 */
class VendorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
          ->add('name', TextType::class, [
            'label' => 'Vendor Name',
            'attr' => [
              'placeholder' => 'e.g., Sysco Corporation',
              'class' => 'form-control',
              'autocomplete' => 'organization',
            ],
                'constraints' => [
                  new Assert\NotBlank(),
                  new Assert\Length(['max' => 255]),
                ],
            'help' => 'Legal business name as it appears on invoices',
          ])
          
          ->add('vendor_code', TextType::class, [
            'label' => 'Vendor Code',
                'required' => false,
            'attr' => [
              'placeholder' => 'Internal reference code, leave blank to auto-generate',
              'class' => 'form-control',
            ],
            'help' => 'Your internal reference code for this vendor',
          ])
          
          ->add('status', ChoiceType::class, [
            'label' => 'Status',
            'choices' => [
              'Active' => 'active',
              'Inactive' => 'inactive',
              'Pending Approval' => 'pending',
            ],
            'attr' => ['class' => 'form-select'],
          ])

          // ===================================================================
          // CONTACT INFORMATION
          // ===================================================================
          ->add('email', EmailType::class, [
            'label' => 'Email',
            'required' => false,
            'attr' => [
              'placeholder' => 'orders@vendor.com',
              'class' => 'form-control',
              'autocomplete' => 'email',
            ],
            'help' => '🤖 Domain extracted for OCR matching',
          ])
          
          ->add('phone', TelType::class, [
            'label' => 'Phone',
            'required' => false,
            'attr' => [
              'placeholder' => '(412) 555-0100',
              'class' => 'form-control',
              'autocomplete' => 'tel',
            ],
          ])
          
          ->add('fax', TelType::class, [
            'label' => 'Fax',
            'required' => false,
            'attr' => [
              'placeholder' => '(412) 555-0101',
              'class' => 'form-control',
            ],
          ])
          
          ->add('website', UrlType::class, [
            'label' => 'Website',
            'required' => false,
            'attr' => [
              'placeholder' => 'https://www.vendor.com',
              'class' => 'form-control',
              'autocomplete' => 'url',
            ],
          ])

          ->add('billing_address', TextareaType::class, [
            'label' => 'Billing Address',
            'required' => false,
            'attr' => [
              'rows' => 3,
              'placeholder' => "123 Main Street\nPittsburgh, PA 15220",
              'class' => 'form-control',
            ],
          ])
            
          ->add('shipping_address', TextareaType::class, [
            'label' => 'Shipping Address',
            'required' => false,
            'attr' => [
              'rows' => 3,
              'placeholder' => "Same as billing, or enter different address",
              'class' => 'form-control',
            ],
            'help' => 'Leave blank if same as billing address',
          ])

          // ===================================================================
          // TAX & FINANCIAL INFORMATION
          // ===================================================================
          ->add('tax_id', TextType::class, [
            'label' => 'Tax ID / EIN',
            'required' => false,
            'attr' => [
              'placeholder' => '12-3456789',
              'class' => 'form-control',
              'pattern' => '\d{2}-?\d{7}',
            ],
          ])
            
          ->add('tax_classification', ChoiceType::class, [
            'label' => 'Tax Classification',
            'required' => false,
            'choices' => [
              'Select...' => '',
              'Corporation' => 'corp',
              'LLC' => 'llc',
              'Partnership' => 'partnership',
              'Sole Proprietor' => 'sole_prop',
              'Other' => 'other',
            ],
            'attr' => ['class' => 'form-select'],
          ])
            
          ->add('payment_terms', ChoiceType::class, [
            'label' => 'Payment Terms',
            'required' => false,
            'choices' => [
              'Due on Receipt' => 'immediate',
              'Net 15' => 'Net 15',
              'Net 30' => 'Net 30',
              'Net 45' => 'Net 45',
              'Net 60' => 'Net 60',
              'Net 90' => 'Net 90',
              '2/10 Net 30' => '2/10 Net 30',
              '1/10 Net 30' => '1/10 Net 30',
            ],
            'attr' => ['class' => 'form-select'],
            'help' => 'Default payment terms for invoices from this vendor',
          ])
            
          ->add('credit_limit', MoneyType::class, [
            'label' => 'Credit Limit',
            'required' => false,
            'currency' => 'USD',
            'attr' => [
              'placeholder' => '10000.00',
              'class' => 'form-control',
            ],
            'help' => 'Maximum amount you can owe this vendor',
          ])
          
          ->add('current_balance', MoneyType::class, [
            'label' => 'Current Balance',
            'required' => false,
            'currency' => 'USD',
            'attr' => [
              'placeholder' => '0.00',
              'class' => 'form-control',
              'readonly' => true,
            ],
                'help' => 'Auto-calculated from invoices (read-only)',
            'disabled' => true,
          ])
          
          ->add('vendor_aliases', CollectionType::class, [
            'label' => 'Brand Aliases (for OCR)',
            'entry_type' => TextType::class,
            'required' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            'attr' => [
              'class' => 'ocr-aliases-collection',
            ],
            'entry_options' => [
              'label' => false,
              'attr' => [
                'placeholder' => 'e.g., SYSCO, Sysco Food, SFS',
                'class' => 'form-control mb-2',
              ],
            ],
            'help' => 'Add brand names that appear on receipts (e.g., "SYSCO", "US FOODS")',
          ])
            
          ->add('vendor_domains', CollectionType::class, [
            'label' => 'Additional Domains',
            'entry_type' => TextType::class,
            'required' => false,
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            'entry_options' => [
              'label' => false,
              'attr' => [
                'placeholder' => 'e.g., syscofoodsystems.com',
                'class' => 'form-control mb-2',
              ],
            ],
          ])

          // ===================================================================
          // NOTES
          // ===================================================================
          ->add('notes', TextareaType::class, [
            'label' => 'Notes',
            'required' => false,
            'attr' => [
              'rows' => 4,
              'placeholder' => 'Internal notes about this vendor...',
              'class' => 'form-control',
            ],
          ])
          
          // ===================================================================
          // SUBMIT BUTTON
          // ===================================================================
          ->add('submit', SubmitType::class, [
            'label' => 'Save Vendor',
            'attr' => ['class' => 'btn btn-primary btn-lg'],
          ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vendor::class,
        ]);
    }
}

<?php

namespace App\Katzen\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Form for importing vendor invoices via OCR scan
 * Accepts images (jpg, png, heic) and PDFs
 */
class ImportVendorInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Upload Invoice or Receipt',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'accept' => 'image/*,.pdf',
                    'class' => 'form-control',
                ],
                'help' => 'Supported: JPG, PNG, HEIC, PDF (max 10MB)',
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/heic',
                            'image/heif',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image or PDF file',
                    ])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Scan & Import',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Not mapped to any entity - we process the file directly
        ]);
    }
}

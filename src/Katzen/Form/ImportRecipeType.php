<?php

namespace App\Katzen\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextAreaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImportRecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label'       => 'Upload Recipe (JSON, CSV, PDF)',
                'required'    => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => ['application/json', 'text/csv', 'application/pdf'],
                        'mimeTypesMessage' => 'Please upload a valid JSON, CSV, or PDF file',
                    ]),
                ],
            ])->add('json_text', TextAreaType::class, [
                'label'    => 'Paste JSON Recipe [Optional]',
                'required' => false,
                'attr'     => ['rows' => 6, 'placeholder' => 'Paste JSON here...'],
            ])->add('submit', SubmitType::class, ['label' => 'Import Recipe']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

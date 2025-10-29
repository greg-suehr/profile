<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Vendor;
use App\Katzen\Entity\VendorInvoice;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class VendorCreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('vendor', EntityType::class, ['class' => Vendor::class])
            ->add('credit_number', TextType::class)
            ->add('credit_date', DateType::class, ['widget' => 'single_text'])
            ->add('credit_amount', MoneyType::class)
            ->add('reason', TextareaType::class)
            ->add('original_invoice', EntityType::class, [
                'class' => VendorInvoice::class,
                'required' => false,
                'placeholder' => 'Link to invoice (optional)...',
            ])
            ->add('submit', SubmitType::class, ['label' => 'Create Credit']);
    }
}

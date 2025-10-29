<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\Vendor;
use App\Katzen\Form\VendorInvoiceItemType;
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

class VendorInvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('vendor', EntityType::class, [
                'class' => Vendor::class,
                'placeholder' => 'Select vendor...',
            ])
            ->add('invoice_number', TextType::class)
            ->add('invoice_date', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('due_date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('subtotal', MoneyType::class)
            ->add('tax_amount', MoneyType::class, ['required' => false])
            ->add('shipping_amount', MoneyType::class, ['required' => false])
            ->add('total_amount', MoneyType::class)
            ->add('purchase', EntityType::class, [
                'class' => Purchase::class,
                'required' => false,
                'placeholder' => 'Link to PO (optional)...',
            ])
            ->add('notes', TextareaType::class, ['required' => false])

            ->add('items', CollectionType::class, [
                'entry_type' => VendorInvoiceItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Save Invoice',
            ]);
    }
}

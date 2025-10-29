<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Account;
use App\Katzen\Entity\StockTarget;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VendorInvoiceItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock_target', EntityType::class, [
                'class' => StockTarget::class,
                'required' => false,
                'placeholder' => 'Select item...',
                'attr' => ['class' => 'item-autocomplete'],
            ])
            ->add('description', TextType::class)
            ->add('quantity', NumberType::class, [
                'scale' => 3,
            ])
            ->add('unit_of_measure', TextType::class, [
                'required' => false,
            ])
            ->add('unit_price', MoneyType::class, [
                'scale' => 4,
            ])
            ->add('expense_account', EntityType::class, [
                'class' => Account::class,
                'placeholder' => 'Auto-assign',
                'required' => false,
            ])
            ->add('cost_center', TextType::class, ['required' => false])
            ->add('department', TextType::class, ['required' => false]);
    }
}

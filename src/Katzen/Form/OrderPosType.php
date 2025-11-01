<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Order;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderPosType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer_entity', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'label' => 'Customer',
                'placeholder' => 'Select a customer...',
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('scheduled_at', DateTimeType::class, [
                'label' => 'Scheduled For',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Order Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Special instructions...',
                ],
            ])
            ->add('recipeIds', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'id' => 'order-data-field',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Save Order',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}

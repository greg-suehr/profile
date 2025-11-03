<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\PriceRule;
use App\Katzen\Entity\Sellable;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextAreaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PriceRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Rule Name',
                'attr' => [
                    'placeholder' => 'e.g., VIP Discount, Happy Hour, Bulk Order',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextAreaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Describe what this rule does and when it applies',
                    'class' => 'form-control',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Rule Type',
                'choices' => [
                    'Customer Segment' => 'customer_segment',
                    'Volume Tier' => 'volume_tier',
                    'Promotion' => 'promotion',
                    'Time-based' => 'time_based',
                    'Fixed Price' => 'fixed_price',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'The type of pricing rule to apply',
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Priority',
                'attr' => [
                    'placeholder' => '100',
                    'class' => 'form-control',
                    'min' => '0',
                ],
                'help' => 'Lower numbers = higher priority. Rules are evaluated in priority order.',
            ])
            ->add('stackable', CheckboxType::class, [
                'label' => 'Stackable',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Can this rule be combined with other rules?',
            ])
            ->add('exclusive', CheckboxType::class, [
                'label' => 'Exclusive',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Should rule evaluation stop after this rule is applied?',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('conditions', TextAreaType::class, [
                'label' => 'Conditions',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control font-monospace',
                    'placeholder' => '[{"field": "customer.segment", "operator": "in", "value": ["wholesale", "vip"]}]',
                ],
                'help' => 'JSON array of conditions that must be met for this rule to apply',
            ])
            ->add('actions', TextAreaType::class, [
                'label' => 'Actions',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control font-monospace',
                    'placeholder' => '[{"type": "percentage_discount", "value": 15}]',
                ],
                'help' => 'JSON array of pricing actions to perform when conditions are met',
            ])
            ->add('applicableSellables', EntityType::class, [
                'class' => Sellable::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label' => 'Applicable Items',
                'attr' => [
                    'class' => 'form-select',
                    'size' => 10,
                ],
                'help' => 'Leave empty to apply to all items, or select specific items',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PriceRule::class,
        ]);
    }
}

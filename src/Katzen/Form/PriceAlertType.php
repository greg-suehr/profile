<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\StockTarget;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PriceAlertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock_target', EntityType::class, ['class' => StockTarget::class])
            ->add('alert_type', ChoiceType::class, [              
                'choices' => [
                    'Percentage increase' => 'threshold_pct',
                    'Dollar amount increase' => 'threshold_abs',
                    'Any upward trend' => 'trend_increase',
                    'All-time high' => 'all_time_high',
                ],
            ])
            ->add('threshold_value', NumberType::class, [
                'required' => false,
                'label' => 'Threshold (% or $)',
            ])
            ->add('notify_email', EmailType::class, ['required' => false])
            ->add('enabled', CheckboxType::class, ['required' => false])
            ->add('submit', SubmitType::class, ['label' => 'Create Alert']);
    }
}

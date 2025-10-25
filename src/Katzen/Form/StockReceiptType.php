<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Purchase;
use App\Katzen\Entity\StockLocation;
use App\Katzen\Entity\StockReceipt;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockReceiptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('purchase', EntityType::class, [
                'class' => Purchase::class,
                'choice_label' => function (Purchase $purchase) {
                    return sprintf(
                        'PO-%s - %s (%s)',
                        $purchase->getPoNumber(),
                        $purchase->getVendor()?->getName() ?? 'Unknown',
                        $purchase->getStatus()
                    );
                },
                'label' => 'Purchase Order',
                'placeholder' => 'Select a PO to receive...',
                'attr' => [
                    'class' => 'form-select',
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->where('p.status IN (:statuses)')
                        ->setParameter('statuses', ['pending', 'partial'])
                        ->orderBy('p.expected_delivery', 'ASC');
                },
            ])
            ->add('location', EntityType::class, [
                'class' => StockLocation::class,
                'choice_label' => 'name',
                'label' => 'Receiving Location',
                'attr' => [
                    'class' => 'form-select',
                ],
                'required' => true,
            ])
            ->add('received_at', DateTimeType::class, [
                'label' => 'Received Date',
                'widget' => 'single_text',
                'data' => new \DateTime(),
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Any notes about this receipt...',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Continue to Items',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StockReceipt::class,
        ]);
    }
}
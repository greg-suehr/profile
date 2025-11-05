<?php

namespace App\Profile\Form;

use App\Profile\Model\FieldDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldDefinitionType extends AbstractType
{
  /**
   * @return void
   */
    public function buildForm(FormBuilderInterface $builder, array $opts)
    {
        $builder
            ->add('name',  TextType::class, ['help'=>'Key for data JSON'])
            ->add('label', TextType::class)
            ->add('type',  ChoiceType::class, [
                'choices' => [
                    'Text'     => 'text',
                    'Integer'  => 'integer',
                    'Boolean'  => 'checkbox',
                    'Choice'   => 'choice',
                    'Date'     => 'date',
                ],
            ])
            ->add('options', TextType::class, [
                'required'=>false,
                'help'    => 'JSON encoded extra options (e.g. {"choices": {"a":"A","b":"B"}})'
            ])
        ;
    }

  /**
   * @return void
   */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => FieldDefinition::class,
        ]);
    }
}

?>

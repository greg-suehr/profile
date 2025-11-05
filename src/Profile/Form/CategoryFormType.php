<?php

namespace App\Profile\Form;

use App\Profile\Entity\Category;
use App\Profile\Model\FieldDefinition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryFormType extends AbstractType
{
  /**
   * @return void
   */
  public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // basic name/desc fields…
        $builder
            ->add('name', TextType::class)
            ->add('description', TextType::class)
            ->add('schema', CollectionType::class, [
                'entry_type'   => FieldDefinitionType::class,
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
                'mapped'       => false,
            ])
        ;
    }

  /**
   * @return void
   */
  public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}

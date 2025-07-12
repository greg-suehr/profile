<?php

namespace App\Profile\Form;

use App\Profile\Entity\Content;
use App\Profile\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentFormType extends AbstractType
{
    private array $schema;

    public function __construct(
      CategoryRepository $categoryRepo,
    )
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $schema = $options['field_schema'];
        foreach ($schema as $name => $def) {
            $typeClass = match($def['type']) {
                'text'     => TextType::class,
                'integer'  => IntegerType::class,
                'checkbox' => CheckboxType::class,
                'choice'   => ChoiceType::class,
                'date'     => DateType::class,
                default    => TextType::class,
            };

            $fieldOptions = $def['options'] ?? [];
            $fieldOptions['label'] = $def['label'] ?? ucfirst($name);

            $builder->add($name, $typeClass, $fieldOptions);
        }

        // adds the JSON field itself to allow saving into Content.data
        $builder->add('data', HiddenType::class, ['mapped'=>false]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('field_schema');
        $resolver->setDefaults([
            'data_class' => Content::class,
        ]);
    }
}

?>

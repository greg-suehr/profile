<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('query', SearchType::class, [
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Search recipes...']
            ])
            ->add('cuisine', ChoiceType::class, [
                'required' => false,
                'choices' => $options['cuisines'],
                'placeholder' => 'Any Cuisine',
                'label' => false,
            ])
            ->add('ingredients', ChoiceType::class, [
                'required' => false,
                'choices' => $options['ingredients'],
                'multiple' => true,
                'expanded' => true,
                'label' => false,
            ])
            ->add('tags', ChoiceType::class, [
                'required' => false,
                'choices' => $options['tags'],
                'multiple' => true,
                'expanded' => true,
                'label' => false,
            ])
            ->add('author', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => ['placeholder' => 'Author username']
            ])          
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'cuisines' => [],
            'ingredients' => [],
            'tags' => [],
        ]);
    }
}

?>

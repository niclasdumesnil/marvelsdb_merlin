<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('decks', EntityType::class, array(
                'class' => 'AppBundle:Deck',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => true,
            ))
            ->add('visibility', ChoiceType::class, array('choices' => array('Public' => 'public', 'Private' => 'private')))
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\\Entity\\Team'
        ));
    }

    public function getName()
    {
        return 'appbundle_teamtype';
    }
}

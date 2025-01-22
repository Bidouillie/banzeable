<?php

namespace App\Form;

use App\Entity\Move;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoveBuilderVariationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('variation', VariationFromPGNType::class, [
                'row_attr' => [
                    'class' => 'd-none',
                ]
            ])
            ->add('selectedPercentHistory', CollectionType::class, [
                'allow_add' => true,
                'entry_type' => TextType::class,
                'row_attr' => [
                    'class' => 'd-none',
                ],
            ])
            ->add('movesMerged', EntityType::class, [
                'class' => Move::class,
                'multiple' => true,
                'required' => false,
                'choice_label' => 'id',
                'row_attr' => [
                    'class' => 'd-none',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}

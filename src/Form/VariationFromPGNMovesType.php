<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Variation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VariationFromPGNMovesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'name',
                'row_attr' => [
                    'class' => 'd-none',
                ],
            ])
            ->add('PGN', HiddenType::class)
            ->add('selectedPercentHistory', CollectionType::class, [
                'allow_add' => true,
                'row_attr' => [
                    'class' => 'd-none',
                ],
            ])
            ->add('action', SubmitType::class, [
                'label' => "Save variation",
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Variation::class,
        ]);
    }
}

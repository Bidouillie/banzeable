<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseAspect;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('aspect', EnumType::class, [
                'class' => CourseAspect::class,
            ])
            ->add('blackOrientation', ChoiceType::class, [
                'label' => "Orientation",
                'choices' => [
                    'White' => false,
                    'Black' => true,
                ],
            ])
            ->add('coverage', ChoiceType::class, [
                'choices' => [
                    'Select option' => null,
                    '1 in 50' => 50,
                    '1 in 75' => 75,
                    '1 in 100' => 100,
                    '1 in 150' => 150,
                ],
            ])
            ->add('rating')
            ->add('price')
            ->add('owners', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'firstname',
                'multiple' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}

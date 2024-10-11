<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Administrator' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            // TODO get assert from entity ?
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank,
                    new Assert\Length([
                        'min' => 5,
                        'minMessage' => 'The password should be at least {{ limit }} characters',
                        // 4096 max length allowed by Symfony for security reasons
                        'max' => 32,
                    ]),
                    // new Assert\NotCompromisedPassword(),
                    // new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM),
                    // new Assert\Regex('/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*\W)(?!.*\s).{8,32}$/', "The password must contain at least 1 lower case letter [a-z], 1 uppercase letter [A-Z], 1 numeric character [0-9] and 1 special character [~`!@#$%^&*()-_+={}[]|\;:\"<>,./?]"),
                ],
            ])
            ->add('firstname')
            ->add('surname')
            ->add('courses', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'id',
                'multiple' => true,
                'required' => false,
            ])
            ->add('studies', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'id',
                'multiple' => true,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

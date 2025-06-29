<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonMergeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('notes_json', FileType::class)
            ->add('notes_key', ChoiceType::class, [
                'required' => false,
                'choices'  => [],
                'row_attr' => ['class' => 'd-none'],
            ])
            ->add('notes_extra_json', FileType::class)
            ->add('notes_extra_key', ChoiceType::class, [
                'required' => false,
                'choices'  => [],
                'row_attr' => ['class' => 'd-none'],
            ])
            ->add('notes_extra_fields', ChoiceType::class, [
                'required' => false,
                'choices'  => [],
                'row_attr' => ['class' => 'd-none'],
            ])
            ->add('notes_extra_fields_destination', ChoiceType::class, [
                'required' => false,
                'choices'  => [],
                'row_attr' => ['class' => 'd-none'],
            ])
        ;

        $formModifier = function (FormInterface $form, ?UploadedFile $notesFile = null, $extra = false): void {

            if (isset($notesFile)) {
                $notesJson = json_decode(file_get_contents($notesFile->getPathname()), null, 512, JSON_OBJECT_AS_ARRAY);

                $count = 0;
                foreach ($notesJson['note_models'] as $notesModelJson) {
                    if ($notesModelJson['__type__'] === 'NoteModel') {
                        $count++;
                        $modelId = strval($notesModelJson['crowdanki_uuid']);
                        $noteFlds = $notesModelJson['flds'];
                    }
                }

                if ($count <= 1 && isset($modelId) && isset($noteFlds)) {
                    $choices = [];
                    foreach ($noteFlds as $field) {
                        $name = $field['name'];
                        $duplicateCount = 1;
                        while (isset($choices[$name])) {
                            if (strrpos($name, " ($duplicateCount)") === strlen($name) - strlen(" ($duplicateCount)")) {
                                $name = substr_replace($name, '', strrpos($name, " ($duplicateCount)"), strlen(" ($duplicateCount)"));
                            }
                            $duplicateCount++;
                            $name .= " ($duplicateCount)";
                        }
                        $choices[$name] = $field['ord'];
                    }
                }
            }

            if (count($choices) !== count(array_unique($choices))) {
                throw new \Exception("Problem with note model fields");
            }

            $form->add($extra ? 'notes_extra_key' : 'notes_key', ChoiceType::class, [
                'required' => isset($choices) ? true : false,
                'choices' => $choices ?? [],
                'row_attr' => empty($choices) ? ['class' => 'd-none'] : [],
            ]);

            if ($extra) {
                $form->add('notes_extra_fields', ChoiceType::class, [
                    'required' => isset($choices) ? true : false,
                    'choices' => $choices ?? [],
                    'row_attr' => empty($choices) ? ['class' => 'd-none'] : [],
                ]);
            } else {
                $form->add('notes_extra_fields_destination', ChoiceType::class, [
                    'required' => isset($choices) ? true : false,
                    'choices' => $choices ?? [],
                    'row_attr' => empty($choices) ? ['class' => 'd-none'] : [],
                ]);
            }
        };

        /*
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier): void {

                $data = $event->getData();

                if (isset($data['notes_json'])) {
                    $formModifier($event->getForm(), ['notes_json']);
                }
                if (isset($data['notes_extra_json'])) {
                    $formModifier($event->getForm(), ['notes_json'], true);
                }
            }
        );
        */

        $builder->get('notes_json')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifier): void {
                // It's important here to fetch $event->getForm()->getData(), as
                // $event->getData() will get you the client data (that is, the ID)
                $notesFile = $event->getForm()->getData();

                // since we've added the listener to the child, we'll have to pass on
                // the parent to the callback function!
                $formModifier($event->getForm()->getParent(), $notesFile);
            }
        );

        $builder->get('notes_extra_json')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifier): void {
                // It's important here to fetch $event->getForm()->getData(), as
                // $event->getData() will get you the client data (that is, the ID)
                $notesFile = $event->getForm()->getData();

                // since we've added the listener to the child, we'll have to pass on
                // the parent to the callback function!
                $formModifier($event->getForm()->getParent(), $notesFile, true);
            }
        );

        // $builder->setAction($options['action']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}

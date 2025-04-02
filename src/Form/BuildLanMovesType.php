<?php

namespace App\Form;

use App\Entity\Move;
use Chess\FenToBoardFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BuildLanMovesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fen', Type\TextType::class, [
                'required' => false,
            ])
            ->add('diverged', Type\CheckboxType::class, [
                'required' => false,
            ])
            ->add('merged', Type\CheckboxType::class, [
                'required' => false,
            ])
            ->add('lanMoves', Type\TextType::class, [
                'required' => false,
            ])
            ->add('expectedPercentage', Type\NumberType::class, [
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ]);

        /**
         * @param array<Move> $moves
         */
        $builder->get('lanMoves')->addModelTransformer(new CallbackTransformer(
            function (?array $moves): string {
                return implode(' ', array_map(function ($move) {
                    return $move->getLan();
                }, $moves ?? []));
            },
            function (?string $lans): array {
                if (empty($lans)) {
                    return [];
                }

                if (!preg_match('/^(([a-h][0-9]){2}( |$))*$/', $lans)) {
                    throw new TransformationFailedException("The lan moves string is not correctly formatted");
                }
                return array_map(function (string $lanMove) {
                    $move = new Move();
                    $move->setLan($lanMove);
                    return $move;
                }, explode(' ', trim($lans)));
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Assert\Callback([self::class, 'validate']),
            ],
        ]);
    }

    public static function validate($form, ExecutionContextInterface $context)
    {
        if (!empty($form['merged']) && empty($form['diverged'])) {
            $context->buildViolation("Moves cannot have merged without diverging")
                ->atPath('merged')
                ->addViolation()
            ;
        }

        if (isset($form['lanMoves'])) {

            $board = FenToBoardFactory::create($form['fen']);

            /**
             * @var Move $move
             */
            foreach ($form['lanMoves'] as $move) {

                $fenFrom = $board->toFen();
                if (!$board->playLan($board->turn, $move->getLan())) {
                    $context->buildViolation("List of moves is not correct (move {$move->getLan()} from fen $fenFrom)")
                        ->atPath('lanMoves')
                        ->addViolation()
                    ;
                }
                $fenTo = $board->toFen();

                $move->setFenFrom($fenFrom);
                $move->setFenTo($fenTo);
            }
        }
    }
}

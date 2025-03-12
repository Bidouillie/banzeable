<?php

namespace App\Form;

use App\Entity\Move;
use Chess\FenToBoardFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BuildLanMovesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('baseFen', TextType::class)
            ->add('lanMoves', TextType::class, [
                'required' => false,
            ])
            ->add('expectedPercentage', NumberType::class, [
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('missingPercentages', IntegerType::class, [
                'required' => false,
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
                if(empty($lans)) {
                    return [];
                }
                if (!preg_match('/^(([a-h][0-9]){2}( ([a-h][0-9]){2})*)?$/', $lans)) {
                    throw new TransformationFailedException("The lan moves string is not correctly formatted");
                }
                return array_map(function (string $lanMove) {
                    $move = new Move();
                    $move->setLan($lanMove);
                    return $move;
                }, explode(' ', $lans));
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
        if (isset($form['lanMoves'])) {

            if (isset($form['missingPercentages'])) {
                if ($form['missingPercentages'] > count($form['lanMoves'])) {
                    $context->buildViolation(sprintf("Number of missing percentages is too high (%d missing for only %d move%s)", $form['missingPercentages'], count($form['lanMoves']), count($form['lanMoves']) > 1 ? 's' : ''))
                        ->atPath('missingPercentages')
                        ->addViolation()
                    ;
                }
            }

            $board = FenToBoardFactory::create($form['baseFen']);

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

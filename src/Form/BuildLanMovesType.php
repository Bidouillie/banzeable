<?php

namespace App\Form;

use App\Entity\Move;
use Chess\FenToBoardFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BuildLanMovesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('baseFen', TextType::class)
            ->add('lanMoves', TextType::class, [
                'required' => false,
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
                return empty($lans) ? [] : array_map(function (string $lanMove) {
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
                new Callback([self::class, 'validate']),
            ],
        ]);
    }

    public static function validate($form, ExecutionContextInterface $context)
    {
        if (isset($form['lanMoves'])) {

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

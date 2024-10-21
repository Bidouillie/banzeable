
import { ChessboardEngine } from './chessboardengine/chessboardengine.js'
import { toast } from './toast.js';

let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new ChessboardEngine(document.getElementById('board'), variation.moves.map((move) => {
        return move.notation.text;
    }), variation.blackOrientation ? 'b' : 'w');

    board.enablePlayableMove((variationMove) => {
        if (variationMove) {
            toast('Good move', 'success');
        } else {
            toast('Wrong move', 'danger');
        }
    }, 1000, 'variation');

    /*
    document.addEventListener('keydown', (event) => {
        switch (event.key) {
            case 'ArrowLeft':
                board.previousMove();
                break;
            case 'ArrowRight':
                board.nextMove();
                break;
        }
    });
    */
});

import { ChessboardEngine } from './chessboardengine/chessboardengine.js'
import { ToastMaker } from './toast.js';

let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new ChessboardEngine(document.getElementById('board'), variation.moves.map((move) => {
        return move.notation.text;
    }), variation.blackOrientation ? 'b' : 'w');

    board.enablePlayableMove((event) => {
        if (event.moveValidation) {
            if (event.lastMove) {
                ToastMaker.toast('Good move, variation is over!', 'success', 3000);
            } else {
                ToastMaker.toast('Good move', 'success');
            }
        } else {
            ToastMaker.toast('Wrong move', 'danger', 2000);
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
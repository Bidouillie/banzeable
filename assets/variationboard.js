
import { ChessboardEngine } from './chessboardengine/chessboardengine.js'

let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new ChessboardEngine(document.getElementById('board'), variation.blackOrientation ? 'b' : 'w', variation.moves.map((move) => {
        return move.notation.text;
    }));

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
});
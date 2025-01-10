
import { AnalysisChessboardEngine } from './chessboardengine/analysis-cbe.js';

let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new AnalysisChessboardEngine(document.getElementById('board'), variation.blackOrientation ? 'b' : 'w', null, variation.moves.map((move) => {
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